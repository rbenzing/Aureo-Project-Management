<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the route registry: every route must name a controller class that
 * exists and an action method it actually has.
 *
 * Router::dispatch() resolves 'controller' => 'Task' to \App\Controllers\
 * TaskController and then checks method_exists(). Both failures are runtime
 * only — a mistyped controller or an action that has since been renamed or
 * deleted shows up as a 404 for whoever clicks the link, and no test notices,
 * because public/index.php is a flat registry that nothing else reads.
 *
 * This matters more than it sounds: the timer routes were repointed from
 * TaskController to TimeTrackingController when TaskController's broken
 * implementation was removed, and nothing in the suite would have caught a typo
 * in either the controller or the action name.
 *
 * Same precedent as AssetUrlTest and SqlPlaceholderGuardTest: a repo-wide guard
 * with no CoversClass.
 */
final class RouteTargetsTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string}> controller/action pairs
     */
    private function routeTargets(): array
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        // Registrations are written as one array literal per route, with
        // 'controller' always preceding 'action'.
        preg_match_all(
            "/'controller'\s*=>\s*'([A-Za-z]+)'\s*,\s*'action'\s*=>\s*'([A-Za-z]+)'/",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(
            static fn (array $m): array => [$m[1], $m[2]],
            $matches
        );
    }

    public function testEveryRouteNamesAControllerAndActionThatExist(): void
    {
        $broken = [];

        foreach ($this->routeTargets() as [$controller, $action]) {
            $class = '\\App\\Controllers\\' . ucfirst($controller) . 'Controller';

            if (!class_exists($class)) {
                $broken[] = "{$class} does not exist (route controller '{$controller}')";

                continue;
            }

            if (!method_exists($class, $action)) {
                $broken[] = "{$class}::{$action}() does not exist";
            }
        }

        $this->assertSame(
            [],
            $broken,
            "Every route must resolve to a real action, or it is a 404 waiting to happen:\n  "
            . implode("\n  ", $broken)
        );
    }

    /**
     * Guards the guard. A parser that quietly stopped matching would leave the
     * test above asserting that an empty list is empty, for ever.
     */
    public function testTheParserFindsTheRouteRegistry(): void
    {
        $this->assertGreaterThan(
            50,
            count($this->routeTargets()),
            'The route parser has stopped matching public/index.php.'
        );
    }

    /**
     * Demonstrates the check rejects what it claims to, so the suite has been
     * seen to fail on a bad target rather than only to pass on good ones.
     */
    public function testTheCheckRejectsAnActionThatDoesNotExist(): void
    {
        $this->assertTrue(method_exists('\\App\\Controllers\\TimeTrackingController', 'startTimer'));
        $this->assertFalse(method_exists('\\App\\Controllers\\TimeTrackingController', 'noSuchAction'));
    }
}
