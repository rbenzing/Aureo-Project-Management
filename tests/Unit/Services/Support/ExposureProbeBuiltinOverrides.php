<?php

declare(strict_types=1);

namespace App\Services;

use Tests\Unit\Services\Support\ExposureProbeBuiltinToggles as Toggles;

/**
 * Namespace-scoped overrides of the builtins ExposureProbe's transports call.
 * PHP resolves an unqualified function call against the current namespace
 * before the global one, so declaring these in App\Services intercepts the
 * calls in ExposureProbe::defaultFetcher() and nothing else - verified: no
 * other code in App\Services calls curl_*, get_headers, stream_context_create
 * or function_exists.
 *
 * Never autoloaded. A test that needs it must require_once it explicitly.
 *
 * Every guard below uses \function_exists (fully qualified) deliberately.
 * This file shadows function_exists itself, so an unqualified guard would
 * call the shadow while defining it.
 */
// ext-curl is optional, and plenty of PHP builds omit it - the machine this
// was developed on is one. Without these the curl branch cannot execute at
// all: it would die on "Undefined constant CURLOPT_NOBODY" before reaching
// any shadow. Production never hits that, because it only enters the branch
// when function_exists('curl_init') is genuinely true and the extension has
// therefore already defined them - this is a test artefact, not a bug in
// ExposureProbe.
//
// The values are the real ones, though nothing reads them: curl_setopt_array
// is shadowed below and discards its options.
foreach ([
    'CURLOPT_NOBODY' => 44,
    'CURLOPT_RETURNTRANSFER' => 19913,
    'CURLOPT_FOLLOWLOCATION' => 52,
    'CURLOPT_TIMEOUT' => 13,
    'CURLOPT_CONNECTTIMEOUT' => 78,
    'CURLOPT_SSL_VERIFYPEER' => 64,
    'CURLOPT_SSL_VERIFYHOST' => 81,
    'CURLINFO_RESPONSE_CODE' => 2097154,
] as $constant => $value) {
    if (!\defined($constant)) {
        \define($constant, $value);
    }
}

/**
 * Reports curl as available unless a test opts out.
 *
 * Deliberately NOT delegating to the real \function_exists('curl_init'): that
 * would make which transport the suite exercises depend on whether the host
 * happens to have ext-curl, so on a build without it all four curl tests
 * would silently run the stream branch and assert nothing about curl at all.
 * Three of them would still pass, coincidentally, which is the worst possible
 * outcome. Every other function is answered honestly.
 */
if (!\function_exists(__NAMESPACE__ . '\\function_exists')) {
    function function_exists(string $function): bool
    {
        if ($function === 'curl_init') {
            return !Toggles::$forceCurlMissing;
        }

        return \function_exists($function);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_init')) {
    /** @return object|false */
    function curl_init(string $url = '')
    {
        if (Toggles::$curlInitFails) {
            return false;
        }

        // Any object will do: every curl_* function that touches it is
        // shadowed below, so the real extension never sees this value.
        return new \stdClass();
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_setopt_array')) {
    function curl_setopt_array(object $handle, array $options): bool
    {
        return true;
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_exec')) {
    /** @return bool|string */
    function curl_exec(object $handle)
    {
        return Toggles::$curlExecResult;
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_getinfo')) {
    /** @return mixed */
    function curl_getinfo(object $handle, ?int $option = null)
    {
        return Toggles::$curlStatus;
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_close')) {
    function curl_close(object $handle): void
    {
    }
}

if (!\function_exists(__NAMESPACE__ . '\\stream_context_create')) {
    /** @return mixed */
    function stream_context_create(array $options = [], array $params = [])
    {
        return \stream_context_create($options, $params);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\get_headers')) {
    /** @return array|false */
    function get_headers(string $url, bool $associative = false, $context = null)
    {
        return Toggles::$streamHeaders;
    }
}
