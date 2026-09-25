<?php

declare(strict_types=1);

/**
 * Verifies every class of this extension is reachable through the autoloader,
 * and that the scheduler task registration points at a real class.
 *
 * Run: php vendor/maidemde/typovigil-agent/Tests/autoload-check.php
 */

// Works both when installed under vendor/ and when checked out as a path
// repository, which sit at different depths below the project root.
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

$classes = [
    \Maidemde\TypovigilAgent\Service\PackageCollector::class,
    \Maidemde\TypovigilAgent\Service\ReportSender::class,
    \Maidemde\TypovigilAgent\Task\SendReportTask::class,
    \Maidemde\TypovigilAgent\Command\SendReportCommand::class,
    \Maidemde\TypovigilAgent\EventListener\PackageChangeListener::class,
];

$failed = false;
foreach ($classes as $class) {
    $ok = class_exists($class);
    printf("%-64s %s\n", $class, $ok ? 'OK' : 'MISSING');
    $failed = $failed || !$ok;
}

exit($failed ? 1 : 0);
