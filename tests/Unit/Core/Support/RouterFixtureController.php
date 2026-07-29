<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Minimal controller double used only by Tests\Unit\Core\RouterTest to
 * exercise App\Core\Router::dispatch() without depending on any real
 * controller (which would drag in Database/session/DI concerns not relevant
 * to routing). Declared under App\Controllers because Router hardcodes that
 * namespace prefix when resolving a `'controller' => 'RouterFixture'` route
 * parameter. This file has no PSR-4 mapping, so RouterTest requires it
 * explicitly before dispatching.
 */
class RouterFixtureController
{
    public static bool $called = false;

    public static ?string $calledMethod = null;

    public static array $calledData = [];

    public function index(string $method, array $data): void
    {
        self::$called = true;
        self::$calledMethod = $method;
        self::$calledData = $data;
    }

    public function show(string $method, array $data): void
    {
        self::$called = true;
        self::$calledMethod = $method;
        self::$calledData = $data;
    }

    public static function reset(): void
    {
        self::$called = false;
        self::$calledMethod = null;
        self::$calledData = [];
    }
}
