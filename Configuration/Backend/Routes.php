<?php

declare(strict_types=1);

use Maidemde\TypovigilAgent\Controller\SetupController;

return [
    'typovigil-agent_setup' => [
        'path' => '/typovigil-agent/setup',
        // Public: the link comes from outside the backend session (the hub's
        // flash message), so it carries no backend request token. The
        // controller enforces its own login + admin check instead.
        'access' => 'public',
        'target' => SetupController::class . '::handleRequest',
    ],
];
