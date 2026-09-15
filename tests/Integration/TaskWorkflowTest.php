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

    /**
     * PINS A DEFECT (audit H10). Transitioning to completed also writes
     * completed_at, and `tasks` has no such column — the schema calls it
     * complete_date. BaseModel::prepareSaveData() removes only $guarded keys,
     * NOT everything outside $fillable, so the unknown field reaches the SQL
     * and the statement fails. A task can therefore never be completed through
     * TaskService. Rewrite this as the positive case when H10 is fixed.
     */
    public function testCompletingATaskFailsBecauseTheColumnDoesNotExist(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::IN_PROGRESS->value]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error updating task');

        $this->service()->transitionStatus($taskId, TaskStatus::COMPLETED);
    }

    public function testCompleteTaskFailsForTheSameReason(): void
    {
        $taskId = $this->createTask($this->projectId, ['status_id' => TaskStatus::IN_PROGRESS->value]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error updating task');

        $this->service()->completeTask($taskId);
    }

    // ---- estimates -------------------------------------------------------

    public function testUpdatingTheEstimatePersists(): void
    {
        $taskId = $this->createTask($this->projectId);

        $this->service()->updateEstimate($taskId, 7200);

        $this->assertSame(7200, (int) $this->reload($taskId)->estimated_time);
    }

    // ---- timers ----------------------------------------------------------

    /**
     * PINS A DEFECT (audit H10). `tasks` has no timer_start column at all —
     * the schema carries complete_date and time_spent and nothing else
     * time-related. Because prepareSaveData() filters only $guarded, the
     * unknown field reaches the SQL and the statement fails, so starting a
     * timer throws rather than quietly doing nothing.
     *
     * The same write happens in TaskController::startTimer(), which IS routed
     * (POST /tasks/start-timer/:task_id) and IS wired to the Start buttons on
     * the dashboard. There it is caught and returned as HTTP 500, so the
     * dashboard timer is broken for every user on every click.
     */
    public function testStartingATimerFailsBecauseTheColumnDoesNotExist(): void
    {
        $taskId = $this->createTask($this->projectId, [
            'assigned_to' => self::USER_ID,
            'status_id' => TaskStatus::OPEN->value,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error updating task');

        $this->service()->startTimer($taskId, self::USER_ID);
    }

    /**
     * Nothing can ever have been stored, so stopping always reports that no
     * timer is running — the guard fires before the broken write is reached.
     */
    public function testStoppingATimerAlwaysReportsNoTimerRunning(): void
    {
        $taskId = $this->createTask($this->projectId, ['assigned_to' => self::USER_ID]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('No timer running for this task');

        $this->service()->stopTimer($taskId, self::USER_ID);
    }

    /**
     * The ownership guard runs before the broken write, so it is real and worth
     * holding: a user may not start a timer on somebody else's task.
     */
    public function testATimerCannotBeStartedOnSomeoneElsesTask(): void
    {
        $taskId = $this->createTask($this->projectId, ['assigned_to' => self::USER_ID]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('not assigned to you');

        $this->service()->startTimer($taskId, self::USER_ID + 99);
    }

    public function testATimerCannotBeStartedOnACompletedTask(): void
    {
        $taskId = $this->createTask($this->projectId, [
            'assigned_to' => self::USER_ID,
            'status_id' => TaskStatus::COMPLETED->value,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot track time on completed tasks');

        $this->service()->startTimer($taskId, self::USER_ID);
    }
}
