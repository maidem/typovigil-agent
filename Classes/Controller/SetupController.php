<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Maidemde\TypovigilAgent\Service\ReportSender;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\HtmlResponse;

/**
 * One-click setup: the hub links here with the token it just generated, so
 * nobody has to copy it into this extension's configuration by hand.
 *
 * Reachable only through the backend route, which already requires a
 * logged-in backend user; admin is required in addition because this writes
 * to LocalConfiguration.php.
 */
final readonly class SetupController
{
    public function __construct(private ExtensionConfiguration $extensionConfiguration) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if ($backendUser === null || !$backendUser->isAdmin()) {
            return new HtmlResponse('Admin access required.', 403);
        }

        $hub = trim((string)($request->getQueryParams()['hub'] ?? ''));
        $token = trim((string)($request->getQueryParams()['token'] ?? ''));

        if ($hub === '' || $token === '' || !ReportSender::isSecureHubUrl($hub)) {
            return new HtmlResponse('Missing or invalid hub/token parameters. The hub URL must use https.', 400);
        }

        $this->extensionConfiguration->set('typovigil_agent', [
            'hubUrl' => $hub,
            'token' => $token,
        ]);

        return new HtmlResponse(
            '<p>TypoVigil agent configured. Hub: ' . htmlspecialchars($hub) . '</p>'
            . '<p>You can close this window.</p>'
        );
    }
}
