<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\ProjectController;
use App\Core\Config;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\Template;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Sort;
use App\Utils\Validator;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Testable subclass: capture render()/redirect*() instead of performing their
 * real side effects. Real render() emits HTML and trips
 * beStrictAboutOutputDuringTests; real redirect*() is header()+exit and would
 * kill the runner. Each override throws so callers relying on the real `never`
 * return type stop at the same point.
 */
final class ProjectControllerTestable extends ProjectController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;
    public ?string $redirectType = null;
    /** @var string[] */
    public array $requiredPermissions = [];

    protected function requirePermission(string $permission): void
    {
        $this->requiredPermissions[] = $permission;
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    /**
     * Only the FIRST redirect is recorded, and that matters.
     *
     * The real helpers are `never` — they exit, so nothing downstream runs. Here
     * they have to throw instead, and that throw happens inside the controller's
     * own try block, where its `catch (\Throwable)` swallows it and issues a
     * second, error redirect. Recording only the first call preserves the
     * decision the controller actually made; the trailing error redirect is an
     * artefact of simulating `never` with an exception, not real behaviour.
     * For the same reason, assert on redirectType/redirectUrl rather than on the
     * escaping exception, which is the artefact one.
     */
    private function recordRedirect(string $url, ?string $message, string $type): void
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
            $this->redirectMessage = $message;
            $this->redirectType = $type;
        }
    }

    protected function redirect(string $url): never
    {
        $this->recordRedirect($url, null, 'plain');

        throw new RuntimeException('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->recordRedirect($url, $message, 'success');

        throw new RuntimeException('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->recordRedirect($url, $message, 'error');

        throw new RuntimeException('halt:error');
    }
}

/**
 * Behavioural tests for ProjectController.
 *
 * SettingsService/LoggerService/Database/SecurityService are process-wide
 * singletons reached directly (not injected) by BaseController, Validator and
 * SecurityService, so they are seeded with mocks via reflection per test and
 * reset in tearDown. Database::executeQuery() is stubbed where Validator's
 * `exists:` rules would otherwise reach a real connection.
 */
#[CoversClass(ProjectController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Company::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Project::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(Sort::class)]
#[UsesClass(Task::class)]
#[UsesClass(Template::class)]
#[UsesClass(User::class)]
#[UsesClass(Validator::class)]
final class ProjectControllerTest extends TestCase
{
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var Task&\PHPUnit\Framework\MockObject\MockObject */
    private $taskModel;
    /** @var Company&\PHPUnit\Framework\MockObject\MockObject */
    private $companyModel;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var Template&\PHPUnit\Framework\MockObject\MockObject */
    private $templateModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectModel = $this->createMock(Project::class);
        $this->taskModel = $this->createMock(Task::class);
        $this->companyModel = $this->createMock(Company::class);
        $this->userModel = $this->createMock(User::class);
        $this->templateModel = $this->createMock(Template::class);

        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getResultsPerPage')->willReturn(10);
        $this->seedSingleton(SettingsService::class, $settingsService);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(Database::class, null);
        $this->seedSingleton(SecurityService::class, null);

        $_SESSION = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        foreach ([SettingsService::class, LoggerService::class, Database::class, SecurityService::class] as $class) {
            $this->seedSingleton($class, null);
        }

        $_SESSION = [];
        $_GET = [];

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $instance): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $instance);
    }

    private function controller(): ProjectControllerTestable
    {
        return new ProjectControllerTestable(
            $this->projectModel,
            $this->taskModel,
            $this->companyModel,
            $this->userModel,
            $this->templateModel
        );
    }

    /** Database whose exists/unique lookups always succeed. */
    private function seedPassingDatabase(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(1);
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($stmt);
        $this->seedSingleton(Database::class, $db);
    }

    private function expectHalt(ProjectControllerTestable $c, callable $call): RuntimeException
    {
        try {
            $call();
            $this->fail('Expected a redirect halt to be thrown');
        } catch (RuntimeException $e) {
            return $e;
        }
    }

    private function project(int $id = 1, bool $deleted = false, array $extra = []): object
    {
        return (object) array_merge([
            'id' => $id,
            'name' => 'Apollo',
            'is_deleted' => $deleted,
            'company_id' => 4,
            'tasks' => [],
        ], $extra);
    }

    // ------------------------------------------------------------------ index()

    public function testIndexRendersProjectsWithStatsAndCompanies(): void
    {
        $this->projectModel->method('getAll')->willReturn([
            'total' => 2,
            'records' => [(object) ['id' => 1], (object) ['id' => 2]],
        ]);
        $this->projectModel->method('findWithDetails')->willReturnCallback(
            fn (int $id): object => $this->project($id)
        );
        $this->projectModel->method('count')->willReturn(3);
        $this->companyModel->method('getAllCompanies')->willReturn([(object) ['id' => 4]]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Projects/index', $c->renderedView);
        $this->assertSame(['view_projects'], $c->requiredPermissions);
        $this->assertCount(2, $c->renderedData['projects']);
        $this->assertSame(2, $c->renderedData['totalProjects']);
        $this->assertSame(1, $c->renderedData['page']);
        $this->assertSame(10, $c->renderedData['limit']);
        // ceil() returns a float.
        $this->assertSame(1.0, $c->renderedData['totalPages']);
        $this->assertSame(3, $c->renderedData['projectStats']['in_progress']);
    }

    public function testIndexAppliesSearchStatusAndCompanyFiltersFromQueryString(): void
    {
        $_GET = ['search' => 'apollo', 'status_id' => '2', 'company_id' => '4'];

        $captured = null;
        $this->projectModel->method('getAll')->willReturnCallback(
            function (array $filters) use (&$captured): array {
                $captured = $filters;

                return ['total' => 0, 'records' => []];
            }
        );
        $this->projectModel->method('count')->willReturn(0);
        $this->companyModel->method('getAllCompanies')->willReturn([]);

        $this->controller()->index('GET', []);

        $this->assertSame('apollo', $captured['search']);
        $this->assertSame(2, $captured['status_id']);
        $this->assertSame(4, $captured['company_id']);
        $this->assertSame(0, $captured['is_deleted']);
    }

    public function testIndexPaginatesToRequestedPage(): void
    {
        $captured = null;
        $this->projectModel->method('getAll')->willReturnCallback(
            function (array $filters, int $page) use (&$captured): array {
                $captured = $page;

                return ['total' => 35, 'records' => []];
            }
        );
        $this->projectModel->method('count')->willReturn(0);
        $this->companyModel->method('getAllCompanies')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', ['page' => '3']);

        $this->assertSame(3, $captured);
        $this->assertSame(3, $c->renderedData['page']);
        // 35 records at 10 per page.
        $this->assertSame(4.0, $c->renderedData['totalPages']);
    }

    public function testIndexRedirectsToDashboardWhenTheQueryFails(): void
    {
        $this->projectModel->method('getAll')->willThrowException(new RuntimeException('db down'));

        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->index('GET', []));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching projects.', $c->redirectMessage);
    }

    // ------------------------------------------------------------------- view()

    public function testViewRendersProjectWithTasksGroupedByStatus(): void
    {
        $tasksByStatus = ['open' => [(object) ['id' => 9]]];
        $this->projectModel->method('findWithDetails')->willReturn($this->project(5));
        $this->taskModel->method('getByProjectId')->willReturn($tasksByStatus);

        $c = $this->controller();
        $c->view('GET', ['id' => '5']);

        $this->assertSame('Projects/view', $c->renderedView);
        $this->assertSame(5, $c->renderedData['project']->id);
        $this->assertSame($tasksByStatus, $c->renderedData['tasksByStatus']);
    }

    public function testViewRejectsANonNumericId(): void
    {
        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->view('GET', ['id' => 'abc']));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('/projects', $c->redirectUrl);
        $this->assertSame('Invalid project ID', $c->redirectMessage);
    }

    public function testViewRejectsAMissingProject(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->view('GET', ['id' => '5']));

        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testViewRejectsASoftDeletedProject(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn($this->project(5, true));

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->view('GET', ['id' => '5']));

        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testViewReportsAGenericMessageForUnexpectedFailures(): void
    {
        $this->projectModel->method('findWithDetails')->willThrowException(new RuntimeException('db down'));

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->view('GET', ['id' => '5']));

        $this->assertSame('/projects', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching project details.', $c->redirectMessage);
    }

    // -------------------------------------------------------------- createForm()

    public function testCreateFormRendersLookupData(): void
    {
        $_SESSION['user']['profile']['company_id'] = 4;

        $this->userModel->method('getAllUsers')->willReturn([(object) ['id' => 7]]);
        $this->companyModel->method('getAllCompanies')->willReturn([(object) ['id' => 4]]);
        $this->projectModel->method('getAllStatuses')->willReturn([(object) ['id' => 1]]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([(object) ['id' => 2]]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Projects/create', $c->renderedView);
        $this->assertSame(['create_projects'], $c->requiredPermissions);
        $this->assertSame(4, $c->renderedData['companyId']);
        $this->assertCount(1, $c->renderedData['templates']);
        $this->assertCount(1, $c->renderedData['users']);
    }

    public function testCreateFormRedirectsWhenLookupDataCannotBeLoaded(): void
    {
        $this->userModel->method('getAllUsers')->willThrowException(new RuntimeException('db down'));

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->createForm('GET', []));

        $this->assertSame('/projects', $c->redirectUrl);
        $this->assertSame('An error occurred while loading the creation form.', $c->redirectMessage);
    }

    // ------------------------------------------------------------------ create()

    public function testCreateWithoutPostDelegatesToTheForm(): void
    {
        $this->userModel->method('getAllUsers')->willReturn([]);
        $this->companyModel->method('getAllCompanies')->willReturn([]);
        $this->projectModel->method('getAllStatuses')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([]);

        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('Projects/create', $c->renderedView);
    }

    public function testCreateStoresTheProjectAndRedirectsToIt(): void
    {
        $this->seedPassingDatabase();
        $this->projectModel->method('transformKeyCodeFormat')->willReturn('APO');

        $captured = null;
        $this->projectModel->method('create')->willReturnCallback(
            function (array $data) use (&$captured): int {
                $captured = $data;

                return 42;
            }
        );

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->create('POST', [
            'name' => 'Apollo Program',
            'description' => 'Lunar',
            'status_id' => '2',
            'owner_id' => '7',
            'company_id' => '4',
        ]));

        $this->assertSame('success', $c->redirectType);
        $this->assertSame('/projects/view/42', $c->redirectUrl);
        $this->assertSame('Project created successfully.', $c->redirectMessage);
        $this->assertSame('Apollo Program', $captured['name']);
        $this->assertSame(2, $captured['status_id']);
        $this->assertSame(7, $captured['owner_id']);
        $this->assertSame('APO', $captured['key_code']);
    }

    public function testCreateRejectsAnInvalidPayloadAndPreservesTheSubmission(): void
    {
        $this->seedPassingDatabase();

        $data = ['name' => 'no'];

        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->create('POST', $data));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('/projects/create', $c->redirectUrl);
        $this->assertNotSame('', (string) $c->redirectMessage);
        $this->assertSame($data, $_SESSION['form_data']);
    }

    public function testCreateFallsBackToTheTemplateDescriptionWhenNoneWasSupplied(): void
    {
        $this->seedPassingDatabase();
        $this->projectModel->method('transformKeyCodeFormat')->willReturn('APO');
        $this->templateModel->method('find')->willReturn(
            (object) ['id' => 3, 'description' => 'From template', 'is_deleted' => false]
        );

        $captured = null;
        $this->projectModel->method('create')->willReturnCallback(
            function (array $data) use (&$captured): int {
                $captured = $data;

                return 8;
            }
        );

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->create('POST', [
            'name' => 'Apollo Program',
            'status_id' => '2',
            'owner_id' => '7',
            'company_id' => '4',
            'template_id' => '3',
        ]));

        $this->assertSame('From template', $captured['description']);
    }

    public function testCreateIgnoresASoftDeletedTemplate(): void
    {
        $this->seedPassingDatabase();
        $this->projectModel->method('transformKeyCodeFormat')->willReturn('APO');
        $this->templateModel->method('find')->willReturn(
            (object) ['id' => 3, 'description' => 'From template', 'is_deleted' => true]
        );

        $captured = null;
        $this->projectModel->method('create')->willReturnCallback(
            function (array $data) use (&$captured): int {
                $captured = $data;

                return 8;
            }
        );

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->create('POST', [
            'name' => 'Apollo Program',
            'status_id' => '2',
            'owner_id' => '7',
            'company_id' => '4',
            'template_id' => '3',
        ]));

        $this->assertNull($captured['description']);
    }

    // ---------------------------------------------------------------- editForm()

    public function testEditFormRendersTheProjectWithLookupData(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn($this->project(5));
        $this->projectModel->method('getAllStatuses')->willReturn([(object) ['id' => 1]]);
        $this->companyModel->method('getAll')->willReturn(['records' => []]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([(object) ['id' => 2]]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '5']);

        $this->assertSame('Projects/edit', $c->renderedView);
        $this->assertSame(['edit_projects'], $c->requiredPermissions);
        $this->assertSame(5, $c->renderedData['project']->id);
        $this->assertSame(4, $c->renderedData['companyId']);
    }

    public function testEditFormRejectsANonNumericId(): void
    {
        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->editForm('GET', ['id' => 'abc']));

        $this->assertSame('/projects', $c->redirectUrl);
        $this->assertSame('Invalid project ID', $c->redirectMessage);
    }

    public function testEditFormRejectsAMissingProject(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->editForm('GET', ['id' => '5']));

        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testEditFormReportsAGenericMessageForUnexpectedFailures(): void
    {
        $this->projectModel->method('findWithDetails')->willThrowException(new RuntimeException('db down'));

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->editForm('GET', ['id' => '5']));

        $this->assertSame('An error occurred while loading the edit form.', $c->redirectMessage);
    }

    // ------------------------------------------------------------------ update()

    public function testUpdateRejectsANonNumericId(): void
    {
        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->update('POST', ['id' => 'abc']));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('Invalid project ID', $c->redirectMessage);
    }

    public function testUpdateRejectsAnInvalidPayloadAndPreservesTheSubmission(): void
    {
        $this->seedPassingDatabase();

        $data = ['id' => '5', 'name' => ''];

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', $data));

        $this->assertSame('/projects/edit/5', $c->redirectUrl);
        $this->assertSame($data, $_SESSION['form_data']);
    }
}
