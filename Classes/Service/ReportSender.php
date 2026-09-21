<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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

    public function __construct(
        private PackageCollector $collector,
        private ExtensionConfiguration $extensionConfiguration,
        private RequestFactory $requestFactory,
        private Registry $registry,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param bool $onlyOnChange Skip the request when nothing changed since last time
     * @return bool true when the hub accepted the report, or when there was nothing to send
     */
    public function send(bool $onlyOnChange = false): bool
    {
        $config = $this->configuration();
        if ($config === null) {
            $this->logger->warning('TypoVigil agent: hubUrl or token not configured, skipping report');

            return false;
        }

        [$hubUrl, $token] = $config;
        $report = $this->collector->collect();
        $hash = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR));

        if ($onlyOnChange && $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY) === $hash) {
            return true;
        }

        try {
            $response = $this->requestFactory->request(
                rtrim($hubUrl, '/') . '/typovigil/report',
                'POST',
                [
                    'timeout' => self::TIMEOUT,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($report, JSON_THROW_ON_ERROR),
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
        try {
            $config = $this->extensionConfiguration->get('typovigil_agent');
        } catch (\Throwable) {
            $config = [];
        }

        // ENV first: config/system/settings.php (where the extension
        // configuration lives) is rebuilt fresh from the image on every
        // container deploy — values entered there are gone after the next
        // one. The environment survives that.
        $hubUrl = trim((string)(getenv('TYPOVIGIL_AGENT_HUB_URL') ?: ($config['hubUrl'] ?? '')));
        $token = trim((string)(getenv('TYPOVIGIL_AGENT_TOKEN') ?: ($config['token'] ?? '')));

        if ($hubUrl === '' || $token === '') {
            return null;
        }

        // Refuse to send a bearer token over plain http — it would travel readable.
        if (!str_starts_with($hubUrl, 'https://') && !str_starts_with($hubUrl, 'http://localhost')) {
            $this->logger->error('TypoVigil agent: hubUrl must use https', ['hubUrl' => $hubUrl]);

            return null;
        }

        return [$hubUrl, $token];
    }
}
