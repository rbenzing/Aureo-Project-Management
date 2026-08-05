<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\SprintController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Template;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SettingsService;
use App\Utils\Validator;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Testable subclass: capture render()/redirect*() calls instead of their
 * real side effects (see the class docblock on SprintControllerTest for why
 * every override records only on the FIRST call before (re)throwing).
 */
final class SprintControllerTestable extends SprintController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;
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

    protected function redirect(string $url): never
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
        }

        throw new RuntimeException('redirect:' . $url);
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
            $this->redirectMessage = $message;
        }

        throw new RuntimeException('redirectSuccess:' . $message);
    }

    protected function redirectWithError(string $url, string $message): never
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
            $this->redirectMessage = $message;
        }

        throw new RuntimeException('redirectError:' . $message);
    }
}

/**
 * Behavioural tests for SprintController.
 *
 * SprintController has NO constructor injection (unlike TaskController /
 * DashboardController): its constructor unconditionally does `new Sprint()`,
 * `new Project()`, `new Task()`, `new Template()`. Those real-but-harmless
 * constructions are allowed to run (BaseModel's constructor only reaches for
 * Database::getInstance(), never opens a connection), and immediately
 * afterwards reflection replaces the four private model properties with
 * mocks — no setAccessible() call needed, PHP 8.1+ makes reflected
 * properties accessible by default. Two actions (planning(),
 * getMilestonesForPlanning()) additionally construct `new User()` /
 * `new Milestone()` as LOCAL variables, which reflection cannot reach; those
 * are driven indirectly through a mocked Database (User::getUserProjects()
 * goes through Database::getConnection()->prepare(), Milestone's query goes
 * through Database::executeQuery() — both are stubbed).
 *
 * Known uncoverable branches (raw exit, not chased per task instructions —
 * see final report for the precise line numbers): create(), update() and
 * addTasks() end EVERY reachable POST branch (success and both catch
 * blocks) in raw header()+exit rather than the overridable
 * redirect()/redirectWithSuccess()/redirectWithError() helpers every other
 * action in this class uses; delete() does the same for its entire
 * POST-with-a-valid-method body. Only their GET-delegation (create/update)
 * or initial method-not-POST guard (delete/addTasks) is exercised here.
 * editForm()'s `catch (InvalidArgumentException $e)` block is the same
 * pattern (raw exit) and is skipped for the same reason; its happy path and
 * generic \Throwable catch (which DOES use the overridable helper) are
 * covered. createFromMilestones()'s non-AJAX success branch is likewise raw
 * exit and skipped; its validation-failure and generic-exception branches
 * are safe because with no request body available under the CLI SAPI,
 * `$input` is always empty, which routes both catch blocks through
 * redirectWithError() instead.
 *
 * Also not covered: the "found and matched" branches of assignTask() and
 * the non-empty-milestone-ids branch of getTasksFromMilestones(). Both read
 * file_get_contents('php://input') for their payload, which is always empty
 * under the CLI SAPI PHPUnit runs under, so only the "input absent" paths
 * are reachable without additional stream-wrapper test infrastructure.
 */
#[CoversClass(SprintController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Database::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Validator::class)]
#[UsesClass(SprintStatus::class)]
#[UsesClass(TaskStatus::class)]
#[UsesClass(User::class)]
#[UsesClass(Milestone::class)]
#[UsesClass(Sprint::class)]
#[UsesClass(Project::class)]
#[UsesClass(Task::class)]
#[UsesClass(Template::class)]
final class SprintControllerTest extends TestCase
{
    /** @var Sprint&\PHPUnit\Framework\MockObject\MockObject */
    private $sprintModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var Task&\PHPUnit\Framework\MockObject\MockObject */
    private $taskModel;
    /** @var Template&\PHPUnit\Framework\MockObject\MockObject */
    private $templateModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sprintModel = $this->createMock(Sprint::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->taskModel = $this->createMock(Task::class);
        $this->templateModel = $this->createMock(Template::class);

        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getResultsPerPage')->willReturn(10);
        $settingsService->method('getSprintSettings')->willReturn([
            'team_capacity_hours' => 40,
            'team_capacity_story_points' => 20,
            'estimation_method' => 'hours',
            'team_size' => 5,
            'default_sprint_length' => 14,
        ]);
        $this->seedSingleton(SettingsService::class, $settingsService);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        // Harmless generic default so `new User()`/`new Milestone()`/`new Sprint()`
        // etc. constructed along the way never reach for a real connection.
        $this->seedSingleton(Database::class, $this->fakeDatabase());

        $_SESSION = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);
        $this->seedSingleton(Database::class, null);

