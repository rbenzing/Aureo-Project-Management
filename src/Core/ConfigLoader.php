<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Resolves application configuration from the first available source.
 *
 * A plain-text .env is unreadable-by-design only while it sits above the
 * document root. Installations that place the application AT the document root
 * cannot rely on that, and nginx has no per-directory configuration to deny it
 * with - so those installs need a source that is safe to serve. A PHP file
 * returning an array is: served directly, it executes and emits nothing.
 *
 * .env remains the last rung and the developer default; nothing about an
 * existing setup changes.
 */
final class ConfigLoader
{
    /** Keys that must be present for the application to boot. */
    public const REQUIRED = [
        'APP_DEBUG',
        'DB_HOST',
        'DB_NAME',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /**
     * @return string Description of the source used, for diagnostics.
     * @throws RuntimeException when no source provides a complete configuration.
     */
    public static function load(string $appRoot): string
    {
        if (self::environmentIsComplete()) {
            return 'environment';
        }

        foreach (self::candidatePaths($appRoot) as $path) {
            if (!is_file($path)) {
                continue;
            }

            if (str_ends_with($path, '.env')) {
                self::loadDotEnv($path);
            } else {
                self::loadPhpFile($path);
            }

            self::assertComplete($path);

            return $path;
        }

        throw new RuntimeException(
            'No configuration found. Tried, in order: real environment variables, then '
            . implode(', ', self::candidatePaths($appRoot))
        );
    }

    /**
     * Resolution order. Earlier entries win.
     *
     * @return list<string>
     */
    public static function candidatePaths(string $appRoot): array
    {
        $paths = [];

        $override = $_ENV['AUREO_CONFIG'] ?? $_SERVER['AUREO_CONFIG'] ?? getenv('AUREO_CONFIG');
        if (is_string($override) && $override !== '') {
            $paths[] = $override;
        }

        // The installer writes secrets outside the web tree where it can, and
        // records the absolute path here rather than making the loader guess.
        $pointer = $appRoot . '/config/config-path.php';
        if (is_file($pointer)) {
            $target = require $pointer;
            if (is_string($target) && $target !== '') {
                $paths[] = $target;
            }
        }

        $paths[] = $appRoot . '/config/config.php';
        $paths[] = $appRoot . '/.env';

        return $paths;
    }

    private static function environmentIsComplete(): bool
    {
        foreach (self::REQUIRED as $key) {
            if (!isset($_ENV[$key]) && !isset($_SERVER[$key]) && getenv($key) === false) {
                return false;
            }
        }

        return true;
    }

    private static function loadDotEnv(string $path): void
    {
        Dotenv::createImmutable(dirname($path), basename($path))->load();
    }

    private static function loadPhpFile(string $path): void
    {
        $values = require $path;

        if (!is_array($values)) {
            throw new RuntimeException("Configuration file {$path} must return an array.");
        }

        foreach ($values as $key => $value) {
            $key = (string) $key;

            // Match Dotenv's immutable semantics: a real environment variable
            // already set by the host always wins over the file.
            if (isset($_ENV[$key])) {
                continue;
            }

            $_ENV[$key] = (string) $value;
            $_SERVER[$key] = (string) $value;
        }
    }

    private static function assertComplete(string $path): void
    {
        $missing = [];

        foreach (self::REQUIRED as $key) {
            if (!isset($_ENV[$key]) && !isset($_SERVER[$key]) && getenv($key) === false) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                "Configuration loaded from {$path} is missing required keys: " . implode(', ', $missing)
            );
        }
    }
}
