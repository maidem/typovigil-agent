<?php

declare(strict_types=1);

use Maidemde\TypovigilAgent\Controller\ModuleController;

return [
    'site_typovigil_agent' => [
        'parent' => 'site',
        'position' => ['after' => 'site_configuration'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/site/typovigil-agent',
        'labels' => 'LLL:EXT:typovigil_agent/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => ModuleController::class . '::handleRequest',
            ],
            'save' => [
                'target' => ModuleController::class . '::save',
                'methods' => ['POST'],
            ],
        ],
    ],
];
