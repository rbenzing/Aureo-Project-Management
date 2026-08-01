<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural tests for AuthMiddleware.
 *
 * AuthMiddleware's constructor does `new User()` and
 * `SettingsService::getInstance()` directly (no DI), so Database's
 * singleton is always replaced with a mock via reflection *before*
 * construction (harmless: BaseModel::__construct only assigns the mock,
 * never queries it). The middleware's own private $userModel and
 * $settingsService properties are then swapped for test doubles via
 * reflection so no real query ever executes.
 *
 * IMPORTANT — every failure/denial path in this class (unauthenticated,
 * session timeout, unauthorized, inactive account, and the catch-all in
 * isAuthenticated()) funnels through redirect(), which calls header()+exit.
 * That terminates the PHPUnit process, so none of those branches can be
 * exercised through the public API in-process. Because every one of
 * isAuthenticated()'s `return false;` statements is preceded by a call that
 * unconditionally exits, those branches are also *unreachable in
 * production* without exiting first — this is documented per-test below and
 * summarized in the final report rather than chased with process isolation.
 * Private helpers that do not themselves call redirect()
 * (checkPermission, isSessionExpired, loadUserPermissions,
 * validateUserSession's success path, updateSessionActivity) are exercised
 * directly via ReflectionMethod to reach branches the exiting wrappers
 * would otherwise hide.
 */
#[CoversClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(User::class)]
#[UsesClass(BaseModel::class)]
final class AuthMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->seedDatabaseSingleton(null);

        parent::tearDown();
    }

    private function seedDatabaseSingleton(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    /**
     * Builds a real AuthMiddleware with its userModel/settingsService
     * private properties swapped for mocks, never touching a real database.
     */
    private function makeMiddleware(?User $userMock = null, ?SettingsService $settingsMock = null): AuthMiddleware
    {
        $this->seedDatabaseSingleton($this->createMock(Database::class));

        $middleware = new AuthMiddleware();

        $ref = new ReflectionClass(AuthMiddleware::class);
        $ref->getProperty('userModel')->setValue($middleware, $userMock ?? $this->createMock(User::class));
        $ref->getProperty('settingsService')->setValue(
            $middleware,
            $settingsMock ?? $this->createMock(SettingsService::class)
        );

        return $middleware;
    }

    private function activeUser(): object
    {
        return (object) ['id' => 5, 'is_active' => true];
    }

    // ---- isAuthenticated() success paths -------------------------------

    public function testIsAuthenticatedReturnsTrueAndLoadsPermissionsWhenNotYetSet(): void
    {
        $_SESSION['user'] = ['profile' => ['id' => 5]];
        // last_activity intentionally absent to exercise the initialization branch.

        $userMock = $this->createMock(User::class);
        $userMock->method('find')->with(5)->willReturn($this->activeUser());
        $userMock->expects($this->once())
            ->method('getRolesAndPermissions')
            ->with(5)
            ->willReturn(['permissions' => ['task.view', 'task.edit']]);

        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);

        $middleware = $this->makeMiddleware($userMock, $settingsMock);

        $this->assertTrue($middleware->isAuthenticated());
        $this->assertIsInt($_SESSION['last_activity']);
        $this->assertSame(['task.view', 'task.edit'], $_SESSION['user']['permissions']);
    }

    public function testIsAuthenticatedSkipsPermissionReloadWhenAlreadyPresent(): void
    {
        $_SESSION['user'] = ['profile' => ['id' => 5], 'permissions' => ['existing.permission']];
        $_SESSION['last_activity'] = time() - 5;

        $userMock = $this->createMock(User::class);
        $userMock->method('find')->willReturn($this->activeUser());
        $userMock->expects($this->never())->method('getRolesAndPermissions');

        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);

        $middleware = $this->makeMiddleware($userMock, $settingsMock);

        $this->assertTrue($middleware->isAuthenticated());
        $this->assertSame(['existing.permission'], $_SESSION['user']['permissions']);
    }

    public function testIsAuthenticatedRefreshesLastActivityTimestamp(): void
    {
        $_SESSION['user'] = ['profile' => ['id' => 5], 'permissions' => []];
        $staleTime = time() - 100;
        $_SESSION['last_activity'] = $staleTime;

        $userMock = $this->createMock(User::class);
        $userMock->method('find')->willReturn($this->activeUser());

        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);

        $middleware = $this->makeMiddleware($userMock, $settingsMock);

        $this->assertTrue($middleware->isAuthenticated());
        $this->assertGreaterThan($staleTime, $_SESSION['last_activity']);
    }

    // ---- hasPermission() / hasAnyPermission() / hasAllPermissions() ---
    // success paths only: their denial branches call handleUnauthorized(),
    // which exits (see class docblock).

    public function testHasPermissionReturnsTrueWhenPermissionIsPresent(): void
    {
        $_SESSION['user'] = ['profile' => ['id' => 5], 'permissions' => ['task.view', 'task.edit']];
        $_SESSION['last_activity'] = time();

        $userMock = $this->createMock(User::class);
        $userMock->method('find')->willReturn($this->activeUser());
        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);

        $middleware = $this->makeMiddleware($userMock, $settingsMock);

        $this->assertTrue($middleware->hasPermission('task.view'));
    }

    public function testHasAnyPermissionReturnsTrueWhenALaterPermissionMatches(): void
    {
        $_SESSION['user'] = ['profile' => ['id' => 5], 'permissions' => ['task.delete']];
        $_SESSION['last_activity'] = time();

        $userMock = $this->createMock(User::class);
        $userMock->method('find')->willReturn($this->activeUser());
        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);

        $middleware = $this->makeMiddleware($userMock, $settingsMock);

        // 'task.view' does not match; loop must continue to 'task.delete'.
        $this->assertTrue($middleware->hasAnyPermission(['task.view', 'task.delete']));
    }

    public function testHasAllPermissionsReturnsTrueWhenEveryPermissionIsPresent(): void
    {
        $_SESSION['user'] = ['profile' => ['id' => 5], 'permissions' => ['a', 'b', 'c']];
        $_SESSION['last_activity'] = time();

        $userMock = $this->createMock(User::class);
        $userMock->method('find')->willReturn($this->activeUser());
        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);

        $middleware = $this->makeMiddleware($userMock, $settingsMock);

        $this->assertTrue($middleware->hasAllPermissions(['a', 'b']));
    }

    // ---- private helpers invoked directly to reach branches the ---------
    // ---- exiting wrappers would otherwise make unreachable -------------

    public function testCheckPermissionTrueAndFalseBranches(): void
    {
        $middleware = $this->makeMiddleware();

        $_SESSION['user']['permissions'] = ['task.view'];
        $this->assertTrue($this->invokePrivate($middleware, 'checkPermission', ['task.view']));
        $this->assertFalse($this->invokePrivate($middleware, 'checkPermission', ['task.delete']));
    }

    public function testCheckPermissionFalseWhenPermissionsMissingFromSession(): void
    {
        $middleware = $this->makeMiddleware();

        unset($_SESSION['user']);
        $this->assertFalse($this->invokePrivate($middleware, 'checkPermission', ['task.view']));
    }

    public function testIsSessionExpiredTrueWhenLastActivityNotSet(): void
    {
        $middleware = $this->makeMiddleware();

        unset($_SESSION['last_activity']);
        $this->assertTrue($this->invokePrivate($middleware, 'isSessionExpired'));
    }

    public function testIsSessionExpiredTrueWhenTimeoutElapsed(): void
    {
        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(60);
        $middleware = $this->makeMiddleware(null, $settingsMock);

        $_SESSION['last_activity'] = time() - 3600;
        $this->assertTrue($this->invokePrivate($middleware, 'isSessionExpired'));
    }

    public function testIsSessionExpiredFalseWhenWithinTimeout(): void
    {
        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock->method('getSessionTimeout')->willReturn(3600);
        $middleware = $this->makeMiddleware(null, $settingsMock);

        $_SESSION['last_activity'] = time() - 10;
        $this->assertFalse($this->invokePrivate($middleware, 'isSessionExpired'));
    }

    public function testLoadUserPermissionsFetchesWhenMissing(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())
            ->method('getRolesAndPermissions')
            ->with(9)
            ->willReturn(['permissions' => ['x.y']]);

        $middleware = $this->makeMiddleware($userMock);

        $_SESSION['user'] = ['profile' => ['id' => 9]];
        $this->invokePrivate($middleware, 'loadUserPermissions');

        $this->assertSame(['x.y'], $_SESSION['user']['permissions']);
    }

    public function testLoadUserPermissionsSkipsFetchWhenAlreadyPresent(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->never())->method('getRolesAndPermissions');

        $middleware = $this->makeMiddleware($userMock);

        $_SESSION['user'] = ['profile' => ['id' => 9], 'permissions' => ['already.set']];
        $this->invokePrivate($middleware, 'loadUserPermissions');

        $this->assertSame(['already.set'], $_SESSION['user']['permissions']);
    }

    public function testValidateUserSessionReturnsTrueForActiveUser(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->method('find')->with(5)->willReturn($this->activeUser());

        $middleware = $this->makeMiddleware($userMock);

        $_SESSION['user'] = ['profile' => ['id' => 5]];
        $this->assertTrue($this->invokePrivate($middleware, 'validateUserSession'));
    }

    public function testUpdateSessionActivitySetsCurrentTimestamp(): void
    {
        $middleware = $this->makeMiddleware();

        $before = time();
        $this->invokePrivate($middleware, 'updateSessionActivity');

        $this->assertGreaterThanOrEqual($before, $_SESSION['last_activity']);
    }
}
