<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\EventListener;

use Maidemde\TypovigilAgent\Service\ReportSender;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\Event\AfterPackageActivationEvent;
use TYPO3\CMS\Core\Package\Event\AfterPackageDeactivationEvent;

/**
 * Reports immediately when the set of installed extensions changes, so the hub
 * does not show a stale picture for up to a day after an update.
 */
final readonly class PackageChangeListener
{
    public function __construct(private ReportSender $reportSender) {}

    #[AsEventListener('typovigil-agent/package-activated')]
    public function onActivation(AfterPackageActivationEvent $event): void
    {
        $this->reportSender->send(onlyOnChange: true);
    }

    #[AsEventListener('typovigil-agent/package-deactivated')]
    public function onDeactivation(AfterPackageDeactivationEvent $event): void
    {
        $this->reportSender->send(onlyOnChange: true);
    }
}
