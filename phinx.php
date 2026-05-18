<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Parse DB_HOST to extract host and port
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = 3306;

if (str_contains($dbHost, ':')) {
    [$dbHost, $dbPort] = explode(':', $dbHost, 2);
    $dbPort = (int) $dbPort;
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => $_ENV['APP_ENV'] ?? 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => $_ENV['DB_NAME'] ?? '',
            'user' => $_ENV['DB_USERNAME'] ?? '',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'local' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => $_ENV['DB_NAME'] ?? '',
            'user' => $_ENV['DB_USERNAME'] ?? '',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'development' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => $_ENV['DB_NAME'] ?? '',
            'user' => $_ENV['DB_USERNAME'] ?? '',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => ($_ENV['DB_NAME'] ?? '') . '_test',
            'user' => $_ENV['DB_USERNAME'] ?? '',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];
