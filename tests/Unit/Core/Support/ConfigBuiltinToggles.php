<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Support;

/**
 * Toggle flags consulted by the namespace-scoped file_exists()/class_exists()
 * overrides declared in ConfigBuiltinOverrides.php.
 *
 * PHP resolves an unqualified function call by first looking for a function
 * of the same name in the *current* namespace before falling back to the
 * global one. Config::loadEnvironment() delegates to ConfigLoader::load(),
 * which calls is_file() unqualified from within App\Core; Config::
 * initializeSettings() and Database::setDefaultOptions() call
 * file_exists()/class_exists() the same way. Once App\Core\file_exists()/
 * is_file()/class_exists() are declared (see the sibling file, required
 * explicitly by ConfigTest/DatabaseTest), those calls are routed through
 * here instead of touching the real filesystem or class table. This lets the
 * suite exercise Config's defensive "no configuration source available" /
 * "SettingsService unavailable" branches and Database's "no Pdo\\Mysql
 * class" fallback branch without editing src/ or resorting to process
 * isolation.
 *
 * All toggles default to "pass through to the real builtin" so production
 * behaviour (and every other test that never touches these flags) is
 * unaffected.
 */
final class ConfigBuiltinToggles
{
    public static bool $forceEnvFileMissing = false;

    public static bool $forceSettingsServiceMissing = false;

    public static bool $forcePdoMysqlClassMissing = false;

    public static function reset(): void
    {
        self::$forceEnvFileMissing = false;
        self::$forceSettingsServiceMissing = false;
        self::$forcePdoMysqlClassMissing = false;
    }
}
