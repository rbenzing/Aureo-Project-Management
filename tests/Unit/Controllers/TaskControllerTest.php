<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\TaskController;
use App\Core\Config;
use App\Core\Database;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\Event;
use App\Events\EventDispatcher;
use App\Events\TaskAssigned;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Validator;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Testable subclass: capture render()/redirect*() calls instead of performing
 * their real side effects (real render() emits HTML and trips
 * beStrictAboutOutputDuringTests; real redirect*() calls header()+exit and
 * would kill the runner). Each override throws so callers relying on the
 * real `never` return type still stop executing at the same point.
 */
final class TaskControllerTestable extends TaskController
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

    /**
     * Several actions (create/update/delete/addComment) call
     * redirectWithSuccess() as the LAST statement of their `try` block, with
     * a sibling `catch (\Throwable)` immediately after. In production that
     * is safe: redirectWithSuccess() ends in exit(), which terminates the
     * process before the catch can ever run. Here it throws instead (so the
     * test doesn't die), and that throw — unlike a real exit() — IS visible
     * to the surrounding catch, which then issues its own generic
     * redirectWithError() "recovering" from what it thinks was a failure.
     * Recording only the first call (and always (re)throwing so `never` is
     * honoured and control still returns to the test) captures the redirect
     * the real code path would actually have produced.
     */
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
 * Behavioural tests for TaskController.
 *
 * SettingsService/LoggerService/Database/SecurityService are process-wide
 * singletons reached directly (not injected) by BaseController and by
 * Validator/SecurityService themselves, so they are seeded with mocks via
 * reflection before every test and reset to null in tearDown. Database's
 * executeQuery() is stubbed to satisfy Validator's `exists` rule checks
 * (they only run when a value is present — empty/missing values short
 * circuit before ever touching the DB).
 *
 * Known uncoverable branch (raw exit, not chased per task instructions):
 * TaskController::update()'s `catch (InvalidArgumentException $e)` block
 * (~lines 413-417) calls header()+exit directly instead of the overridable
 * $this->redirect()/$this->redirectWithError() helpers every other catch
 * block in this class uses (compare create()'s equivalent catch, which
 * calls $this->redirect()). Invoking it would kill the test runner, so the
 * invalid-id/task-not-found/validation-failure paths of update() are not
 * exercised here — only its success path and its generic \Throwable catch
 * (which does use the overridable helper) are covered.
 *
 * Also not covered: the "valid JSON body" branches of updateStatus(),
 * addComment() and updateBacklogPriorities(). These read
 * file_get_contents('php://input'), which is always empty under the CLI
 * SAPI PHPUnit runs under, so only the "input absent/empty" branches are
 * reachable without additional stream-wrapper test infrastructure.
 */
