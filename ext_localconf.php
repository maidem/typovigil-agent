<?php

declare(strict_types=1);

defined('TYPO3') or die('Access denied.');

// TYPO3 14 registers the task as a native TCA record type (Configuration/TCA/Overrides);
// the SC_OPTIONS way is deprecated there (#98453) but still the only one on 13.4.
if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 14) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Maidemde\TypovigilAgent\Task\SendReportTask::class] = [
        'extension' => 'typovigil_agent',
        'title' => 'LLL:EXT:typovigil_agent/Resources/Private/Language/locallang.xlf:task.sendReport.title',
        'description' => 'LLL:EXT:typovigil_agent/Resources/Private/Language/locallang.xlf:task.sendReport.description',
    ];
}
