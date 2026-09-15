<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\UserController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Company;
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
use RuntimeException;

/**
 * Marker thrown by the testable subclass's redirect*() overrides.
 *
 * Extends \Error, not \Exception: several actions call redirect*() from inside
 * a try block that catches \Exception, so an Exception-based marker gets
 * intercepted and reformatted into a different redirect, hiding the one under
 * test. An \Error travels like a real never-returning redirect.
 */
final class UserHalt extends \Error
{
}

final class UserControllerTestable extends UserController
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

        throw new UserHalt('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'success';

        throw new UserHalt('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new UserHalt('halt:error');
    }

    protected function logException(\Throwable $e, string $context): void
    {
        if ($e instanceof UserHalt) {
            throw $e;
        }

        parent::logException($e, $context);
    }
}

/**
 * Behavioural tests for UserController, which administers accounts and had 161
 * statements that no test executed. Nothing would have noticed if delete()
 * stopped refusing to delete the signed-in account, or if view() started
 * rendering soft-deleted users.
 *
 * Note on CSRF: unlike RoleController, UserController performs no per-action
 * token check. That is not a gap — CsrfMiddleware::handleToken() validates
 * every POST before routing, so the protection is global rather than absent.
 *
 * NOT COVERED, and not coverable in-process: create()'s success path calls
 * Email::sendActivationEmail() statically, which builds an Email and attempts
 * real delivery. There is no seam to intercept it without changing production
 * code, so the POST-success branch (~15 statements) is left to the validation
 * and error branches around it. Everything else is exercised.
 */
#[CoversClass(UserController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
#[UsesClass(Validator::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
final class UserControllerTest extends TestCase
{
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var Company&\PHPUnit\Framework\MockObject\MockObject */
    private $companyModel;
    /** @var Role&\PHPUnit\Framework\MockObject\MockObject */
    private $roleModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel = $this->createMock(User::class);
        $this->companyModel = $this->createMock(Company::class);
        $this->roleModel = $this->createMock(Role::class);

        $db = $this->createMock(Database::class);

        // Validator asks two different questions through the same method.
        // `unique` counts rows and wants zero; `exists` counts rows and wants
        // at least one. They are told apart by the `as cnt` alias that only
        // validateExists() writes.
        $db->method('executeQuery')->willReturnCallback(function (string $sql) {
            $statement = $this->createMock(\PDOStatement::class);
            $statement->method('fetchColumn')->willReturn(str_contains($sql, 'as cnt') ? 1 : 0);

            return $statement;
        });

        $settings = $this->createMock(SettingsService::class);
        $settings->method('getResultsPerPage')->willReturn(10);

        $this->seedSingleton(SettingsService::class, $settings);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(SecurityService::class, $this->createMock(SecurityService::class));
        $this->seedSingleton(Database::class, $db);
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

    private function controller(): UserControllerTestable
    {
        return new UserControllerTestable($this->userModel, $this->companyModel, $this->roleModel);
    }

    private function halt(UserControllerTestable $c, string $action, string $method, array $data): UserControllerTestable
    {
        try {
            $c->{$action}($method, $data);
            $this->fail("{$action}() was expected to redirect.");
        } catch (UserHalt) {
            // expected
        }

        return $c;
    }

    private function user(int $id, bool $deleted = false): \stdClass
    {
        $user = new \stdClass();
        $user->id = $id;
        $user->first_name = 'Test';
        $user->last_name = "User {$id}";
        $user->email = "user{$id}@example.test";
        $user->is_deleted = $deleted;

        return $user;
    }

    private function rolesAndPermissions(): array
    {
        return ['roles' => [(object) ['name' => 'admin']], 'permissions' => ['view_users']];
    }

    // ---- index() ---------------------------------------------------------

