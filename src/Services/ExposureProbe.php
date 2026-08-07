<?php

// file: Services/ExposureProbe.php
declare(strict_types=1);

namespace App\Services;

/**
 * Asks the server whether it will hand out files that must never be served.
 *
 * The drop-in layout puts the whole application inside the document root and
 * relies on .htaccess or web.config to hide everything that is not a route.
 * Whether those files are honoured is a property of the host, not of this
 * repository - nginx ignores .htaccess entirely - so the only trustworthy
 * answer comes from asking over HTTP.
 *
 * A host that blocks loopback HTTP cannot answer. That case reports
 * verified=false and must be surfaced to the operator, never rounded up to a
 * pass.
 */
final class ExposureProbe
{
    /**
     * Probed in this order. Each is a real disclosure: credentials, the app
     * log (which carries queries and session identifiers), or the git config,
     * whose presence proves the whole history is downloadable.
     */
    public const PATHS = [
        '/.env',
        '/config/config.php',
        '/config/config-path.php',
        '/log/aureo.log',
        '/.git/config',
        '/composer.json',
    ];

    private const TIMEOUT_SECONDS = 5;

    /** @var callable(string):?int */
    private $fetch;

    public function __construct(?callable $fetch = null)
    {
        $this->fetch = $fetch ?? self::defaultFetcher();
    }

    /**
     * @return array{verified:bool, exposed:list<string>, safe:list<string>, unreachable:list<string>}
     */
    public function run(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        $exposed = [];
        $safe = [];
        $unreachable = [];

        foreach (self::PATHS as $path) {
            $status = ($this->fetch)($baseUrl . $path);

            if ($status === null || $status >= 500) {
                // Either the request never completed, or it reached PHP and
                // blew up. Neither demonstrates that the file is denied.
                $unreachable[] = $path;

                continue;
            }

            if ($status === 200) {
                $exposed[] = $path;

                continue;
            }

            $safe[] = $path;
        }

        return [
            'verified' => $unreachable === [],
            'exposed' => $exposed,
            'safe' => $safe,
            'unreachable' => $unreachable,
        ];
    }

    /**
     * Origin for the probe, derived from the request the operator is already
     * making. Never accept this from a form field: the probe runs on an
     * unauthenticated route, so a caller-supplied URL would make it a
     * server-side request forgery primitive.
     *
     * @param array<string,mixed> $server
     */
    public static function baseUrlFromGlobals(array $server, string $basePath): ?string
    {
        $host = $server['HTTP_HOST'] ?? null;

        if (!is_string($host) || $host === '') {
            return null;
        }

        // A bare host, optionally with a numeric port. Anything else - an
        // embedded '@', a path, a CR/LF, a non-numeric port - is refused
        // rather than sanitised.
        if (preg_match('/^[A-Za-z0-9._-]+(:\d{1,5})?$/', $host) !== 1) {
            return null;
        }

        $https = $server['HTTPS'] ?? '';
        $scheme = (is_string($https) && $https !== '' && strtolower($https) !== 'off') ? 'https' : 'http';

        return $scheme . '://' . $host . rtrim($basePath, '/');
    }

    /**
     * Parses the status code out of an HTTP status line.
     *
     * Extracted from the stream fetcher so the parsing - the only part with
     * a decision in it - can be tested without any I/O at all.
     */
    public static function parseStatusLine(string $statusLine): ?int
    {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * cURL when it is available, streams otherwise. Both follow no redirects
     * (a redirect away from the file is itself the answer) and fetch headers
     * only, so a large log file is never pulled into memory.
     *
     * NOTE: `function_exists` here is deliberately UNQUALIFIED, as are every
     * curl_* and get_headers call below. Namespace-scoped function shadowing
     * is how this project reaches builtin-dependent branches in a test (see
     * tests/Unit/Core/Support/), and a leading backslash would put both
     * transports permanently out of reach of the suite. Verified: no other
     * code in App\Services calls any of these, so the shadow's blast radius
     * is this class alone.
     *
     * @return callable(string):?int
     */
    private static function defaultFetcher(): callable
    {
        if (function_exists('curl_init')) {
            return static function (string $url): ?int {
                $handle = curl_init($url);
                if ($handle === false) {
                    return null;
                }

                curl_setopt_array($handle, [
                    CURLOPT_NOBODY => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                    CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
                    // Self-signed certificates are common on staging hosts and
                    // are irrelevant here: the question is what the server
                    // hands out, not who it claims to be.
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);

                $ok = curl_exec($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                curl_close($handle);

                if ($ok === false || $status === 0) {
                    return null;
                }

                return $status;
            };
        }

        return static function (string $url): ?int {
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => self::TIMEOUT_SECONDS,
                    'follow_location' => 0,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $headers = get_headers($url, false, $context);

            if ($headers === false || $headers === []) {
                return null;
            }

            return self::parseStatusLine((string) $headers[0]);
        };
    }
}
