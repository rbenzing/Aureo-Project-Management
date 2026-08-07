<?php

declare(strict_types=1);

namespace App\Utils;

use Tests\Unit\Utils\Support\PasswordHasherBuiltinToggles as Toggles;

/**
 * Namespace-scoped override of password_algos() for App\Utils.
 *
 * PHP resolves an unqualified function call against the current namespace
 * before the global one, so declaring this here intercepts the call in
 * PasswordHasher::algorithm() and nothing else - verified: no other code in
 * App\Utils calls password_algos().
 *
 * Without it the bcrypt fallback is unreachable from a test, because the
 * branch is chosen by a property of the PHP build rather than by any input.
 * That is the branch that only ever runs on hosts we cannot test on, which
 * makes it the one most worth pinning: an error there is invisible until it
 * is in front of a user who cannot install.
 *
 * Never autoloaded. A test that needs it must require_once it explicitly.
 */
if (!\function_exists(__NAMESPACE__ . '\\password_algos')) {
    /** @return list<string> */
    function password_algos(): array
    {
        $algorithms = \password_algos();

        if (!Toggles::$hideArgon2id) {
            return $algorithms;
        }

        return array_values(array_filter(
            $algorithms,
            static fn (string $algorithm): bool => $algorithm !== PASSWORD_ARGON2ID
        ));
    }
}
