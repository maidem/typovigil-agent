<?php

declare(strict_types=1);

use Maidemde\TypovigilAgent\Task\SendReportTask;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Native task type, TYPO3 14 and newer. On 13.4 ext_localconf.php registers the task instead.
if ((new Typo3Version())->getMajorVersion() >= 14) {
    ExtensionManagementUtility::addRecordType(
        [
            'label' => 'TypoVigil: send report',
            'description' => 'Reports the installed TYPO3 version and extensions to the configured hub.',
            'value' => SendReportTask::class,
            'icon' => 'mimetypes-x-tx_scheduler_task_group',
            'group' => 'typovigil_agent',
        ],
        '
            --div--;core.form.tabs:general,
                tasktype,
                task_group,
                description,
            --div--;core.form.tabs:timing,
                --palette--;;execution,
            --div--;core.form.tabs:access,
                disable,
            --div--;core.form.tabs:extended,',
        [],
        '',
        'tx_scheduler_task'
    );
}
