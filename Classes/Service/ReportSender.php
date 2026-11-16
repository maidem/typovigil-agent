<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Registry;

/**
 * Sends the package list to the hub.
 *
 * The instance pushes; the hub never reaches in here. That way a monitored site
 * needs no publicly reachable endpoint of its own.
 */
final readonly class ReportSender
{
    private const REGISTRY_NAMESPACE = 'typovigil_agent';
    private const REGISTRY_KEY = 'lastReportHash';
    private const TIMEOUT = 15;
    /** The change listener runs inside extension:setup, so a slow hub must not hold it up. */
    public const LISTENER_TIMEOUT = 5;

    public function __construct(
        private PackageCollector $collector,
        private RequestFactory $requestFactory,
        private Registry $registry,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param bool $onlyOnChange Skip the request when nothing changed since last time
     * @param int $timeout Seconds to wait for the hub
     * @return bool true when the hub accepted the report, or when there was nothing to send
     */
    public function send(bool $onlyOnChange = false, int $timeout = self::TIMEOUT): bool
    {
        $config = $this->configuration();
        if ($config === null) {
            $this->logger->warning('TypoVigil agent: hubUrl or token not configured, skipping report');

            return false;
        }

        [$hubUrl, $token] = $config;

        // A failing collector must not take a scheduler run or a deploy down with it.
        try {
            $body = json_encode($this->collector->collect(), JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->error('TypoVigil agent: could not build the report', ['exception' => $e->getMessage()]);

            return false;
        }

        $hash = hash('sha256', $body);

        if ($onlyOnChange && $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY) === $hash) {
            return true;
        }

        try {
            $response = $this->requestFactory->request(
                rtrim($hubUrl, '/') . '/typovigil/report',
                'POST',
                [
                    'timeout' => $timeout,
                    // The bearer token must never follow a redirect somewhere else.
                    'allow_redirects' => false,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $body,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('TypoVigil agent: report failed', ['exception' => $e->getMessage()]);

            return false;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $this->logger->error('TypoVigil agent: hub rejected the report', ['status' => $status]);

            return false;
        }

        // Only remember the hash after the hub confirmed, so a failed send is retried.
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, $hash);

        return true;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function configuration(): ?array
    {
        // ENV only: config/system/settings.php (where the extension
        // configuration used to live) is rebuilt fresh from the image on
        // every container deploy — a value entered there would be gone after
        // the next one. There used to be a backend module and a one-click
        // setup link for exactly that file; both were removed once every
        // monitored instance turned out to run in a container, which made
        // them a UI that quietly stopped doing anything after the first
        // deploy.
        $hubUrl = trim((string)getenv('TYPOVIGIL_AGENT_HUB_URL'));
        $token = trim((string)getenv('TYPOVIGIL_AGENT_TOKEN'));

        if ($hubUrl === '' || $token === '') {
            return null;
        }

        // Refuse to send a bearer token over plain http — it would travel readable.
        if (!self::isSecureHubUrl($hubUrl)) {
            $this->logger->error('TypoVigil agent: hubUrl must use https', ['hubUrl' => $hubUrl]);

            return null;
        }

        return [$hubUrl, $token];
    }

    public static function isSecureHubUrl(string $hubUrl): bool
    {
        if (str_starts_with($hubUrl, 'https://')) {
            return true;
        }

        $host = parse_url($hubUrl, PHP_URL_HOST);

        return str_starts_with($hubUrl, 'http://') && ($host === 'localhost' || $host === '127.0.0.1');
    }
}