    public function testIndexRendersTheUserList(): void
    {
        $this->userModel->method('getAll')->willReturn(['records' => [$this->user(1)], 'total' => 1]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Users/index', $c->renderedView);
        $this->assertSame(1, $c->renderedData['totalUsers']);
    }

    /**
     * The list must exclude soft-deleted accounts; the filter is the only thing
     * keeping deleted users off the administration screen.
     */
    public function testIndexAsksOnlyForUsersThatAreNotDeleted(): void
    {
        $this->userModel->expects($this->once())
            ->method('getAll')
            ->with(['is_deleted' => 0], 1, 10)
            ->willReturn(['records' => [], 'total' => 0]);

        $this->controller()->index('GET', []);
    }

    public function testIndexRedirectsToTheDashboardWhenTheLookupFails(): void
    {
        $this->userModel->method('getAll')->willThrowException(new RuntimeException('db down'));

        $c = $this->halt($this->controller(), 'index', 'GET', []);

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
    }

    // ---- view() ----------------------------------------------------------

    public function testViewRendersTheUserWithRolesAndPermissions(): void
    {
        $this->userModel->method('findWithDetails')->willReturn($this->user(2));
        $this->userModel->method('getRolesAndPermissions')->willReturn($this->rolesAndPermissions());

        $c = $this->controller();
        $c->view('GET', ['id' => '2']);

        $this->assertSame('Users/view', $c->renderedView);
        $this->assertSame(['view_users'], $c->renderedData['user']->permissions);
    }

    public function testViewRejectsAnIdThatIsNotAnInteger(): void
    {
        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => 'abc']);

