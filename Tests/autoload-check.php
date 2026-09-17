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
    \Maidemde\TypovigilAgent\EventListener\PackageChangeListener::class,
    \Maidemde\TypovigilAgent\Controller\SetupController::class,
    \Maidemde\TypovigilAgent\Controller\ModuleController::class,
];

$failed = false;
foreach ($classes as $class) {
    $ok = class_exists($class);
    printf("%-64s %s\n", $class, $ok ? 'OK' : 'MISSING');
    $failed = $failed || !$ok;
}

// The extension configuration template must declare both settings the sender
// reads, otherwise the fields never show up in the backend.
$template = file_get_contents(dirname(__DIR__) . '/ext_conf_template.txt');
foreach (['hubUrl', 'token'] as $setting) {
    $ok = str_contains($template, $setting . ' =');
    printf("%-64s %s\n", "ext_conf_template declares '{$setting}'", $ok ? 'OK' : 'MISSING');
    $failed = $failed || !$ok;
}

// Every label key the module template asks for must exist in both languages,
// otherwise the backend silently renders the bare key.
$template = file_get_contents(dirname(__DIR__) . '/Resources/Private/Templates/Module/Index.html');
preg_match_all('/key="([^"]+)"/', $template, $matches);
foreach (['locallang.xlf', 'de.locallang.xlf'] as $file) {
    $labels = file_get_contents(dirname(__DIR__) . '/Resources/Private/Language/' . $file);
    foreach (array_unique($matches[1]) as $key) {
        $ok = str_contains($labels, 'id="' . $key . '"');
        printf("%-64s %s\n", "{$file}: {$key}", $ok ? 'OK' : 'MISSING');
        $failed = $failed || !$ok;
    }
}

exit($failed ? 1 : 0);
