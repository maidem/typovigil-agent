<?php

declare(strict_types=1);

/**
 * Checks ReportSender's https-enforcement rule: a bearer token must never
 * travel over plain http, except to localhost for local development.
 *
 * Run: php vendor/maidemde/typovigil-agent/Tests/report-sender-security-check.php
 */

$autoload = null;
foreach ([dirname(__DIR__, 3), dirname(__DIR__, 4)] as $root) {
    if (is_file($root . '/vendor/autoload.php')) {
        $autoload = $root . '/vendor/autoload.php';
        break;
    }
}

if ($autoload === null) {
    fwrite(STDERR, "Could not locate vendor/autoload.php\n");
    exit(1);
}

require_once $autoload;

$cases = [
    'https://hub.example.com' => true,
    'https://hub.example.com/' => true,
    'http://localhost' => true,
    'http://localhost:8080' => true,
    'http://hub.example.com' => false,
    'http://localhost.attacker.com' => false,
    '' => false,
];

$failed = false;
foreach ($cases as $hubUrl => $expected) {
    $actual = \Maidemde\TypovigilAgent\Service\ReportSender::isSecureHubUrl($hubUrl);
    $ok = $actual === $expected;
    printf("%-40s expected=%-5s actual=%-5s %s\n", $hubUrl, var_export($expected, true), var_export($actual, true), $ok ? 'OK' : 'FAIL');
    $failed = $failed || !$ok;
}

exit($failed ? 1 : 0);
