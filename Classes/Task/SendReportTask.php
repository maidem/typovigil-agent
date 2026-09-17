<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Task;

use Maidemde\TypovigilAgent\Service\ReportSender;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Sends the daily report. Runs unconditionally so the hub can tell a healthy
 * instance from one whose agent has stopped working.
 */
final class SendReportTask extends AbstractTask
{
    public function execute(): bool
    {
        // makeInstance cannot build this: the sender takes constructor arguments
        // and a scheduler task gets no injection of its own.
        return GeneralUtility::getContainer()->get(ReportSender::class)->send();
    }
}
