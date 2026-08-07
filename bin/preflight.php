<?php

declare(strict_types=1);

/**
 * Pre-installation environment check for operators with shell access.
 *
 * Runs the same checks as the first two steps of the web installer:
 *
 *   php bin/preflight.php
 *   php bin/preflight.php --url=https://example.com
 *
 * Without --url only the environment checks run; the exposure self-test needs
 * an origin to fetch, and guessing one from the command line would be wrong
 * more often than right.
 *
 * Exit codes: 0 all clear (warnings allowed), 1 at least one failure,
 * 2 could not run.
 */

use App\Services\ExposureProbe;
use App\Services\PreflightService;

$root = \dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php is missing. Run \"composer install\" or use the release archive.\n");
    exit(2);
}

require_once $root . '/vendor/autoload.php';

$url = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--url=')) {
        $url = substr($arg, 6);

        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php bin/preflight.php [--url=https://example.com]\n");
        exit(0);
    }
    fwrite(STDERR, "Unknown argument: {$arg}\n");
    exit(2);
}

$symbols = [
    PreflightService::SEVERITY_PASS => '  ok  ',
    PreflightService::SEVERITY_WARN => ' warn ',
    PreflightService::SEVERITY_FAIL => ' FAIL ',
];

fwrite(STDOUT, "=== Aureo preflight ===\n\n");

$service = new PreflightService(
    PHP_VERSION,
    null,
    null,
    null,
    (string) session_save_path()
);

$checks = $service->run($root, null);
$failed = false;

foreach ($checks as $check) {
    fwrite(STDOUT, sprintf("[%s] %s\n", $symbols[$check['severity']], $check['label']));
    fwrite(STDOUT, '         ' . $check['detail'] . "\n");

    if ($check['severity'] !== PreflightService::SEVERITY_PASS && $check['remedy'] !== '') {
        fwrite(STDOUT, '         -> ' . $check['remedy'] . "\n");
    }

    $failed = $failed || $check['severity'] === PreflightService::SEVERITY_FAIL;
}

if ($url !== null) {
    fwrite(STDOUT, "\n=== Exposure self-test against {$url} ===\n\n");

    $result = (new ExposureProbe())->run($url);

    foreach ($result['safe'] as $path) {
        fwrite(STDOUT, sprintf("[%s] %s is not served\n", $symbols[PreflightService::SEVERITY_PASS], $path));
    }
    foreach ($result['unreachable'] as $path) {
        fwrite(STDOUT, sprintf("[%s] %s could not be checked\n", $symbols[PreflightService::SEVERITY_WARN], $path));
    }
    foreach ($result['exposed'] as $path) {
        fwrite(STDOUT, sprintf("[%s] %s IS PUBLICLY READABLE\n", $symbols[PreflightService::SEVERITY_FAIL], $path));
        $failed = true;
    }

    if (!$result['verified']) {
        fwrite(STDOUT, "\n  Some paths could not be checked, so this host is not verified.\n");
        fwrite(STDOUT, "  Confirm by hand that the paths above are denied before going live.\n");
    }
}

fwrite(STDOUT, "\n" . ($failed ? "Preflight FAILED.\n" : "Preflight passed.\n"));

exit($failed ? 1 : 0);
