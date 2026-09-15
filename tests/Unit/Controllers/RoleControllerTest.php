<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\RoleController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\BaseModel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Marker thrown by the testable subclass's redirect*() overrides. Named per
 * file rather than shared so this file runs in isolation, matching the
 * convention in CompanyControllerTest.
 *
 * It extends \Error rather than \Exception deliberately. create() and update()
 * call redirectWithSuccess() from inside a try block whose inner handler is
 * catch (\Exception) { rollBack(); throw; } — an Exception-based marker is
 * caught there and rolls back the transaction it just committed, so a passing
 * success path looks like a failed one. An Error passes straight through that
 * handler, which is what a real redirect (never-returning) does.
 */
final class RoleHalt extends \Error
{
}

/**
 * Captures render()/redirect*() instead of performing them, and no-ops
 * requirePermission(), which would otherwise send a response and exit. PHP
 * dispatches $this->requirePermission() virtually, so this override is already
 * in effect for calls made from the parent constructor.
 */
final class RoleControllerTestable extends RoleController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;
    public ?string $redirectType = null;

    protected function requirePermission(string $permission): void
    {
        // no-op in tests
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirect(string $url): never
    {
        $this->redirectUrl = $url;
        $this->redirectType = 'plain';

        throw new RoleHalt('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'success';

        throw new RoleHalt('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new RoleHalt('halt:error');
    }

    protected function logException(\Throwable $e, string $context): void
    {
        // The halt marker is thrown from inside the try blocks the controller
        // wraps with catch(\Throwable), so without this it would be caught and
        // reformatted into a different redirect, hiding the one under test.
        if ($e instanceof RoleHalt) {
            throw $e;
        }

        parent::logException($e, $context);
    }
}

/**
 * Behavioural tests for RoleController, which administers roles and the
 * permissions attached to them and had exactly zero coverage: 142 statements,
 * none executed by any test. Nothing here would have noticed if delete()
 * stopped checking whether a role was still assigned to users, or if update()
 * stopped syncing permissions at all.
 *
 * RoleController builds its own CsrfMiddleware, so the mock is injected over
 * the private property after construction rather than passed in. Validator is
 * real, not mocked: its `unique:roles,name` rule reaches the Database
 * singleton, which is seeded here so the rule resolves without a server.
 */
#[CoversClass(RoleController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(CsrfMiddleware::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
#[UsesClass(Validator::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
final class RoleControllerTest extends TestCase
{
    /** @var Role&\PHPUnit\Framework\MockObject\MockObject */
    private $roleModel;
    /** @var Permission&\PHPUnit\Framework\MockObject\MockObject */
    private $permissionModel;
    /** @var CsrfMiddleware&\PHPUnit\Framework\MockObject\MockObject */
    private $csrf;
    /** @var Database&\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleModel = $this->createMock(Role::class);
        $this->permissionModel = $this->createMock(Permission::class);
        $this->csrf = $this->createMock(CsrfMiddleware::class);
        $this->db = $this->createMock(Database::class);

        // Validator's unique rule counts matching rows; zero means "available".
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetchColumn')->willReturn(0);
        $this->db->method('executeQuery')->willReturn($statement);

        $settings = $this->createMock(SettingsService::class);
        $settings->method('getResultsPerPage')->willReturn(10);

        $this->seedSingleton(SettingsService::class, $settings);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(SecurityService::class, $this->createMock(SecurityService::class));
        $this->seedSingleton(Database::class, $this->db);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        foreach ([SettingsService::class, LoggerService::class, SecurityService::class, Database::class] as $class) {
            $this->seedSingleton($class, null);
        }

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    private function controller(bool $csrfValid = true): RoleControllerTestable
    {
        $controller = new RoleControllerTestable($this->roleModel, $this->permissionModel);

        $this->csrf->method('validateToken')->willReturn($csrfValid);
        (new ReflectionProperty(RoleController::class, 'csrfMiddleware'))->setValue($controller, $this->csrf);

        return $controller;
    }

    /** Runs an action that is expected to halt, and returns the controller. */
    private function halt(RoleControllerTestable $c, string $action, string $method, array $data): RoleControllerTestable
    {
        try {
            $c->{$action}($method, $data);
            $this->fail("{$action}() was expected to redirect.");
        } catch (RoleHalt) {
            // expected
        }

        return $c;
    }

    private function role(int $id, bool $deleted = false): \stdClass
    {
        $role = new \stdClass();
        $role->id = $id;
        $role->name = "Role {$id}";
        $role->is_deleted = $deleted;

        return $role;
    }

    // ---- index() ---------------------------------------------------------

    public function testIndexRendersTheRoleList(): void
    {
        $this->roleModel->method('getAllWithDetails')->willReturn([
            'records' => [$this->role(1)],
            'total' => 1,
        ]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Roles/index', $c->renderedView);
        $this->assertSame(1, $c->renderedData['totalRoles']);
        $this->assertCount(1, $c->renderedData['roles']);
    }

    public function testIndexRedirectsToTheDashboardWhenTheLookupFails(): void
    {
        $this->roleModel->method('getAllWithDetails')->willThrowException(new RuntimeException('db down'));

        $c = $this->halt($this->controller(), 'index', 'GET', []);

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
    }

    // ---- view() ----------------------------------------------------------

    public function testViewRendersTheRoleWithItsUsers(): void
    {
        $this->roleModel->method('findWithPermissions')->willReturn($this->role(3));
        $this->roleModel->method('getUsers')->willReturn([(object) ['id' => 9]]);

        $c = $this->controller();
        $c->view('GET', ['id' => '3']);

        $this->assertSame('Roles/view', $c->renderedView);
        $this->assertSame(3, $c->renderedData['role']->id);
        $this->assertCount(1, $c->renderedData['usersWithRole']);
    }

    public function testViewRejectsAnIdThatIsNotAnInteger(): void
    {
        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => 'not-a-number']);

        $this->assertSame('/roles', $c->redirectUrl);
        $this->assertSame('Invalid role ID', $c->redirectMessage);
    }

    /**
     * Soft-deleted rows still exist, so "found" is not the same question as
     * "visible" — a view that skipped this check would happily render one.
     */
    public function testViewTreatsASoftDeletedRoleAsMissing(): void
    {
        $this->roleModel->method('findWithPermissions')->willReturn($this->role(4, true));

        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => '4']);

        $this->assertSame('Role not found', $c->redirectMessage);
    }

    // ---- createForm() ----------------------------------------------------

    public function testCreateFormRendersTheOrganizedPermissions(): void
    {
        $this->permissionModel->method('getOrganizedPermissions')->willReturn(['Roles' => ['view_roles']]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Roles/create', $c->renderedView);
        $this->assertSame(['Roles' => ['view_roles']], $c->renderedData['permissions']);
    }

    // ---- create() --------------------------------------------------------

    public function testCreateOnAGetRequestShowsTheFormInstead(): void
    {
        $this->permissionModel->method('getOrganizedPermissions')->willReturn([]);

        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('Roles/create', $c->renderedView);
    }

    public function testCreatePersistsTheRoleAndItsPermissions(): void
    {
        $this->roleModel->expects($this->once())->method('beginTransaction');
        $this->roleModel->method('create')->willReturn(42);
        $this->roleModel->expects($this->once())->method('commit');
        $this->roleModel->expects($this->never())->method('rollBack');

        $this->permissionModel->expects($this->once())
            ->method('assignToRole')
            ->with(42, [1, 2]);

        $c = $this->halt($this->controller(), 'create', 'POST', [
            'csrf_token' => 'valid',
            'name' => 'Auditor',
            'permissions' => ['1', '2'],
        ]);

        $this->assertSame('/roles', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * The CSRF check is the first thing past the permission gate, so a request
     * carrying a bad token must not reach the transaction at all.
     */
    public function testCreateRejectsAnInvalidCsrfTokenBeforeTouchingTheDatabase(): void
    {
        $this->roleModel->expects($this->never())->method('beginTransaction');
        $this->roleModel->expects($this->never())->method('create');

        $c = $this->halt($this->controller(csrfValid: false), 'create', 'POST', [
            'csrf_token' => 'wrong',
            'name' => 'Auditor',
        ]);

        $this->assertSame('/roles/create', $c->redirectUrl);
        $this->assertSame('Invalid CSRF token', $c->redirectMessage);
    }

    public function testCreateRollsBackWhenPermissionAssignmentFails(): void
    {
        $this->roleModel->method('create')->willReturn(42);
        $this->roleModel->expects($this->once())->method('rollBack');
        $this->roleModel->expects($this->never())->method('commit');

        $this->permissionModel->method('assignToRole')
            ->willThrowException(new RuntimeException('permission insert failed'));

        $c = $this->halt($this->controller(), 'create', 'POST', [
            'csrf_token' => 'valid',
            'name' => 'Auditor',
            'permissions' => ['1'],
        ]);

        $this->assertSame('/roles/create', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
    }

    public function testCreateKeepsTheSubmissionForRedisplayWhenValidationFails(): void
    {
        $c = $this->halt($this->controller(), 'create', 'POST', [
            'csrf_token' => 'valid',
            'name' => '',
        ]);

        $this->assertSame('/roles/create', $c->redirectUrl);
        $this->assertArrayHasKey('form_data', $_SESSION);
        $this->assertSame('', $_SESSION['form_data']['name']);
    }

    // ---- editForm() ------------------------------------------------------

    public function testEditFormRendersTheRoleAndPermissions(): void
    {
        $this->roleModel->method('findWithPermissions')->willReturn($this->role(5));
        $this->permissionModel->method('getOrganizedPermissions')->willReturn(['Roles' => []]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '5']);

        $this->assertSame('Roles/edit', $c->renderedView);
        $this->assertSame(5, $c->renderedData['role']->id);
    }

    public function testEditFormRejectsAMissingRole(): void
    {
        $this->roleModel->method('findWithPermissions')->willReturn(null);

        $c = $this->halt($this->controller(), 'editForm', 'GET', ['id' => '5']);

        $this->assertSame('/roles', $c->redirectUrl);
        $this->assertSame('Role not found', $c->redirectMessage);
    }

    // ---- update() --------------------------------------------------------

    public function testUpdateOnAGetRequestShowsTheEditFormInstead(): void
    {
        $this->roleModel->method('findWithPermissions')->willReturn($this->role(6));
        $this->permissionModel->method('getOrganizedPermissions')->willReturn([]);

        $c = $this->controller();
        $c->update('GET', ['id' => '6']);

        $this->assertSame('Roles/edit', $c->renderedView);
    }

    public function testUpdateSavesTheRoleAndSyncsItsPermissions(): void
    {
        $this->roleModel->expects($this->once())->method('update')->with(6, $this->anything());
        $this->roleModel->expects($this->once())->method('syncPermissions')->with(6, [3, 4]);
        $this->roleModel->expects($this->once())->method('commit');

        $c = $this->halt($this->controller(), 'update', 'POST', [
            'csrf_token' => 'valid',
            'id' => '6',
            'name' => 'Editor',
            'permissions' => ['3', '4'],
        ]);

        $this->assertSame('/roles', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * Submitting no permissions has to mean "revoke them all". If this passed
     * anything other than an empty list, clearing a role's permissions through
     * the UI would silently do nothing.
     */
    public function testUpdateWithNoPermissionsRevokesThemAll(): void
    {
        $this->roleModel->expects($this->once())->method('syncPermissions')->with(6, []);

        $this->halt($this->controller(), 'update', 'POST', [
            'csrf_token' => 'valid',
            'id' => '6',
            'name' => 'Editor',
        ]);
    }

    public function testUpdateRollsBackWhenSyncingPermissionsFails(): void
    {
        $this->roleModel->method('syncPermissions')->willThrowException(new RuntimeException('sync failed'));
        $this->roleModel->expects($this->once())->method('rollBack');
        $this->roleModel->expects($this->never())->method('commit');

        $c = $this->halt($this->controller(), 'update', 'POST', [
            'csrf_token' => 'valid',
            'id' => '6',
            'name' => 'Editor',
        ]);

        $this->assertSame('/roles/edit/6', $c->redirectUrl);
    }

    // ---- delete() --------------------------------------------------------

    public function testDeleteRefusesANonPostRequest(): void
    {
        $c = $this->halt($this->controller(), 'delete', 'GET', ['id' => '7']);

        $this->assertSame('/roles', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testDeleteSoftDeletesTheRole(): void
    {
        $this->roleModel->method('find')->willReturn($this->role(7));
        $this->roleModel->method('getUsers')->willReturn([]);
        $this->roleModel->expects($this->once())->method('update')->with(7, ['is_deleted' => true]);

        $c = $this->halt($this->controller(), 'delete', 'POST', ['csrf_token' => 'valid', 'id' => '7']);

        $this->assertSame('/roles', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * The guard that matters: deleting a role still assigned to users would
     * strip their permissions without warning.
     */
    public function testDeleteRefusesARoleThatIsStillAssignedToUsers(): void
    {
        $this->roleModel->method('find')->willReturn($this->role(7));
        $this->roleModel->method('getUsers')->willReturn([(object) ['id' => 1]]);
        $this->roleModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['csrf_token' => 'valid', 'id' => '7']);

        $this->assertSame(
            'Cannot delete role as it is currently assigned to one or more users.',
            $c->redirectMessage
        );
    }

    public function testDeleteRejectsAnInvalidCsrfToken(): void
    {
        $this->roleModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(csrfValid: false), 'delete', 'POST', [
            'csrf_token' => 'wrong',
            'id' => '7',
        ]);

        $this->assertSame('Invalid CSRF token', $c->redirectMessage);
    }

    public function testDeleteTreatsAnAlreadyDeletedRoleAsMissing(): void
    {
        $this->roleModel->method('find')->willReturn($this->role(7, true));
        $this->roleModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['csrf_token' => 'valid', 'id' => '7']);

        $this->assertSame('Role not found', $c->redirectMessage);
    }
}
