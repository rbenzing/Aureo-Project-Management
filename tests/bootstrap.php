<?php

declare(strict_types=1);

// Define BASE_PATH for tests
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/public');
}

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Set test environment variables (before Config::init())
$_ENV['APP_DEBUG'] = 'true';
$_ENV['APP_ENV'] = 'testing';

// Keep the suite out of the repo's own log/aureo.log. Most LoggerService
// instances are built inside production code (BaseController's constructor,
// the container, the singleton) where no test can pass a directory in, so the
// only lever that reaches all of them is the environment. var/tmp/ is already
// gitignored.
// putenv() as well as $_ENV: several tests rebuild $_ENV from a snapshot or
// unset keys wholesale, and a LoggerService constructed during that window
// falls back to the repo log — and then points PHP's error_log at it for the
// rest of the process. getenv() is unaffected by those tests.
$aureoTestLogDir = dirname(__DIR__) . '/var/tmp/test-logs';
$_ENV['AUREO_LOG_DIR'] = $aureoTestLogDir;
$_SERVER['AUREO_LOG_DIR'] = $aureoTestLogDir;
putenv('AUREO_LOG_DIR=' . $aureoTestLogDir);

// Initialize session for tests
if (session_status() === PHP_SESSION_NONE) {
    $_SESSION = [];
}
