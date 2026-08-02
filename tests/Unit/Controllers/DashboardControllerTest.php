<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\DashboardController;
use App\Core\Config;
use App\Core\Database;
use App\Enums\TaskStatus;
use App\Middleware\AuthMiddleware;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Testable subclass: capture render() output and turn the exiting
 * redirectWithError() into a catchable signal so we can assert on the
 * message DashboardController computed without killing the test runner.
 */
final class DashboardControllerTestable extends DashboardController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;

        throw new RuntimeException('redirect:' . $message);
    }
}

/**
 * Behavioural tests for DashboardController::index().
 *
 * SettingsService, LoggerService and Database are process-wide singletons
 * reached directly by BaseController's constructor and by Dashboard's
 * private helpers (getStoryPointsSummary/getTaskTypeDistribution/
 * getPriorityTasks/getUserBacklogCount all call Database::getInstance()
 * rather than taking an injected Database). They are seeded with mocks via
 * reflection on the static `instance` property before every test and reset
 * to null in tearDown so no live MySQL connection is ever opened and no
 * state leaks into other test files sharing the process.
 */
#[CoversClass(DashboardController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Database::class)]
#[UsesClass(Config::class)]
#[UsesClass(TaskStatus::class)]
#[UsesClass(User::class)]
#[UsesClass(Project::class)]
#[UsesClass(Task::class)]
#[UsesClass(Milestone::class)]
#[UsesClass(Sprint::class)]
final class DashboardControllerTest extends TestCase
{
    /** @var AuthMiddleware&\PHPUnit\Framework\MockObject\MockObject */
    private $authMiddleware;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var Task&\PHPUnit\Framework\MockObject\MockObject */
    private $taskModel;
    /** @var Milestone&\PHPUnit\Framework\MockObject\MockObject */
    private $milestoneModel;
    /** @var Sprint&\PHPUnit\Framework\MockObject\MockObject */
    private $sprintModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMiddleware = $this->createMock(AuthMiddleware::class);
        $this->userModel = $this->createMock(User::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->taskModel = $this->createMock(Task::class);
        $this->milestoneModel = $this->createMock(Milestone::class);
        $this->sprintModel = $this->createMock(Sprint::class);

        $this->seedSingleton(SettingsService::class, $this->createMock(SettingsService::class));
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(Database::class, null);

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);
        $this->seedSingleton(Database::class, null);
        $_SESSION = [];

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $instance): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $instance);
    }

    private function controller(): DashboardControllerTestable
    {
        return new DashboardControllerTestable(
            $this->authMiddleware,
            $this->userModel,
            $this->projectModel,
            $this->taskModel,
            $this->milestoneModel,
            $this->sprintModel
        );
    }

    private function statementReturning(array $fetchAllValue, mixed $fetchColumnValue): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($fetchAllValue);
        $stmt->method('fetchColumn')->willReturn($fetchColumnValue);

        return $stmt;
    }

    private function authenticatedUser(int $userId = 42): \stdClass
    {
        $_SESSION['user'] = [
            'id' => 1,
            'profile' => ['id' => $userId],
            'roles' => [],
            'permissions' => [],
            'config' => [],
        ];

        $user = new \stdClass();
        $user->id = $userId;
        $user->is_deleted = false;
        $user->name = 'Test User';

        return $user;
    }

    /**
     * Replicates Config::getErrorMessage()'s own branching so assertions hold
     * regardless of whether APP_DEBUG is on for this run.
     */
    private function assertErrorMessage(string $exceptionMessage, string $fallback, ?string $actual): void
    {
        $this->assertNotNull($actual);
        if (Config::isDebug()) {
            $this->assertStringStartsWith('DEBUG: ' . $exceptionMessage, $actual);
        } else {
            $this->assertSame($fallback, $actual);
        }
    }

    public function testNotAuthenticatedRedirectsToLoginWithCriticalError(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(false);

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected redirect exception');
        } catch (RuntimeException $e) {
            $this->assertSame('redirect:' . $c->redirectMessage, $e->getMessage());
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertErrorMessage(
            'Authentication required.',
            'A critical error occurred while loading the dashboard.',
            $c->redirectMessage
        );
    }

    public function testAuthenticatedWithoutSessionProfileIdRedirectsToLogin(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $_SESSION['user'] = ['id' => 1, 'profile' => [], 'roles' => [], 'permissions' => [], 'config' => []];

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected redirect exception');
        } catch (RuntimeException) {
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertErrorMessage(
            'Session expired. Please log in again.',
            'A critical error occurred while loading the dashboard.',
            $c->redirectMessage
        );
    }

    public function testUserNotFoundRedirectsToLogin(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->with(42)->willReturn(null);

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected redirect exception');
        } catch (RuntimeException) {
        }

        $this->assertErrorMessage(
            'User not found. Please try again.',
            'A critical error occurred while loading the dashboard.',
            $c->redirectMessage
        );
    }

    public function testDeletedUserRedirectsToLogin(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $user->is_deleted = true;
        $this->userModel->method('findWithDetails')->with(42)->willReturn($user);

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected redirect exception');
        } catch (RuntimeException) {
        }

        $this->assertErrorMessage(
            'User not found. Please try again.',
            'A critical error occurred while loading the dashboard.',
            $c->redirectMessage
        );
    }

    public function testNoPermissionsRendersDefaultZeroedDashboard(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        // No permissions granted -> every optional data section must stay at its default.
        $_SESSION['user']['permissions'] = [];

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Dashboard/index', $c->renderedView);
        $this->assertSame($user, $c->renderedData['user']);
        $this->assertSame([], $c->renderedData['userPermissions']);

        $data = $c->renderedData['dashboardData'];
        $this->assertSame([], $data['recent_projects']);
        $this->assertSame([], $data['recent_tasks']);
        $this->assertSame([], $data['upcoming_milestones']);
        $this->assertSame(0, $data['task_summary']['total']);
        $this->assertSame(0, $data['time_tracking_summary']['total_hours']);
        $this->assertSame(0, $data['project_summary']['total']);
        $this->assertNull($data['active_timer']);
        $this->assertSame([], $data['active_sprints']);
        $this->assertSame(['story' => 0, 'bug' => 0, 'task' => 0, 'epic' => 0], $data['task_type_distribution']);
        $this->assertSame([], $data['priority_tasks']);
    }

    public function testViewProjectsPermissionPopulatesRecentProjectsAndProjectSummary(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_projects'];

        $projects = [(object) ['id' => 1, 'name' => 'Alpha']];
        $this->projectModel->method('getRecentByUser')->with(42, 5)->willReturn($projects);
        $this->projectModel->method('count')->willReturn(3);

        $c = $this->controller();
        $c->index('GET', []);

        $data = $c->renderedData['dashboardData'];
        $this->assertSame($projects, $data['recent_projects']);
        $this->assertSame(3, $data['project_summary']['total']);
        $this->assertSame(3, $data['project_summary']['in_progress']);
        $this->assertSame(3, $data['project_summary']['completed']);
        $this->assertSame(3, $data['project_summary']['delayed']);
        $this->assertSame(3, $data['project_summary']['on_hold']);
        // Sections gated by a different permission stay at their zeroed default.
        $this->assertSame([], $data['recent_tasks']);
    }

    public function testViewProjectsPermissionExceptionKeepsRecentProjectsEmpty(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_projects'];

        $this->projectModel->method('getRecentByUser')->willThrowException(new RuntimeException('db down'));
        $this->projectModel->method('count')->willReturn(0);

        $c = $this->controller();
        $c->index('GET', []);

        $data = $c->renderedData['dashboardData'];
        $this->assertSame([], $data['recent_projects']);
    }

    public function testViewTasksPermissionPopulatesTaskDataViaDatabase(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_tasks'];

        $recentTasks = [(object) ['id' => 9, 'title' => 'Do thing']];
        $this->taskModel->method('getByUserId')->with(42, 15)->willReturn($recentTasks);
        $this->taskModel->method('count')->willReturn(2);

        $dbMock = $this->createMock(Database::class);
        $dbMock->method('executeQuery')->willReturn(
            $this->statementReturning([['task_type' => 'bug', 'count' => 4]], '7')
        );
        $this->seedSingleton(Database::class, $dbMock);

        $c = $this->controller();
        $c->index('GET', []);

        $data = $c->renderedData['dashboardData'];
        $this->assertSame($recentTasks, $data['recent_tasks']);
        $this->assertSame(['story' => 0, 'bug' => 4, 'task' => 0, 'epic' => 0], $data['task_type_distribution']);
        $this->assertSame(7, $data['story_points_summary']['this_week']);
        $this->assertSame(7, $data['story_points_summary']['total']);
        $this->assertSame(7, $data['story_points_summary']['completed']);
        $this->assertSame(0, $data['story_points_summary']['remaining']);
        // task_summary is populated from the injected Task model mock's count(), not Database.
        $this->assertSame(2, $data['task_summary']['total']);
    }

    public function testViewTasksPermissionDatabaseFailureKeepsRecentTasksButDefaultsExtras(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_tasks'];

        $recentTasks = [(object) ['id' => 9, 'title' => 'Do thing']];
        // getByUserId (before the Database-backed calls) succeeds and is already
        // committed to $dashboardData when the later Database call throws.
        $this->taskModel->method('getByUserId')->willReturn($recentTasks);
        $this->taskModel->method('count')->willReturn(0);

        $dbMock = $this->createMock(Database::class);
        $dbMock->method('executeQuery')->willThrowException(new RuntimeException('query failed'));
        $this->seedSingleton(Database::class, $dbMock);

        $c = $this->controller();
        $c->index('GET', []);

        $data = $c->renderedData['dashboardData'];
        $this->assertSame($recentTasks, $data['recent_tasks']);
        $this->assertSame([], $data['priority_tasks']);
        $this->assertSame(['story' => 0, 'bug' => 0, 'task' => 0, 'epic' => 0], $data['task_type_distribution']);
        $this->assertSame(0, $data['story_points_summary']['total']);
    }

    public function testViewMilestonesPermissionPopulatesUpcomingMilestones(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_milestones'];

        $milestones = [(object) ['id' => 5, 'name' => 'Beta launch']];
        $this->milestoneModel->method('getAllWithProgress')
            ->with(5, 1, ['due_date' => ['>', date('Y-m-d')], 'is_deleted' => 0])
            ->willReturn($milestones);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame($milestones, $c->renderedData['dashboardData']['upcoming_milestones']);
    }

    public function testViewMilestonesPermissionExceptionKeepsEmpty(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_milestones'];

        $this->milestoneModel->method('getAllWithProgress')->willThrowException(new RuntimeException('nope'));

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame([], $c->renderedData['dashboardData']['upcoming_milestones']);
    }

    public function testViewTimeTrackingPopulatesSummaryAndActiveTimerWithTask(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_time_tracking'];
        $_SESSION['active_timer'] = ['task_id' => 5, 'start_time' => time() - 100];

        $this->taskModel->method('getTotalTimeSpent')->willReturn(100);
        $this->taskModel->method('getTotalBillableTime')->willReturn(80);
        $this->taskModel->method('getWeeklyTimeSpent')->willReturn(10);
        $this->taskModel->method('getMonthlyTimeSpent')->willReturn(40);

        $activeTask = new \stdClass();
        $activeTask->id = 5;
        $activeTask->is_deleted = false;
        $activeTask->title = 'Active task';
        $this->taskModel->method('find')->with(5)->willReturn($activeTask);

        $c = $this->controller();
        $c->index('GET', []);

        $data = $c->renderedData['dashboardData'];
        $this->assertSame(100, $data['time_tracking_summary']['total_hours']);
        $this->assertSame(80, $data['time_tracking_summary']['billable_hours']);
        $this->assertSame(10, $data['time_tracking_summary']['this_week']);
        $this->assertSame(40, $data['time_tracking_summary']['this_month']);
        $this->assertSame($activeTask, $data['active_timer']['task']);
        $this->assertGreaterThanOrEqual(100, $data['active_timer']['duration']);
    }

    public function testViewTimeTrackingActiveTimerReferencingDeletedTaskOmitsTaskKey(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_time_tracking'];
        $_SESSION['active_timer'] = ['task_id' => 5, 'start_time' => time() - 100];

        $deletedTask = new \stdClass();
        $deletedTask->is_deleted = true;
        $this->taskModel->method('find')->with(5)->willReturn($deletedTask);

        $c = $this->controller();
        $c->index('GET', []);

        $data = $c->renderedData['dashboardData'];
        $this->assertArrayNotHasKey('task', $data['active_timer']);
        $this->assertSame(5, $data['active_timer']['task_id']);
    }

    public function testViewTimeTrackingNoActiveTimerStaysNull(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_time_tracking'];
        unset($_SESSION['active_timer']);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertNull($c->renderedData['dashboardData']['active_timer']);
    }

    public function testViewSprintsPermissionPopulatesActiveSprints(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_sprints'];

        $sprints = [(object) ['id' => 1, 'name' => 'Sprint 1']];
        $this->sprintModel->method('getProjectSprints')->with(42, 'active')->willReturn($sprints);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame($sprints, $c->renderedData['dashboardData']['active_sprints']);
    }

    public function testViewSprintsPermissionExceptionKeepsEmpty(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_sprints'];

        $this->sprintModel->method('getProjectSprints')->willThrowException(new RuntimeException('nope'));

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame([], $c->renderedData['dashboardData']['active_sprints']);
    }

    public function testTaskSummaryCountsExcludeOverdueFromInProgressAndComputeOpenOther(): void
    {
        $this->authMiddleware->method('isAuthenticated')->willReturn(true);
        $user = $this->authenticatedUser(42);
        $this->userModel->method('findWithDetails')->willReturn($user);
        $_SESSION['user']['permissions'] = ['view_tasks'];

        $this->taskModel->method('getByUserId')->willReturn([]);
        // total=10, completed=2, overdue=1, inProgressTotal=4, inProgressOverdue=1 -> inProgress=3, open_other=4
        $this->taskModel->method('count')->willReturnMap([
            [['assigned_to' => 42, 'is_deleted' => 0], 10],
            [['assigned_to' => 42, 'status_id' => TaskStatus::COMPLETED->value, 'is_deleted' => 0], 2],
            [
                [
                    'assigned_to' => 42,
                    'due_date' => ['<', date('Y-m-d')],
                    'status_id' => ['NOT IN', [TaskStatus::CLOSED->value, TaskStatus::COMPLETED->value]],
                    'is_deleted' => 0,
                ],
                1,
            ],
            [['assigned_to' => 42, 'status_id' => TaskStatus::IN_PROGRESS->value, 'is_deleted' => 0], 4],
            [
                [
                    'assigned_to' => 42,
                    'status_id' => TaskStatus::IN_PROGRESS->value,
                    'due_date' => ['<', date('Y-m-d')],
                    'is_deleted' => 0,
                ],
                1,
            ],
            [['assigned_to' => 42, 'is_ready_for_sprint' => 1, 'is_deleted' => 0], 6],
            [
                [
                    'assigned_to' => 42,
                    'task_type' => 'bug',
                    'status_id' => ['NOT IN', [5, 6]],
                    'is_deleted' => 0,
                ],
                3,
            ],
        ]);

        $dbMock = $this->createMock(Database::class);
        $dbMock->method('executeQuery')->willReturn($this->statementReturning([], 0));
        $this->seedSingleton(Database::class, $dbMock);

        $c = $this->controller();
        $c->index('GET', []);

        $summary = $c->renderedData['dashboardData']['task_summary'];
        $this->assertSame(10, $summary['total']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(1, $summary['overdue']);
        $this->assertSame(3, $summary['in_progress']);
        $this->assertSame(4, $summary['open_other']);
        $this->assertSame(6, $summary['sprint_ready']);
        $this->assertSame(3, $summary['bugs']);
    }
}
