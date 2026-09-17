<?php

declare(strict_types=1);

defined('TYPO3') or die('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Maidemde\TypovigilAgent\Task\SendReportTask::class] = [
    'extension' => 'typovigil_agent',
    'title' => 'TypoVigil: send report',
    'description' => 'Reports the installed TYPO3 version and extensions to the configured hub.',
];
