<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Support;

/**
 * Switch consulted by the App\Utils override in
 * PasswordHasherBuiltinOverrides.php. Separate file because that one lives in
 * the App\Utils namespace and must declare nothing but functions.
 */
final class PasswordHasherBuiltinToggles
{
    /**
     * Makes password_algos() report a runtime without Argon2id.
     *
     * The only way to reach PasswordHasher's fallback on a machine that has
     * libargon2 - which is every machine this suite normally runs on, and
     * precisely why the fallback would otherwise ship unexecuted.
     */
    public static bool $hideArgon2id = false;

    public static function reset(): void
    {
        self::$hideArgon2id = false;
    }
}