#[CoversClass(TaskController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Database::class)]
#[UsesClass(Config::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Validator::class)]
#[UsesClass(EventDispatcher::class)]
#[UsesClass(TaskAssigned::class)]
#[UsesClass(Event::class)]
#[UsesClass(Priority::class)]
#[UsesClass(TaskType::class)]
#[UsesClass(TaskStatus::class)]
#[UsesClass(Project::class)]
#[UsesClass(User::class)]
#[UsesClass(Sprint::class)]
final class TaskControllerTest extends TestCase
{
    /** @var Task&\PHPUnit\Framework\MockObject\MockObject */
    private $taskModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var Sprint&\PHPUnit\Framework\MockObject\MockObject */
    private $sprintModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taskModel = $this->createMock(Task::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->userModel = $this->createMock(User::class);
        $this->sprintModel = $this->createMock(Sprint::class);

        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getResultsPerPage')->willReturn(10);
        $this->seedSingleton(SettingsService::class, $settingsService);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(Database::class, null);
        $this->seedSingleton(SecurityService::class, null);

        $_SESSION = [];
        $_GET = [];
        unset($_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);
        $this->seedSingleton(Database::class, null);
        $this->seedSingleton(SecurityService::class, null);

        $_SESSION = [];
        $_GET = [];
        unset($_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $instance): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $instance);
    }

    private function controller(): TaskControllerTestable
    {
        return new TaskControllerTestable(
            $this->taskModel,
            $this->projectModel,
            $this->userModel,
            $this->sprintModel
        );
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

    /** Database mock whose exists/unique checks always succeed. */
    private function seedPassingDatabase(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(1);
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($stmt);
        $this->seedSingleton(Database::class, $db);
    }

    private function expectRedirect(TaskControllerTestable $c, callable $call): RuntimeException
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

    // ---- index() ---------------------------------------------------

    public function testIndexUnassignedUriUsesUnassignedTaskQueries(): void
    {
        $_SERVER['REQUEST_URI'] = '/tasks/unassigned';
        $this->taskModel->method('getUnassignedTasks')->willReturn([(object) ['id' => 1]]);
        $this->taskModel->method('countUnassignedTasks')->willReturn(1);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Tasks/index', $c->renderedView);
        $this->assertTrue($c->renderedData['isUnassigned']);
        $this->assertSame(1, $c->renderedData['totalTasks']);
        $this->assertContains('view_tasks', $c->requiredPermissions);
    }

    public function testIndexAssignedUserIdUsesUserQueries(): void
    {
        $_SERVER['REQUEST_URI'] = '/tasks';
        $this->taskModel->method('getByUserId')->with(9, $this->anything(), $this->anything())->willReturn([(object) ['id' => 2]]);
        $this->taskModel->method('count')->with(['assigned_to' => 9, 'is_deleted' => 0])->willReturn(1);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', ['id' => '9']);

        $this->assertFalse($c->renderedData['isUnassigned']);
        $this->assertSame(9, $c->renderedData['assignedUserId']);
    }

    public function testIndexProjectIdUsesProjectQueries(): void
    {
        $_SERVER['REQUEST_URI'] = '/tasks';
        $_GET['project_id'] = '4';
        $tasks = [(object) ['id' => 3], (object) ['id' => 4]];
        $this->taskModel->method('getByProjectId')->with(4)->willReturn($tasks);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', ['project_id' => '4']);

        $this->assertSame(2, $c->renderedData['totalTasks']);
        $this->assertSame(4, $c->renderedData['projectId']);
    }

    public function testIndexDefaultUsesAllWithDetails(): void
    {
        $_SERVER['REQUEST_URI'] = '/tasks';
        $this->taskModel->method('getAllWithDetails')->willReturn([(object) ['id' => 1]]);
        $this->taskModel->method('count')->with(['is_deleted' => 0])->willReturn(1);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame(1, $c->renderedData['totalTasks']);
        $this->assertFalse($c->renderedData['assignedUserId']);
        $this->assertFalse($c->renderedData['projectId']);
    }

    public function testIndexExceptionRedirectsToDashboard(): void
    {
        $_SERVER['REQUEST_URI'] = '/tasks';
        $this->taskModel->method('getAllWithDetails')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->index('GET', []));

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching tasks.', $c->redirectMessage);
    }

    // ---- backlog() --------------------------------------------------

    public function testBacklogWithProjectId(): void
    {
        $_GET['project_id'] = '5';
        $this->taskModel->method('getProductBacklog')->with($this->anything(), $this->anything(), 5)->willReturn([(object) ['id' => 1]]);
        $this->taskModel->method('countProductBacklog')->with(5)->willReturn(1);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->backlog('GET', []);

        $this->assertSame('Tasks/backlog', $c->renderedView);
        $this->assertSame(5, $c->renderedData['projectId']);
    }

    public function testBacklogWithoutProjectId(): void
    {
        $this->taskModel->method('getProductBacklog')->with($this->anything(), $this->anything(), null)->willReturn([]);
        $this->taskModel->method('countProductBacklog')->with(null)->willReturn(0);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->backlog('GET', []);

        $this->assertNull($c->renderedData['projectId']);
    }

    public function testBacklogExceptionRedirectsToTasks(): void
    {
        $this->taskModel->method('getProductBacklog')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->backlog('GET', []));

        $this->assertSame('/tasks', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching the backlog.', $c->redirectMessage);
    }

    // ---- view() -------------------------------------------------------

    public function testViewValidTaskRenders(): void
    {
        $task = (object) ['id' => 1, 'is_deleted' => false, 'project_id' => 3];
        $this->taskModel->method('findWithDetails')->with(1)->willReturn($task);
        $this->projectModel->method('findWithDetails')->with(3)->willReturn((object) ['id' => 3]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->userModel->method('getAllUsers')->willReturn([]);

        $c = $this->controller();
        $c->view('GET', ['id' => '1']);

        $this->assertSame('Tasks/view', $c->renderedView);
        $this->assertSame($task, $c->renderedData['task']);
    }

    public function testViewInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->view('GET', ['id' => 'abc']));

        $this->assertSame('/tasks', $c->redirectUrl);
        $this->assertSame('Invalid task ID', $c->redirectMessage);
    }

    public function testViewNotFoundRedirects(): void
    {
        $this->taskModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->view('GET', ['id' => '99']));

        $this->assertSame('Task not found', $c->redirectMessage);
    }

    public function testViewGenericExceptionRedirects(): void
    {
        $this->taskModel->method('findWithDetails')->willThrowException(new RuntimeException('db error'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->view('GET', ['id' => '1']));

        $this->assertSame('An error occurred while fetching task details.', $c->redirectMessage);
    }

    // ---- createForm() ---------------------------------------------

    public function testCreateFormRendersFieldsAndClearsSessionFormData(): void
    {
        $_GET['project_id'] = '3';
        $_GET['parent_task_id'] = '8';
        $_SESSION['form_data'] = ['title' => 'stale'];
        $this->projectModel->method('getAllWithDetails')->willReturn([]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->userModel->method('getAllUsers')->willReturn([]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Tasks/create', $c->renderedView);
        $this->assertSame(3, $c->renderedData['projectId']);
        $this->assertSame(8, $c->renderedData['parentTaskId']);
        $this->assertSame(['title' => 'stale'], $c->renderedData['formData']);
        $this->assertArrayNotHasKey('form_data', $_SESSION);
    }

    public function testCreateFormExceptionRedirects(): void
    {
        $this->projectModel->method('getAllWithDetails')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->createForm('GET', []));

        $this->assertSame('An error occurred while loading the creation form.', $c->redirectMessage);
    }

    // ---- create() ---------------------------------------------------

    public function testCreateGetDelegatesToCreateForm(): void
    {
        $this->projectModel->method('getAllWithDetails')->willReturn([]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->userModel->method('getAllUsers')->willReturn([]);

        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('Tasks/create', $c->renderedView);
    }

    public function testCreatePostInvalidDataRedirectsBackWithSessionError(): void
    {
        $c = $this->controller();
        $e = $this->expectRedirect($c, fn () => $c->create('POST', []));

        // The flash now travels through redirectWithError() rather than being
        // assigned to $_SESSION['error'] before a bare redirect(). Production
        // behaviour is identical — the helper sets that exact key — but the
        // testable subclass captures the call instead, so assert on the captured
        // message and confirm the error-carrying helper was the one used.
        $this->assertSame('/tasks/create', $c->redirectUrl);
        $this->assertStringStartsWith('redirectError:', $e->getMessage());
        $this->assertNotSame('', (string) $c->redirectMessage);
        $this->assertSame([], $_SESSION['form_data']);
    }

    public function testCreatePostValidWithoutAssignedToRedirectsWithSuccess(): void
    {
        $this->seedPassingDatabase();
        $this->taskModel->method('create')->willReturn(42);

        $c = $this->controller();
        $data = ['title' => 'New task', 'project_id' => '3', 'status_id' => '1', 'task_type' => 'task'];
        $this->expectRedirect($c, fn () => $c->create('POST', $data));

        $this->assertSame('/tasks/view/42', $c->redirectUrl);
        $this->assertSame('Task created successfully.', $c->redirectMessage);
        $this->assertContains('create_tasks', $c->requiredPermissions);
    }

    public function testCreatePostValidWithAssignedToDispatchesEventWithoutError(): void
    {
        $this->withSession(7);
        $this->seedPassingDatabase();
        $this->taskModel->method('create')->willReturn(55);

        $c = $this->controller();
        $data = [
            'title' => 'Assigned task',
            'project_id' => '3',
            'status_id' => '1',
            'task_type' => 'bug',
            'assigned_to' => '5',
            'description' => 'Some description',
        ];
        $this->expectRedirect($c, fn () => $c->create('POST', $data));

        $this->assertSame('/tasks/view/55', $c->redirectUrl);
    }

    public function testCreatePostExceptionRedirectsWithError(): void
    {
        $this->seedPassingDatabase();
        $this->taskModel->method('create')->willThrowException(new RuntimeException('insert failed'));

        $c = $this->controller();
        $data = ['title' => 'New task', 'project_id' => '3', 'status_id' => '1', 'task_type' => 'task'];
        $this->expectRedirect($c, fn () => $c->create('POST', $data));

        $this->assertSame('/tasks/create', $c->redirectUrl);
        $this->assertSame('An error occurred while creating the task.', $c->redirectMessage);
    }

    // ---- editForm() -----------------------------------------------

    public function testEditFormValidRenders(): void
    {
        $task = (object) ['id' => 1, 'is_deleted' => false];
        $this->taskModel->method('find')->with(1)->willReturn($task);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->userModel->method('getAllUsers')->willReturn([]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '1']);

        $this->assertSame('Tasks/edit', $c->renderedView);
        $this->assertSame($task, $c->renderedData['task']);
    }

    public function testEditFormInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->editForm('GET', []));

        $this->assertSame('Invalid task ID', $c->redirectMessage);
    }

    public function testEditFormNotFoundRedirects(): void
    {
        $this->taskModel->method('find')->willReturn(false);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->editForm('GET', ['id' => '1']));

        $this->assertSame('Task not found', $c->redirectMessage);
    }

    public function testEditFormExceptionRedirects(): void
    {
        $this->taskModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->editForm('GET', ['id' => '1']));

        $this->assertSame('An error occurred while loading the edit form.', $c->redirectMessage);
    }

    // ---- update() -----------------------------------------------------

    public function testUpdateGetDelegatesToEditForm(): void
    {
        $task = (object) ['id' => 1, 'is_deleted' => false];
        $this->taskModel->method('find')->willReturn($task);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->userModel->method('getAllUsers')->willReturn([]);

        $c = $this->controller();
        $c->update('GET', ['id' => '1']);

        $this->assertSame('Tasks/edit', $c->renderedView);
    }

    public function testUpdatePostValidSetsCompleteDateWhenTransitioningToCompleted(): void
    {
        $this->seedPassingDatabase();
        $current = (object) ['id' => 1, 'assigned_to' => null, 'complete_date' => null, 'is_deleted' => false];
        $this->taskModel->method('find')->with(1)->willReturn($current);
        $capturedData = null;
        $this->taskModel->expects($this->once())->method('update')
            ->with(1, $this->callback(function ($data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            }))
            ->willReturn(true);

        $c = $this->controller();
        $data = [
            'id' => '1',
            'title' => 'Updated',
            'project_id' => '3',
            'status_id' => (string) TaskStatus::COMPLETED->value,
            'task_type' => 'task',
        ];
        $this->expectRedirect($c, fn () => $c->update('POST', $data));

        $this->assertSame('/tasks/view/1', $c->redirectUrl);
        $this->assertSame('Task updated successfully.', $c->redirectMessage);
        $this->assertArrayHasKey('complete_date', $capturedData);
    }

    public function testUpdatePostValidKeepsExistingCompleteDateUnchanged(): void
    {
        $this->seedPassingDatabase();
        $current = (object) ['id' => 1, 'assigned_to' => null, 'complete_date' => '2026-01-01', 'is_deleted' => false];
        $this->taskModel->method('find')->with(1)->willReturn($current);
        $capturedData = null;
        $this->taskModel->method('update')
            ->with(1, $this->callback(function ($data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            }))
            ->willReturn(true);

        $c = $this->controller();
        $data = [
            'id' => '1',
            'title' => 'Updated',
            'project_id' => '3',
            'status_id' => (string) TaskStatus::COMPLETED->value,
            'task_type' => 'task',
        ];
        $this->expectRedirect($c, fn () => $c->update('POST', $data));

        $this->assertArrayNotHasKey('complete_date', $capturedData);
    }

    public function testUpdatePostValidNonCompletedStatusOmitsCompleteDate(): void
    {
        $this->seedPassingDatabase();
        $current = (object) ['id' => 1, 'assigned_to' => null, 'complete_date' => null, 'is_deleted' => false];
        $this->taskModel->method('find')->willReturn($current);
        $capturedData = null;
        $this->taskModel->method('update')
            ->with(1, $this->callback(function ($data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            }))
            ->willReturn(true);

        $c = $this->controller();
        $data = [
            'id' => '1',
            'title' => 'Updated',
            'project_id' => '3',
            'status_id' => (string) TaskStatus::IN_PROGRESS->value,
            'task_type' => 'task',
        ];
        $this->expectRedirect($c, fn () => $c->update('POST', $data));

        $this->assertArrayNotHasKey('complete_date', $capturedData);
    }

    public function testUpdatePostGenericExceptionRedirectsWithError(): void
    {
        $this->seedPassingDatabase();
        $this->taskModel->method('find')->willThrowException(new RuntimeException('lookup failed'));

        $c = $this->controller();
        $data = [
            'id' => '1',
            'title' => 'Updated',
            'project_id' => '3',
            'status_id' => '1',
            'task_type' => 'task',
        ];
        $this->expectRedirect($c, fn () => $c->update('POST', $data));

        $this->assertSame('/tasks/edit/1', $c->redirectUrl);
        $this->assertSame('An error occurred while updating the task.', $c->redirectMessage);
    }

    // ---- updateStatus() -------------------------------------------

    public function testUpdateStatusNonPostReturns405(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->updateStatus('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($output, true));
    }

    public function testUpdateStatusMissingFieldsReturns400(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->updateStatus('POST', []));

        $this->assertSame(
            ['success' => false, 'message' => 'task_id and status_id are required'],
            json_decode($output, true)
        );
    }

    // ---- delete() -----------------------------------------------------

    public function testDeleteNonPostRedirectsWithError(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delete('GET', []));

        $this->assertSame('/tasks', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testDeletePostInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delete('POST', []));

        $this->assertSame('Invalid task ID', $c->redirectMessage);
    }

    public function testDeletePostNotFoundRedirects(): void
    {
        $this->taskModel->method('find')->willReturn(false);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delete('POST', ['id' => '9']));

        $this->assertSame('Task not found', $c->redirectMessage);
    }

    public function testDeletePostValidSoftDeletesAndRedirects(): void
    {
        $task = (object) ['id' => 1, 'is_deleted' => false, 'project_id' => 6];
        $this->taskModel->method('find')->willReturn($task);
        $this->taskModel->expects($this->once())->method('update')->with(1, ['is_deleted' => true])->willReturn(true);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delete('POST', ['id' => '1']));

        $this->assertSame('/tasks/project/6', $c->redirectUrl);
        $this->assertSame('Task deleted successfully.', $c->redirectMessage);
    }

    public function testDeletePostExceptionRedirects(): void
    {
        $this->taskModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->delete('POST', ['id' => '1']));

        $this->assertSame('An error occurred while deleting the task.', $c->redirectMessage);
    }

    // ---- startTimer() ---------------------------------------------

    public function testStartTimerNonPostReturns405(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->startTimer('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($output, true));
    }

    public function testStartTimerInvalidTaskIdReturns500WithMessage(): void
    {
        $this->withSession(7);

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->startTimer('POST', []));

        $this->assertSame(['success' => false, 'message' => 'Invalid task ID'], json_decode($output, true));
    }

    public function testStartTimerNoSessionUserReturns500(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->startTimer('POST', ['task_id' => '1']));

        $this->assertSame(['success' => false, 'message' => 'User session invalid'], json_decode($output, true));
    }

    public function testStartTimerTaskNotFoundReturns500(): void
    {
        $this->withSession(7);
        $this->taskModel->method('find')->willReturn(false);

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->startTimer('POST', ['task_id' => '1']));

        $this->assertSame(['success' => false, 'message' => 'Task not found'], json_decode($output, true));
    }

    public function testStartTimerValidStartsTimer(): void
    {
        $this->withSession(7);
        $task = (object) ['id' => 1, 'is_deleted' => false];
        $this->taskModel->method('find')->willReturn($task);
        $this->taskModel->expects($this->once())->method('update')
            ->with(1, $this->callback(fn ($d) => array_key_exists('timer_start', $d)))
            ->willReturn(true);

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->startTimer('POST', ['task_id' => '1']));

        $this->assertSame(['success' => true, 'message' => 'Timer started'], json_decode($output, true));
    }

    // ---- stopTimer() ----------------------------------------------

    public function testStopTimerNonPostReturns405(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->stopTimer('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($output, true));
    }

    public function testStopTimerNoRunningTimerReturns500(): void
    {
        $this->withSession(7);
        $task = (object) ['id' => 1, 'is_deleted' => false, 'timer_start' => null];
        $this->taskModel->method('find')->willReturn($task);

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->stopTimer('POST', ['task_id' => '1']));

        $this->assertSame(['success' => false, 'message' => 'No running timer found for this task'], json_decode($output, true));
    }

    public function testStopTimerValidRecordsTimeEntry(): void
    {
        $this->withSession(7);
        $task = (object) [
            'id' => 1,
            'is_deleted' => false,
            'timer_start' => date('Y-m-d H:i:s', time() - 60),
            'time_spent' => 120,
        ];
        $this->taskModel->method('find')->willReturn($task);
        $this->taskModel->expects($this->once())->method('createTimeEntry')->willReturn(1);
        $this->taskModel->expects($this->once())->method('update')
            ->with(1, $this->callback(fn ($d) => $d['timer_start'] === null && $d['time_spent'] > 120))
            ->willReturn(true);

        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->stopTimer('POST', ['task_id' => '1']));
        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['success']);
        $this->assertGreaterThanOrEqual(60, $decoded['elapsed']);
    }

    // ---- addComment() -----------------------------------------------

    public function testAddCommentNonPostReturns405(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->addComment('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($output, true));
    }

    public function testAddCommentInvalidIdRedirects(): void
    {
        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->addComment('POST', []));

        $this->assertSame('/tasks/view/', $c->redirectUrl);
        $this->assertSame('Invalid task ID', $c->redirectMessage);
    }

    public function testAddCommentTaskNotFoundRedirects(): void
    {
        $this->taskModel->method('find')->willReturn(false);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->addComment('POST', ['id' => '1']));

        $this->assertSame('/tasks/view/1', $c->redirectUrl);
        $this->assertSame('Task not found', $c->redirectMessage);
    }

    public function testAddCommentEmptyContentRedirects(): void
    {
        $task = (object) ['id' => 1, 'is_deleted' => false];
        $this->taskModel->method('find')->willReturn($task);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->addComment('POST', ['id' => '1', 'content' => '   ']));

        $this->assertSame('Comment cannot be empty', $c->redirectMessage);
    }

    public function testAddCommentNoSessionUserRedirectsWithGenericMessage(): void
    {
        $task = (object) ['id' => 1, 'is_deleted' => false];
        $this->taskModel->method('find')->willReturn($task);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->addComment('POST', ['id' => '1', 'content' => 'hi']));

        // RuntimeException("User session invalid") is not an InvalidArgumentException,
        // so it falls through to the generic \Throwable catch's generic message.
        $this->assertSame('An error occurred while adding the comment.', $c->redirectMessage);
    }

    public function testAddCommentValidRedirectsWithSuccess(): void
    {
        $this->withSession(7);
        $task = (object) ['id' => 1, 'is_deleted' => false];
        $this->taskModel->method('find')->willReturn($task);
        $this->taskModel->expects($this->once())->method('addComment')->with(1, 7, 'Nice work')->willReturn(3);

        $c = $this->controller();
        $this->expectRedirect($c, fn () => $c->addComment('POST', ['id' => '1', 'content' => 'Nice work']));

        $this->assertSame('/tasks/view/1', $c->redirectUrl);
        $this->assertSame('Comment added.', $c->redirectMessage);
    }

    // ---- updateBacklogPriorities() ---------------------------------

    public function testUpdateBacklogPrioritiesNonPostReturns405(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->updateBacklogPriorities('GET', []));

        $this->assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($output, true));
    }

    public function testUpdateBacklogPrioritiesEmptyInputReturns500(): void
    {
        $c = $this->controller();
        $output = $this->captureOutput(fn () => $c->updateBacklogPriorities('POST', []));

        // The InvalidArgumentException thrown for a missing "priorities" array
        // is caught by the same generic \Throwable handler as any other
        // failure, so the client only ever sees the generic message.
        $this->assertSame(['success' => false, 'message' => 'An error occurred'], json_decode($output, true));
    }
}
