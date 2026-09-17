<?php

$EM_CONF['typovigil_agent'] = [
    'title' => 'TypoVigil Agent',
    'description' => 'Reports the installed TYPO3 version and extensions to a TypoVigil hub, so update and security status can be tracked centrally.',
    'category' => 'be',
    'author' => 'Maik Demuth',
    'author_email' => 'hi@maidem.de',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-0.0.0',
            'typo3' => '13.4.0-14.99.99',
            'scheduler' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
