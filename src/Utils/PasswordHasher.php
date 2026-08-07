<?php

// file: Utils/PasswordHasher.php
declare(strict_types=1);

namespace App\Utils;

/**
 * The single place this application decides how to hash a password.
 *
 * PASSWORD_ARGON2ID is a compile-time option. The constant is defined on
 * every PHP 8 build, so defined() tells you nothing - but password_hash()
 * throws a ValueError, not a warning, when the named algorithm is absent.
 * Hardcoding it therefore turns a missing libargon2 into a fatal error at
 * registration, at password reset, when an administrator creates a user, and
 * during the canonical migration's admin seed. That is precisely the shared
 * hosting the drop-in deployment layout exists to support.
 *
 * password_algos() is the only truthful source, so ask it.
 *
 * This is not a downgrade. Argon2id is still used wherever it exists, which
 * is every host that works today; the fallback replaces a crash and nothing
 * else. password_verify() reads the algorithm out of the hash, so stored
 * hashes keep verifying and a database may hold a mixture of both.
 *
 * One caveat worth knowing: bcrypt - PASSWORD_DEFAULT on PHP 8.2 - silently
 * truncates at 72 bytes. It only applies on hosts without libargon2, and
 * only to passwords longer than that.
 */
final class PasswordHasher
{
    /**
     * The strongest algorithm this runtime actually provides.
     *
     * @return string|int|null The algorithm identifier password_hash() expects.
     */
    public static function algorithm(): string|int|null
    {
        if (\in_array(PASSWORD_ARGON2ID, password_algos(), true)) {
            return PASSWORD_ARGON2ID;
        }

        return PASSWORD_DEFAULT;
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, self::algorithm());
    }
}
