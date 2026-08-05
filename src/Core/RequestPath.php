<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Resolves where the application is mounted and which path was requested.
 *
 * Aureo supports four layouts, and the mount point differs in each:
 * document root at public/, document root at the application root, a
 * subdirectory install, and hosts where no rewrite rule can be configured so
 * URLs run through /index.php/... . SCRIPT_NAME distinguishes them all.
 */
final class RequestPath
{
    private string $basePath;
    private string $path;
    private bool $usesPathInfo;

    public function __construct(string $requestUri, string $scriptName)
    {
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';

        $scriptName = str_replace('\\', '/', $scriptName);
        $scriptName = $scriptName === '' ? '' : '/' . ltrim($scriptName, '/');
        $scriptDir = self::normaliseDirectory(dirname($scriptName));

        if ($scriptName !== '' && self::hasPrefix($requestPath, $scriptName)) {
            $this->usesPathInfo = true;
            $this->basePath = $scriptDir;
            $this->path = self::stripPrefix($requestPath, $scriptName);

            return;
        }

        if ($scriptDir !== '' && self::hasPrefix($requestPath, $scriptDir)) {
            $this->usesPathInfo = false;
            $this->basePath = $scriptDir;
            $this->path = self::stripPrefix($requestPath, $scriptDir);

            return;
        }

        $this->usesPathInfo = false;
        $this->basePath = '';
        $this->path = ltrim($requestPath, '/');
    }

    public static function fromGlobals(): self
    {
        return new self(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            (string) ($_SERVER['SCRIPT_NAME'] ?? '')
        );
    }

    /** URL prefix the application is mounted at: '' or '/aureo'. No trailing slash. */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /** Route path with no leading slash: 'projects/view/5'. */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<string> Router segments. The site root yields [''] so the
     *                      empty route registered for the dashboard matches.
     */
    public function segments(): array
    {
        return explode('/', $this->path);
    }

    /** True when routing through /index.php/... because no rewrite is available. */
    public function usesPathInfo(): bool
    {
        return $this->usesPathInfo;
    }

    /**
     * Segment-boundary-aware prefix test. Plain str_starts_with would treat
     * '/a' as a prefix of '/abc/projects' and mis-route the request.
     */
    private static function hasPrefix(string $path, string $prefix): bool
    {
        $prefix = rtrim($prefix, '/');

        if ($prefix === '') {
            return false;
        }

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function stripPrefix(string $path, string $prefix): string
    {
        return ltrim(substr($path, strlen(rtrim($prefix, '/'))), '/');
    }

    private static function normaliseDirectory(string $directory): string
    {
        $directory = str_replace('\\', '/', $directory);

        return ($directory === '/' || $directory === '.') ? '' : rtrim($directory, '/');
    }
}