        $_SESSION = [];
        $_GET = [];

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $instance): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $instance);
    }

    /**
     * @return Database&\PHPUnit\Framework\MockObject\MockObject
     */
    private function fakeDatabase(array $fetchAllRows = [], mixed $fetchColumnValue = 0): Database
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($fetchAllRows);
        $stmt->method('fetchColumn')->willReturn($fetchColumnValue);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($stmt);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    private function controller(): SprintControllerTestable
    {
        $c = new SprintControllerTestable();
        $ref = new ReflectionClass(SprintController::class);
        $ref->getProperty('sprintModel')->setValue($c, $this->sprintModel);
        $ref->getProperty('projectModel')->setValue($c, $this->projectModel);
        $ref->getProperty('taskModel')->setValue($c, $this->taskModel);
        $ref->getProperty('templateModel')->setValue($c, $this->templateModel);

        return $c;
    }

    private function withSession(int $userId = 7): void
    {
        $_SESSION['user'] = [
            'id' => $userId,
            'profile' => ['id' => $userId],
            'roles' => [],
            'permissions' => [],
            'config' => [],
        ];
    }

    private function expectRedirect(SprintControllerTestable $c, callable $call): RuntimeException
    {
        try {
            $call();
            $this->fail('Expected a redirect exception to be thrown');
        } catch (RuntimeException $e) {
            return $e;
        }
    }

    private function captureOutput(callable $fn): string
    {
        ob_start();
        $fn();

        return ob_get_clean();
    }

    private function sprint(int $id, int $statusId, int $projectId = 1): \stdClass
    {
        $s = new \stdClass();
        $s->id = $id;
        $s->status_id = $statusId;
        $s->project_id = $projectId;
        $s->is_deleted = false;
        $s->name = "Sprint {$id}";

        return $s;
    }

    // ---- index() --------------------------------------------------

    public function testIndexWithoutProjectIdAggregatesCountsPerProject(): void
    {
        $projects = [(object) ['id' => 1], (object) ['id' => 2]];
        $this->projectModel->method('getAllWithDetails')->willReturn($projects);
        $this->sprintModel->method('getAllWithTasks')->willReturn([(object) ['id' => 99]]);
        $this->sprintModel->method('getByProjectId')->willReturnMap([
            [1, [$this->sprint(1, 1), $this->sprint(2, 2), $this->sprint(3, 4)]],
            [2, []],
        ]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Sprints/index', $c->renderedView);
        $this->assertSame($projects, $c->renderedData['projects']);
        $this->assertNull($c->renderedData['project']);
        $counts = $c->renderedData['projectSprintCounts'];
        $this->assertSame(['active' => 1, 'completed' => 1, 'planning' => 1, 'total' => 3], $counts[1]);
        $this->assertSame(['active' => 0, 'completed' => 0, 'planning' => 0, 'total' => 0], $counts[2]);
        $this->assertContains('view_sprints', $c->requiredPermissions);
    }

    public function testIndexWithProjectIdScopesToThatProject(): void
    {
        $project = (object) ['id' => 5, 'is_deleted' => false];
        $this->projectModel->method('findWithDetails')->with(5)->willReturn($project);
        $sprints = [$this->sprint(1, 2, 5)];
        $this->sprintModel->method('getByProjectId')->with(5)->willReturn($sprints);

        $c = $this->controller();
        $c->index('GET', ['id' => '5']);

        $this->assertSame($project, $c->renderedData['project']);
        $this->assertSame($sprints, $c->renderedData['sprints']);
        $this->assertSame([], $c->renderedData['projects']);
    }

    public function testIndexProjectNotFoundRedirects(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->index('GET', ['id' => '5']));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testIndexGenericExceptionRedirectsToDashboard(): void
    {
        $this->projectModel->method('getAllWithDetails')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->index('GET', []));

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching sprints.', $c->redirectMessage);
    }

    // ---- view() -----------------------------------------------------

    public function testViewValidRenders(): void
    {
        $sprint = $this->sprint(1, 2);
        $this->sprintModel->method('find')->with(1)->willReturn($sprint);
        $this->sprintModel->method('getSprintTasks')->with(1)->willReturn([]);
        $this->sprintModel->method('getSprintHierarchy')->with(1)->willReturn(['nodes' => []]);
        $this->projectModel->method('find')->with(1)->willReturn((object) ['id' => 1]);

        $c = $this->controller();
        $c->view('GET', ['id' => '1']);

        $this->assertSame('Sprints/view', $c->renderedView);
        $this->assertSame($sprint, $c->renderedData['sprint']);
        $this->assertSame(['nodes' => []], $sprint->hierarchy);
    }

    public function testViewInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->view('GET', []));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('Invalid sprint ID', $c->redirectMessage);
    }

    public function testViewNotFoundRedirects(): void
    {
        $this->sprintModel->method('find')->willReturn(false);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->view('GET', ['id' => '9']));

        $this->assertSame('Sprint not found', $c->redirectMessage);
    }

    public function testViewGenericExceptionRedirectsToDashboard(): void
    {
        $this->sprintModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->view('GET', ['id' => '1']));

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching sprint details.', $c->redirectMessage);
    }

    // ---- current() ----------------------------------------------------

    public function testCurrentNoSessionUserRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->current('GET', []));

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching current sprint information.', $c->redirectMessage);
    }

    public function testCurrentValidBuildsSprintDetailsAndDedupes(): void
    {
        $this->withSession(7);
        $sprintA = $this->sprint(1, 2);
        $sprintB = $this->sprint(1, 2); // same id as A -> deduped
        $sprintC = $this->sprint(2, 2);
        $this->sprintModel->method('getActiveSprintsForUser')->willReturn([$sprintA]);
        $this->sprintModel->method('getActiveSprintsInUserProjects')->willReturn([$sprintB, $sprintC]);
        $this->sprintModel->method('getSprintTasks')->willReturn([
            (object) ['id' => 1, 'assigned_to' => 7, 'status_id' => 1],
            (object) ['id' => 2, 'assigned_to' => 99, 'status_id' => 1],
        ]);
        $this->projectModel->method('find')->willReturn((object) ['id' => 1]);

        $c = $this->controller();
        $c->current('GET', []);

        $this->assertSame('Sprints/current', $c->renderedView);
        $this->assertCount(2, $c->renderedData['sprintDetails']);
        $this->assertCount(2, $c->renderedData['sprintDetails'][0]['all_tasks']);
        $this->assertCount(1, $c->renderedData['sprintDetails'][0]['user_tasks']);
    }

    public function testCurrentExceptionRedirects(): void
    {
        $this->withSession(7);
        $this->sprintModel->method('getActiveSprintsForUser')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->current('GET', []));

        $this->assertSame('An error occurred while fetching current sprint information.', $c->redirectMessage);
    }

    // ---- board() --------------------------------------------------

    public function testBoardValidOrganizesTasksByStatus(): void
    {
        $sprint = $this->sprint(1, 2);
        $this->sprintModel->method('findWithDetails')->with(1)->willReturn($sprint);
        $this->projectModel->method('findWithDetails')->with(1)->willReturn((object) ['id' => 1, 'is_deleted' => false]);
        $this->sprintModel->method('getSprintTasks')->willReturn([
            (object) ['id' => 1, 'status_id' => 6, 'story_points' => 3, 'estimated_time' => 3600],
            (object) ['id' => 2, 'status_id' => 3, 'story_points' => 2, 'estimated_time' => 0],
        ]);
        $this->taskModel->method('getTaskStatuses')->willReturn([
            (object) ['id' => 3, 'name' => 'In Progress'],
            (object) ['id' => 6, 'name' => 'Completed'],
        ]);

        $c = $this->controller();
        $c->board('GET', ['id' => '1']);

        $this->assertSame('Sprints/board', $c->renderedView);
        $byStatus = $c->renderedData['tasksByStatus'];
        $this->assertCount(1, $byStatus[6]['tasks']);
        $this->assertCount(1, $byStatus[3]['tasks']);
        $this->assertSame(50.0, $c->renderedData['sprintStats']['completion_percentage']);
    }

    public function testBoardInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->board('GET', []));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('Invalid sprint ID', $c->redirectMessage);
    }

    public function testBoardSprintNotFoundRedirects(): void
    {
        $this->sprintModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->board('GET', ['id' => '1']));

        $this->assertSame('Sprint not found', $c->redirectMessage);
    }

    public function testBoardProjectNotFoundRedirects(): void
    {
        $this->sprintModel->method('findWithDetails')->willReturn($this->sprint(1, 2));
        $this->projectModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->board('GET', ['id' => '1']));

        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testBoardGenericExceptionRedirects(): void
    {
        $this->sprintModel->method('findWithDetails')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->board('GET', ['id' => '1']));

        $this->assertSame('An error occurred while loading the sprint board.', $c->redirectMessage);
    }

    // ---- planning() -------------------------------------------------

    public function testPlanningNoSessionUserRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->planning('GET', []));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('An error occurred while loading sprint planning.', $c->redirectMessage);
    }

    public function testPlanningWithoutProjectIdListsUserProjectsOnly(): void
    {
        $this->withSession(7);
        // User::getUserProjects() goes through Database::getConnection()->prepare(),
        // stubbed via fakeDatabase() to return one project row.
        $this->seedSingleton(Database::class, $this->fakeDatabase([(object) ['id' => 1]]));

        $c = $this->controller();
        $c->planning('GET', []);

        $this->assertSame('Sprints/planning', $c->renderedView);
        $this->assertNull($c->renderedData['selectedProject']);
        $this->assertSame([], $c->renderedData['productBacklog']);
    }

    public function testPlanningWithValidProjectPopulatesBacklogAndCapacity(): void
    {
        $this->withSession(7);
        $this->seedSingleton(Database::class, $this->fakeDatabase());
        $project = (object) ['id' => 3, 'is_deleted' => false];
        $this->projectModel->method('findWithDetails')->with(3)->willReturn($project);
        $this->taskModel->method('getProductBacklog')->willReturn([(object) ['id' => 1]]);
        $this->sprintModel->method('getByProjectId')->willReturn([
            $this->sprint(1, 2, 3),
            $this->sprint(2, 1, 3),
        ]);

        $c = $this->controller();
        $c->planning('GET', ['project_id' => '3']);

        $this->assertSame($project, $c->renderedData['selectedProject']);
        $this->assertCount(1, $c->renderedData['productBacklog']);
        $this->assertCount(1, $c->renderedData['activeSprints']);
        $this->assertSame(40, $c->renderedData['sprintCapacity']['hours']);
    }

    public function testPlanningWithDeletedProjectKeepsSelectionEmpty(): void
    {
        $this->withSession(7);
        $this->seedSingleton(Database::class, $this->fakeDatabase());
        $deletedProject = (object) ['id' => 3, 'is_deleted' => true];
        $this->projectModel->method('findWithDetails')->willReturn($deletedProject);

        $c = $this->controller();
        $c->planning('GET', ['project_id' => '3']);

        // selectedProject is still assigned (only the backlog/capacity population
        // is gated on !is_deleted), so it holds the deleted project as-is.
        $this->assertSame($deletedProject, $c->renderedData['selectedProject']);
        $this->assertSame([], $c->renderedData['productBacklog']);
    }

    // ---- createForm() ---------------------------------------------

    public function testCreateFormValidRenders(): void
    {
        $project = (object) ['id' => 3, 'is_deleted' => false, 'company_id' => 9];
        $this->projectModel->method('find')->with(3)->willReturn($project);
        $this->taskModel->method('getByProjectId')->willReturn([]);
        $this->sprintModel->method('getSprintStatuses')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->with('sprint', 9)->willReturn([]);

        $c = $this->controller();
        $c->createForm('GET', ['id' => '3']);

        $this->assertSame('Sprints/create', $c->renderedView);
        $this->assertSame(9, $c->renderedData['companyId']);
    }

    public function testCreateFormMissingProjectIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->createForm('GET', []));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('Project ID is required', $c->redirectMessage);
    }

    public function testCreateFormProjectNotFoundRedirects(): void
    {
        $this->projectModel->method('find')->willReturn(false);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->createForm('GET', ['id' => '3']));

        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testCreateFormGenericExceptionRedirectsToDashboard(): void
    {
        $this->projectModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->createForm('GET', ['id' => '3']));

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while loading the sprint creation form.', $c->redirectMessage);
    }

    // ---- create()/update() GET delegation --------------------------

    public function testCreateGetDelegatesToCreateForm(): void
    {
        $project = (object) ['id' => 3, 'is_deleted' => false];
        $this->projectModel->method('find')->willReturn($project);
        $this->taskModel->method('getByProjectId')->willReturn([]);
        $this->sprintModel->method('getSprintStatuses')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([]);

        $c = $this->controller();
        $c->create('GET', ['id' => '3']);

        $this->assertSame('Sprints/create', $c->renderedView);
    }

    public function testUpdateGetDelegatesToEditForm(): void
    {
        $sprint = $this->sprint(1, 1);
        $project = (object) ['id' => 1, 'is_deleted' => false];
        $this->sprintModel->method('find')->willReturn($sprint);
        $this->projectModel->method('find')->willReturn($project);
        $this->taskModel->method('getByProjectId')->willReturn([]);
        $this->sprintModel->method('getSprintTasks')->willReturn([]);
        $this->sprintModel->method('getSprintStatuses')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([]);

        $c = $this->controller();
        $c->update('GET', ['id' => '1']);

        $this->assertSame('Sprints/edit', $c->renderedView);
    }

    // ---- editForm() -------------------------------------------------

    public function testEditFormValidRenders(): void
    {
        $sprint = $this->sprint(1, 1);
        $project = (object) ['id' => 1, 'is_deleted' => false, 'company_id' => 4];
        $this->sprintModel->method('find')->willReturn($sprint);
        $this->projectModel->method('find')->willReturn($project);
        $this->taskModel->method('getByProjectId')->willReturn([]);
        $this->sprintModel->method('getSprintTasks')->willReturn([(object) ['id' => 5]]);
        $this->sprintModel->method('getSprintStatuses')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '1']);

        $this->assertSame('Sprints/edit', $c->renderedView);
        $this->assertSame([5], $c->renderedData['sprintTaskIds']);
    }

    public function testEditFormGenericExceptionRedirectsToDashboard(): void
    {
        $this->sprintModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->editForm('GET', ['id' => '1']));

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while loading the edit form.', $c->redirectMessage);
    }

    // ---- delete()/addTasks() method guard ---------------------------

    public function testDeleteNonPostRedirectsWithError(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delete('GET', []));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testAddTasksNonPostRedirectsWithError(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->addTasks('GET', []));

        $this->assertSame('/sprints', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    // ---- assignTask() -----------------------------------------------

    public function testAssignTaskNonPostReturns405(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->assignTask('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($output, true));
    }

    public function testAssignTaskMissingFieldsReturns400(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->assignTask('POST', []));

        $this->assertSame(
            ['success' => false, 'message' => 'Missing task_id or sprint_id'],
            json_decode($output, true)
        );
    }

    // ---- createFromMilestones() --------------------------------------

    public function testCreateFromMilestonesNonPostRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->createFromMilestones('GET', []));

        $this->assertSame('/sprints/planning', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testCreateFromMilestonesValidationFailureRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->createFromMilestones('POST', []));

        $this->assertSame('/sprints/planning', $c->redirectUrl);
        $this->assertStringStartsWith('Validation error:', $c->redirectMessage);
    }

    public function testCreateFromMilestonesGenericExceptionRedirects(): void
    {
        // Validator's `exists:projects,id` must pass to reach sprintModel->createFromMilestones().
        $this->seedSingleton(Database::class, $this->fakeDatabase([], 1));
        $this->sprintModel->method('createFromMilestones')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $data = [
            'name' => 'Sprint X',
            'project_id' => '3',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-15',
        ];
        $this->expectRedirect($c, fn () => $c->createFromMilestones('POST', $data));

        $this->assertSame('An error occurred while creating the sprint.', $c->redirectMessage);
    }

    // ---- getMilestonesForPlanning() --------------------------------

    public function testGetMilestonesForPlanningMissingProjectIdReturns400(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->getMilestonesForPlanning('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Invalid project ID'], json_decode($output, true));
    }

    public function testGetMilestonesForPlanningValidReturnsMilestones(): void
    {
        // Milestone::getAvailableForSprint() goes through Database::executeQuery().
        $this->seedSingleton(Database::class, $this->fakeDatabase([['id' => 1]]));

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->getMilestonesForPlanning('GET', ['project_id' => '3']));
        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['success']);
        $this->assertSame([['id' => 1]], $decoded['milestones']);
    }

    // ---- getTasksFromMilestones() -----------------------------------

    public function testGetTasksFromMilestonesEmptyIdsReturnsEmptyTasks(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->getTasksFromMilestones('POST', []));

        $this->assertSame(['success' => true, 'tasks' => []], json_decode($output, true));
    }

    // ---- startSprint() ------------------------------------------------

    public function testStartSprintFromPlanningSucceeds(): void
    {
        $sprint = $this->sprint(1, SprintStatus::PLANNING->value);
        $this->sprintModel->method('find')->willReturn($sprint);
        $this->sprintModel->expects($this->once())->method('update')
            ->with(1, ['status_id' => SprintStatus::ACTIVE->value])->willReturn(true);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->startSprint('POST', ['id' => '1']));

        $this->assertSame('/sprints/view/1', $c->redirectUrl);
        $this->assertSame('Sprint started successfully.', $c->redirectMessage);
    }

    public function testStartSprintFromNonPlanningFails(): void
    {
        $sprint = $this->sprint(1, SprintStatus::ACTIVE->value);
        $this->sprintModel->method('find')->willReturn($sprint);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->startSprint('POST', ['id' => '1']));

        $this->assertSame('Only sprints in Planning status can be started', $c->redirectMessage);
    }

    // ---- completeSprint() ---------------------------------------------

    public function testCompleteSprintFromActiveSucceeds(): void
    {
        $sprint = $this->sprint(1, SprintStatus::ACTIVE->value);
        $this->sprintModel->method('find')->willReturn($sprint);
        $this->sprintModel->expects($this->once())->method('update')
            ->with(1, ['status_id' => SprintStatus::COMPLETED->value])->willReturn(true);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->completeSprint('POST', ['id' => '1']));

        $this->assertSame('Sprint completed successfully.', $c->redirectMessage);
    }

    public function testCompleteSprintFromPlanningFails(): void
    {
        $sprint = $this->sprint(1, SprintStatus::PLANNING->value);
        $this->sprintModel->method('find')->willReturn($sprint);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->completeSprint('POST', ['id' => '1']));

        $this->assertSame('Only active or in-review sprints can be completed', $c->redirectMessage);
    }

    // ---- delaySprint() --------------------------------------------

    public function testDelaySprintFromActiveSucceeds(): void
    {
        $sprint = $this->sprint(1, SprintStatus::ACTIVE->value);
        $this->sprintModel->method('find')->willReturn($sprint);
        $this->sprintModel->expects($this->once())->method('update')
            ->with(1, ['status_id' => SprintStatus::DELAYED->value])->willReturn(true);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delaySprint('POST', ['id' => '1']));

        $this->assertSame('Sprint marked as delayed.', $c->redirectMessage);
    }

    public function testDelaySprintFromPlanningFails(): void
    {
        $sprint = $this->sprint(1, SprintStatus::PLANNING->value);
        $this->sprintModel->method('find')->willReturn($sprint);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delaySprint('POST', ['id' => '1']));

        $this->assertSame('Only active sprints can be delayed', $c->redirectMessage);
    }

    // ---- cancelSprint() -------------------------------------------

    public function testCancelSprintFromActiveSucceeds(): void
    {
        $sprint = $this->sprint(1, SprintStatus::ACTIVE->value, 6);
        $this->sprintModel->method('find')->willReturn($sprint);
        $this->sprintModel->expects($this->once())->method('update')
            ->with(1, ['status_id' => SprintStatus::CANCELLED->value])->willReturn(true);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->cancelSprint('POST', ['id' => '1']));

        $this->assertSame('/sprints/project/6', $c->redirectUrl);
        $this->assertSame('Sprint cancelled successfully.', $c->redirectMessage);
    }

    public function testCancelSprintAlreadyFinalFails(): void
    {
        $sprint = $this->sprint(1, SprintStatus::COMPLETED->value);
        $this->sprintModel->method('find')->willReturn($sprint);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->cancelSprint('POST', ['id' => '1']));

        $this->assertSame('Sprint is already in a final state and cannot be cancelled', $c->redirectMessage);
    }

    public function testCancelSprintInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->cancelSprint('POST', []));

        $this->assertSame('Invalid sprint ID', $c->redirectMessage);
    }

    public function testCancelSprintNotFoundRedirects(): void
    {
        $this->sprintModel->method('find')->willReturn(false);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->cancelSprint('POST', ['id' => '9']));

        $this->assertSame('Sprint not found', $c->redirectMessage);
    }

    // ---- getProjectSprintsApi() ----------------------------------

    public function testGetProjectSprintsApiValidReturnsSprints(): void
    {
        $sprints = [$this->sprint(1, 2)];
        $this->sprintModel->method('getByProjectId')->with(3)->willReturn($sprints);

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->getProjectSprintsApi('GET', ['project_id' => '3']));
        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['success']);
        $this->assertCount(1, $decoded['sprints']);
    }

    public function testGetProjectSprintsApiInvalidProjectIdReturnsError(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->getProjectSprintsApi('GET', []));

        $this->assertSame(
            ['success' => false, 'message' => 'Invalid project ID'],
            json_decode($output, true)
        );
    }
}
