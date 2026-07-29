<?php

declare(strict_types=1);

namespace App\Core;

use Tests\Unit\Core\Support\ConfigBuiltinToggles;

/**
 * Namespace-scoped overrides of file_exists()/class_exists(), consulted only
 * from within App\Core (see Tests\Unit\Core\Support\ConfigBuiltinToggles for
 * why this works and why it is safe). This file is never autoloaded — tests
 * that need it must require_once it explicitly before the toggle can have
 * any effect.
 *
 * Both App\Core\Router::dispatch() and App\Core\Database::setDefaultOptions()
 * also call class_exists() unqualified from this same namespace, so the
 * override only intercepts the exact class lookups Config/Database perform
 * (SettingsService, Pdo\Mysql) and transparently defers to the real builtin
 * for every other argument.
 */
if (!function_exists(__NAMESPACE__ . '\\file_exists')) {
    function file_exists(string $filename): bool
    {
        if (ConfigBuiltinToggles::$forceEnvFileMissing) {
            return false;
        }

        return \file_exists($filename);
    }
}

if (!function_exists(__NAMESPACE__ . '\\class_exists')) {
    function class_exists(string $class, bool $autoload = true): bool
    {
        $normalized = ltrim($class, '\\');

        if (ConfigBuiltinToggles::$forceSettingsServiceMissing && $normalized === 'App\\Services\\SettingsService') {
            return false;
        }

        if (ConfigBuiltinToggles::$forcePdoMysqlClassMissing && $normalized === 'Pdo\\Mysql') {
            return false;
        }

        return \class_exists($class, $autoload);
    }
}
