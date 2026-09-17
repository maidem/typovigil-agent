<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Controller;

use Maidemde\TypovigilAgent\Service\ReportSender;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend module: enter hub URL and token here instead of hunting for them in
 * the extension configuration, and see whether reporting actually works.
 */
final readonly class ModuleController
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private ModuleTemplateFactory $moduleTemplateFactory,
        private ReportSender $reportSender,
        private Registry $registry,
        private UriBuilder $uriBuilder,
        private FlashMessageService $flashMessageService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        if (($GLOBALS['BE_USER'] ?? null)?->isAdmin() !== true) {
            return $this->redirectWithMessage('module.notAdmin', ContextualFeedbackSeverity::ERROR);
        }

        if (($request->getQueryParams()['action'] ?? '') === 'sendNow') {
            return $this->sendNow();
        }

        $config = $this->configuration();
        $view = $this->moduleTemplateFactory->create($request);
        $view->assignMultiple([
            'hubUrl' => $config['hubUrl'],
            'token' => $config['token'],
            'isConfigured' => $config['hubUrl'] !== '' && $config['token'] !== '',
            'lastReportHash' => $this->registry->get('typovigil_agent', 'lastReportHash'),
        ]);

        return $view->renderResponse('Module/Index');
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        if (($GLOBALS['BE_USER'] ?? null)?->isAdmin() !== true) {
            return $this->redirectWithMessage('module.notAdmin', ContextualFeedbackSeverity::ERROR);
        }

        $body = $request->getParsedBody();
        $hubUrl = rtrim(trim((string)($body['hubUrl'] ?? '')), '/');
        // Not "token": that name is taken by the backend's own CSRF parameter,
        // and a field of that name would overwrite it in the POST body.
        $token = trim((string)($body['agentToken'] ?? ''));

        if ($hubUrl === '' || $token === '') {
            return $this->redirectWithMessage('module.message.incomplete', ContextualFeedbackSeverity::ERROR);
        }

        // Same rule the sender enforces: a bearer token must not travel readable.
        if (!str_starts_with($hubUrl, 'https://') && !str_starts_with($hubUrl, 'http://localhost')) {
            return $this->redirectWithMessage('module.message.insecureHub', ContextualFeedbackSeverity::ERROR);
        }

        $this->extensionConfiguration->set('typovigil_agent', [
            'hubUrl' => $hubUrl,
            'token' => $token,
        ]);

        return $this->redirectWithMessage('module.message.saved', ContextualFeedbackSeverity::OK);
    }

    private function sendNow(): ResponseInterface
    {
        $success = $this->reportSender->send();

        return $this->redirectWithMessage(
            $success ? 'module.message.sendSucceeded' : 'module.message.sendFailed',
            $success ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR,
        );
    }

    /**
     * @return array{hubUrl: string, token: string}
     */
    private function configuration(): array
    {
        try {
            $config = $this->extensionConfiguration->get('typovigil_agent');
        } catch (\Throwable) {
            $config = [];
        }

        return [
            'hubUrl' => trim((string)($config['hubUrl'] ?? '')),
            'token' => trim((string)($config['token'] ?? '')),
        ];
    }

    private function redirectWithMessage(string $key, ContextualFeedbackSeverity $severity): ResponseInterface
    {
        $message = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Messaging\FlashMessage::class,
            $this->translate($key),
            '',
            $severity,
            true,
        );
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($message);

        return new RedirectResponse((string)$this->uriBuilder->buildUriFromRoute('site_typovigil_agent'));
    }

    private function translate(string $key): string
    {
        return $GLOBALS['LANG']->sL(
            'LLL:EXT:typovigil_agent/Resources/Private/Language/locallang.xlf:' . $key
        );
    }
}
