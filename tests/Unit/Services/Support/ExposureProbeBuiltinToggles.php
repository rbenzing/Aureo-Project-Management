<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Support;

/**
 * Switches consulted by the App\Services function overrides in
 * ExposureProbeBuiltinOverrides.php. Separate from the overrides because that
 * file lives in the App\Services namespace and must contain nothing but
 * function declarations.
 */
final class ExposureProbeBuiltinToggles
{
    /** Makes function_exists('curl_init') report false, forcing the stream transport. */
    public static bool $forceCurlMissing = false;

    /** When true, curl_init() returns false instead of a handle. */
    public static bool $curlInitFails = false;

    /** Stand-in for curl_exec()'s return value. */
    public static bool|string $curlExecResult = '';

    /** Stand-in for curl_getinfo(..., CURLINFO_RESPONSE_CODE). */
    public static int $curlStatus = 0;

    /** Stand-in for get_headers(); false models a failed request. */
    public static array|false $streamHeaders = false;

    public static function reset(): void
    {
        self::$forceCurlMissing = false;
        self::$curlInitFails = false;
        self::$curlExecResult = '';
        self::$curlStatus = 0;
        self::$streamHeaders = false;
    }
}
