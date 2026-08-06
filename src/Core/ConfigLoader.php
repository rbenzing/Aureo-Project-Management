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
            self::hydrateEnvironmentFromRealEnvironment();

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
            if (self::resolveFromRealEnvironment($key) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * PHP's default variables_order (GPCS - no E) never populates $_ENV from
     * the real process environment: getenv($key) sees a value while
     * isset($_ENV[$key]) stays false. Every consumer in the app reads $_ENV
     * directly (see class docblock), so rung 1 completing was not enough -
     * this is what actually makes the values reach $_ENV. It reuses the same
     * three-way lookup environmentIsComplete() used to decide rung 1 was
     * viable, so whichever source made that true is guaranteed to be the one
     * that gets copied.
     *
     * Every variable the host provides is copied, not only REQUIRED - the
     * app reads plenty of optional keys straight from $_ENV (APP_SCHEME,
     * SMTP_*, PASSWORD_PEPPER, CSRF_TOKEN_EXPIRY, SESSION_SECURE, ...) and
     * the .env/config.php rungs already load whatever keys are present
     * rather than a fixed allowlist - rung 1 should behave the same way.
     * getenv() with no argument (not $_SERVER) is the bulk source for that:
     * it reflects the real process environment regardless of
     * variables_order (guaranteed since PHP 7.1), whereas $_SERVER on web
     * SAPIs is full of unrelated request/SAPI data (HTTP headers,
     * SCRIPT_NAME, ...) that has no business landing in $_ENV.
     */
    private static function hydrateEnvironmentFromRealEnvironment(): void
    {
        foreach (self::REQUIRED as $key) {
            self::copyIntoEnv($key, self::resolveFromRealEnvironment($key));
        }

        foreach ((array) getenv() as $key => $value) {
            self::copyIntoEnv((string) $key, is_string($value) ? $value : null);
        }
    }

    /**
     * Same three-way source priority environmentIsComplete() /
     * assertComplete() use to decide whether a key is available at all.
     */
    private static function resolveFromRealEnvironment(string $key): ?string
    {
        if (isset($_ENV[$key])) {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }

    private static function copyIntoEnv(string $key, ?string $value): void
    {
        // Match Dotenv's/loadPhpFile()'s immutable semantics: a value
        // already present in $_ENV always wins.
        if ($value === null || isset($_ENV[$key])) {
            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
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
            if (self::resolveFromRealEnvironment($key) === null) {
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
