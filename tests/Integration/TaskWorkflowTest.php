<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BaseModel;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Services\TaskService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\DomainFixtures;
use Tests\Support\TestCase;

/**
 * Integration tests for the task lifecycle against a real database — the first
 * half of audit finding M1, which recorded that nothing exercised the task,
 * sprint or time-tracking flows end to end.
 *
 * Running these against a real driver rather than a mocked one matters for more
 * than persistence. TaskService compares $task->assigned_to !== $userId with
 * strict types and calls TaskStatus::tryFrom($task->status_id) on an int-backed
 * enum under strict_types=1. Both depend on PDO returning integers for INT
 * columns, which a hand-built stdClass in a unit test always gets right and a
 * real connection is entitled not to.
 *
 * Timers are deliberately absent. TaskService::startTimer()/stopTimer() wrote
 * a timer_start column that exists in no table and had no callers; they were
 * removed, and the working implementation — session-backed, writing
 * time_entries and billable_time — lives in TimeTrackingController, which the
 * /tasks/start-timer and /tasks/stop-timer routes now reach.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(TaskService::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(Task::class)]
#[UsesClass(User::class)]
#[UsesClass(TaskStatus::class)]
#[UsesClass(BusinessRuleException::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class TaskWorkflowTest extends TestCase
{
    use DomainFixtures;

    /** The migration seeds exactly one user, the administrator. */
    private const USER_ID = 1;

    private int $projectId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->projectId = $this->createProject($this->createCompany());
    }

    protected function tearDown(): void
    {
        $this->removeFixtures();

        parent::tearDown();
    }

    private function service(): TaskService
    {
        return new TaskService();
    }

    private function reload(int $taskId): \stdClass
    {
        return $this->db->executeQuery(
            'SELECT * FROM tasks WHERE id = :id',
            [':id' => $taskId]
        )->fetch(\PDO::FETCH_OBJ);
    }

    // ---- assignment ------------------------------------------------------

    public function testAssigningATaskPersistsTheAssignee(): void
    {
        $taskId = $this->createTask($this->projectId);

        $this->service()->assignTask($taskId, self::USER_ID);

        $this->assertSame(self::USER_ID, (int) $this->reload($taskId)->assigned_to);
    }

    /**
     * Proves the column comes back as an int. startTimer() guards with
     * `$task->assigned_to !== $userId`, a strict comparison — were the driver to
     * hand back "1", the guard would reject the very user the task is assigned
     * to, and no unit test building its own stdClass could show it.
     */
    public function testTheAssigneeIsReadBackAsAnInteger(): void
    {
        $taskId = $this->createTask($this->projectId, ['assigned_to' => self::USER_ID]);

        $this->assertIsInt($this->reload($taskId)->assigned_to);
        $this->assertIsInt($this->reload($taskId)->status_id);
    }

    public function testUnassigningATaskClearsTheAssignee(): void
    {
        $taskId = $this->createTask($this->projectId, ['assigned_to' => self::USER_ID]);

        $this->service()->unassignTask($taskId);

        $this->assertNull($this->reload($taskId)->assigned_to);
    }

    // ---- status transitions ----------------------------------------------

    public function testAPermittedTransitionIsPersisted(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::OPEN->value]);

        $this->service()->transitionStatus($taskId, TaskStatus::IN_PROGRESS);

        $this->assertSame(TaskStatus::IN_PROGRESS->value, (int) $this->reload($taskId)->status_id);
    }

    /**
     * The state machine is the whole point of transitionStatus(). A task cannot
     * jump from open to completed without passing through work in progress.
     */
    public function testAForbiddenTransitionIsRefusedAndChangesNothing(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::OPEN->value]);

        try {
            $this->service()->transitionStatus($taskId, TaskStatus::COMPLETED);
            $this->fail('Open to completed must not be permitted.');
        } catch (BusinessRuleException) {
            // expected
        }

        $this->assertSame(
            TaskStatus::OPEN->value,
            (int) $this->reload($taskId)->status_id,
            'A refused transition must leave the row untouched.'
        );
    }

    public function testAClosedTaskCanBeReopened(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::CLOSED->value]);

        $this->service()->transitionStatus($taskId, TaskStatus::OPEN);

        $this->assertSame(TaskStatus::OPEN->value, (int) $this->reload($taskId)->status_id);
    }

    public function testCompletingATaskStampsTheCompletionDate(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::IN_PROGRESS->value]);

        $this->service()->transitionStatus($taskId, TaskStatus::COMPLETED);

        $row = $this->reload($taskId);

        $this->assertSame(TaskStatus::COMPLETED->value, (int) $row->status_id);
        $this->assertSame(date('Y-m-d'), $row->complete_date);
    }

    /**
     * complete_date is a DATE column, so a datetime string would be truncated
     * by the server rather than rejected — the assertion above is on the exact
     * stored value for that reason.
     */
    public function testCompleteTaskStampsTheCompletionDate(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::IN_PROGRESS->value]);

        $this->service()->completeTask($taskId);

        $row = $this->reload($taskId);

        $this->assertSame(TaskStatus::COMPLETED->value, (int) $row->status_id);
        $this->assertSame(date('Y-m-d'), $row->complete_date);
    }

    public function testAnAlreadyCompletedTaskCannotBeCompletedAgain(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::COMPLETED->value]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('already completed');

        $this->service()->completeTask($taskId);
    }

    // ---- estimates -------------------------------------------------------

    public function testUpdatingTheEstimatePersists(): void
    {
        $taskId = $this->createTask($this->projectId);

        $this->service()->updateEstimate($taskId, 7200);

        $this->assertSame(7200, (int) $this->reload($taskId)->estimated_time);
    }
}