        $this->assertSame('/users', $c->redirectUrl);
        $this->assertSame('Invalid user ID', $c->redirectMessage);
    }

    public function testViewTreatsASoftDeletedUserAsMissing(): void
    {
        $this->userModel->method('findWithDetails')->willReturn($this->user(2, true));

        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => '2']);

        $this->assertSame('User not found', $c->redirectMessage);
    }

    // ---- profile() -------------------------------------------------------

    public function testProfileSendsAnonymousVisitorsToLogin(): void
    {
        $c = $this->halt($this->controller(), 'profile', 'GET', []);

        $this->assertSame('/login', $c->redirectUrl);
    }

    public function testProfileRendersTheSignedInUser(): void
    {
        $_SESSION['user']['profile']['id'] = 5;
        $this->userModel->method('findWithDetails')->willReturn($this->user(5));
        $this->userModel->method('getRolesAndPermissions')->willReturn($this->rolesAndPermissions());

        $c = $this->controller();
        $c->profile('GET', []);

        $this->assertSame('Users/profile', $c->renderedView);
        $this->assertSame(5, $c->renderedData['user']->id);
    }

    /**
     * Reads the id from the session, never from the request — a profile that
     * honoured a supplied id would let any user read any other user's record.
     */
    public function testProfileIgnoresAnIdSuppliedInTheRequest(): void
    {
        $_SESSION['user']['profile']['id'] = 5;
        $this->userModel->expects($this->once())->method('findWithDetails')->with(5)
            ->willReturn($this->user(5));
        $this->userModel->method('getRolesAndPermissions')->willReturn($this->rolesAndPermissions());

        $this->controller()->profile('GET', ['id' => '99']);
    }

    public function testProfileRedirectsToTheDashboardWhenTheRecordIsGone(): void
    {
        $_SESSION['user']['profile']['id'] = 5;
        $this->userModel->method('findWithDetails')->willReturn(null);

        $c = $this->halt($this->controller(), 'profile', 'GET', []);

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('Profile not found.', $c->redirectMessage);
    }

    // ---- createForm() / create() -----------------------------------------

    public function testCreateFormRendersRolesAndCompanies(): void
    {
        $this->companyModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->roleModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Users/create', $c->renderedView);
        $this->assertArrayHasKey('roles', $c->renderedData);
        $this->assertArrayHasKey('companies', $c->renderedData);
    }

    public function testCreateOnAGetRequestShowsTheFormInstead(): void
    {
        $this->companyModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->roleModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);

        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('Users/create', $c->renderedView);
    }

    public function testCreateKeepsTheSubmissionForRedisplayWhenValidationFails(): void
    {
        $this->userModel->expects($this->never())->method('create');

        $c = $this->halt($this->controller(), 'create', 'POST', [
            'first_name' => '',
            'last_name' => 'User',
            'email' => 'not-an-email',
            'role_id' => '1',
        ]);

        $this->assertSame('/users/create', $c->redirectUrl);
        $this->assertSame('not-an-email', $_SESSION['form_data']['email']);
    }

    // ---- editForm() / update() -------------------------------------------

    public function testEditFormRendersTheUserWithRolesAndCompanies(): void
    {
        $this->userModel->method('findWithDetails')->willReturn($this->user(7));
        $this->companyModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->roleModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '7']);

        $this->assertSame('Users/edit', $c->renderedView);
        $this->assertSame(7, $c->renderedData['user']->id);
    }

    public function testEditFormRejectsAMissingUser(): void
    {
        $this->userModel->method('findWithDetails')->willReturn(null);

        $c = $this->halt($this->controller(), 'editForm', 'GET', ['id' => '7']);

        $this->assertSame('/users', $c->redirectUrl);
        $this->assertSame('User not found', $c->redirectMessage);
    }

    public function testUpdateOnAGetRequestShowsTheEditFormInstead(): void
    {
        $this->userModel->method('findWithDetails')->willReturn($this->user(7));
        $this->companyModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->roleModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);

        $c = $this->controller();
        $c->update('GET', ['id' => '7']);

        $this->assertSame('Users/edit', $c->renderedView);
    }

    public function testUpdateSavesTheSubmittedFields(): void
    {
        $this->userModel->expects($this->once())
            ->method('update')
            ->with(7, $this->callback(static function (array $data): bool {
                return $data['first_name'] === 'Ada'
                    && $data['last_name'] === 'Lovelace'
                    && $data['role_id'] === 3;
            }));

        $c = $this->halt($this->controller(), 'update', 'POST', [
            'id' => '7',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'role_id' => '3',
        ]);

        $this->assertSame('/users', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * An empty company must persist as NULL rather than 0: the column is a
     * foreign key, and 0 matches no company.
     */
    public function testUpdateStoresAnAbsentCompanyAsNull(): void
    {
        $this->userModel->expects($this->once())
            ->method('update')
            ->with(7, $this->callback(static fn (array $d): bool => $d['company_id'] === null));

        $this->halt($this->controller(), 'update', 'POST', [
            'id' => '7',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'role_id' => '3',
            'company_id' => '',
        ]);
    }

    public function testUpdateRejectsAnIdThatIsNotAnInteger(): void
    {
        $this->userModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'update', 'POST', ['id' => 'abc']);

        $this->assertSame('Invalid user ID', $c->redirectMessage);
    }

    // ---- delete() --------------------------------------------------------

    public function testDeleteRefusesANonPostRequest(): void
    {
        $c = $this->halt($this->controller(), 'delete', 'GET', ['id' => '8']);

        $this->assertSame('/users', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testDeleteSoftDeletesTheUser(): void
    {
        $_SESSION['user']['id'] = 1;
        $this->userModel->method('find')->willReturn($this->user(8));
        $this->userModel->expects($this->once())->method('update')->with(8, ['is_deleted' => true]);

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '8']);

        $this->assertSame('success', $c->redirectType);
    }

    /**
     * The guard that matters: an administrator deleting their own account locks
     * themselves out, and there is no undo in the UI.
     */
    public function testDeleteRefusesToDeleteTheSignedInAccount(): void
    {
        $_SESSION['user']['id'] = 8;
        $this->userModel->method('find')->willReturn($this->user(8));
        $this->userModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '8']);

        $this->assertSame('Cannot delete your own account', $c->redirectMessage);
    }

    public function testDeleteTreatsAnAlreadyDeletedUserAsMissing(): void
    {
        $this->userModel->method('find')->willReturn($this->user(8, true));
        $this->userModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '8']);

        $this->assertSame('User not found', $c->redirectMessage);
    }

    public function testDeleteRejectsAnIdThatIsNotAnInteger(): void
    {
        $this->userModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => 'abc']);

        $this->assertSame('Invalid user ID', $c->redirectMessage);
    }
}
