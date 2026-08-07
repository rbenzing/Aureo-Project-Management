<?php

// file: Core/InstallGate.php
declare(strict_types=1);

namespace App\Core;

/**
 * Decides whether a request should be handled by the installer, passed to the
 * application, or refused.
 *
 * Pure by design. The installer runs before authentication, before the DI
 * container, and before any configuration exists - there is no layer beneath
 * it to catch a mistake here, so the decision is a table that can be exhausted
 * in a unit test rather than logic spread through public/index.php.
 */
final class InstallGate
{
    public const DECISION_RUN = 'run';
    public const DECISION_PASS_THROUGH = 'pass-through';
    public const DECISION_REFUSE = 'refuse';

    /** First URL segment that reaches the installer. */
    public const SEGMENT = 'install';

    /** Written relative to the application root once an installation completes. */
    public const LOCK_FILE = 'config/installed.lock';

    /**
     * Whether the caller must count rows in `users` before calling decide().
     *
     * Only one branch consults that count, and answering it costs a database
     * connection on a code path that runs for every single request.
     */
    public static function needsUserCheck(bool $lockExists, bool $configurationResolved, string $firstSegment): bool
    {
        return !$lockExists && $configurationResolved && $firstSegment === self::SEGMENT;
    }

    /**
     * @param bool     $lockExists           config/installed.lock is present
     * @param bool     $configurationResolved ConfigLoader found a complete configuration
     * @param string   $firstSegment         first segment of the request path
     * @param int|null $existingUserCount    rows in `users`; null when unknown
     *                                       (table absent, or database unreachable)
     */
    public static function decide(
        bool $lockExists,
        bool $configurationResolved,
        string $firstSegment,
        ?int $existingUserCount
    ): string {
        // A completed installation is never reinstallable over HTTP. This is
        // checked first so that no later branch can override it.
        if ($lockExists) {
            return self::DECISION_PASS_THROUGH;
        }

        // Nothing to boot and nothing to take over: the installer is the only
        // thing that can respond, whatever was requested.
        if (!$configurationResolved) {
            return self::DECISION_RUN;
        }

        if ($firstSegment !== self::SEGMENT) {
            return self::DECISION_PASS_THROUGH;
        }

        // Configured, unlocked, and asking for /install. Every installation
        // predating the lock file looks exactly like this, so the database is
        // the only thing that distinguishes "half-finished" from "live site".
        //
        // Unknown counts REFUSE. InstallerService::countUsers() returns null
        // for any failure at all - server unreachable, credentials rotated,
        // max_connections exhausted, `users` absent - and a resolvable
        // configuration is itself evidence that somebody already installed
        // this. Treating null as "go ahead" meant a live site handed out its
        // installer during any database blip, letting a caller repoint the
        // credentials and create an administrator. Brief database outages are
        // ordinary, and an attacker can help one along by exhausting
        // connections, so this must fail closed.
        //
        // The cost is that a genuinely half-finished install - configuration
        // written, migrations never run - is refused too. That case is
        // recoverable by deleting the configuration file, which
        // docs/DEPLOYMENT.md documents, and it is far rarer than a live site
        // with a momentarily unreachable database.
        if ($existingUserCount === null || $existingUserCount > 0) {
            return self::DECISION_REFUSE;
        }

        return self::DECISION_RUN;
    }
}
