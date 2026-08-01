<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\Database;
use App\Enums\Priority;
use App\Enums\TaskType;
use App\Models\BaseModel;
use App\Models\Concerns\Searchable;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Models\Task;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Time;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Behavioural tests for the Task model: detail loading, hierarchy/subtask
 * handling, filtering/sorting query construction, time/estimate metrics,
 * history tracking on create/update, and the search-index row projection.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * SettingsService's process-wide static cache is pre-seeded to an empty
 * array so Time::formatSeconds()'s settings lookups resolve deterministically
 * to the "minutes" default without touching the Setting model or Database.
 *
 * create()/update() invoke BaseModel's afterSave() hook via the Searchable
 * trait, which calls Task::find() again internally. Every write-path test
 * therefore programs the Database mock's find() query to return "not found"
 * (a blank statement) so toSearchIndexRow() short-circuits to null and the
 * SearchIndex model is never actually touched — keeping those tests focused
 * on Task's own history-tracking behaviour.
 */
#[CoversClass(Task::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Priority::class)]
#[UsesClass(Searchable::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(TaskType::class)]
#[UsesClass(Time::class)]
final class TaskTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
        $this->seedSecurityService(null);
        $this->seedSettingsCache([]);
        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);
        $this->seedSecurityService(null);
        $this->seedSettingsCache(null);
        unset($_SESSION['user']);

        parent::tearDown();
    }

    // --------------------------------------------------------------- helpers

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function seedSecurityService(?SecurityService $service): void
    {
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, $service);
    }

    private function seedSettingsCache(?array $cache): void
    {
        (new ReflectionClass(SettingsService::class))->getProperty('cache')->setValue(null, $cache);
    }

    private function newTaskWithDb(Database $db): Task
    {
        $this->seedDatabase($db);

        return new Task();
    }

    private function statement(array $methodReturns): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);

        foreach ($methodReturns as $method => $value) {
            $stmt->method($method)->willReturn($value);
        }

        return $stmt;
    }

    private function blankStatement(): PDOStatement
    {
        return $this->statement(['fetch' => false, 'fetchAll' => [], 'fetchColumn' => 0]);
    }

    /**
     * Database mock whose executeQuery() replays $steps in order (each either
     * a PDOStatement to return or a Throwable to throw), repeating the last
     * step for any calls beyond the list.
     */
    private function dbSequenced(array $steps): Database
    {
        $db = $this->createMock(Database::class);
        $call = 0;
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$call, $steps) {
                $step = $steps[$call] ?? end($steps);
                $call++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        return $db;
    }

    /** Database mock capturing every executeQuery() call while always returning $stmt. */
    private function capturingQueryDb(array &$calls, PDOStatement $stmt): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$calls, $stmt): PDOStatement {
                $calls[] = ['sql' => $sql, 'params' => $params];

                return $stmt;
            }
        );

        return $db;
    }

    /**
     * Database mock for write-path tests: executeQuery() replays $queryReturns
     * (falling back to a blank/not-found statement), executeInsertUpdate()
     * captures every call into $insertCalls while returning $insertSuccess.
     */
    private function makeDb(array $queryReturns, array &$insertCalls, bool $insertSuccess = true, int $lastInsertId = 1): Database
    {
        $db = $this->createMock(Database::class);

        $call = 0;
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$call, $queryReturns) {
                if ($queryReturns === []) {
                    return $this->blankStatement();
                }

                $step = $queryReturns[$call] ?? end($queryReturns);
                $call++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$insertCalls, $insertSuccess): bool {
                $insertCalls[] = ['sql' => $sql, 'params' => $params];

                return $insertSuccess;
            }
        );

        $db->method('lastInsertId')->willReturn($lastInsertId);

        return $db;
    }

    private function taskFixture(array $overrides = []): object
    {
        return (object) array_merge([
            'due_date' => null,
            'start_date' => null,
            'estimated_time' => 0,
            'time_spent' => 0,
            'status_id' => 1,
            'priority' => 'none',
            'is_subtask' => false,
            'milestones' => [],
        ], $overrides);
    }

    private function invokePrivate(Task $task, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod(Task::class, $method))->invoke($task, ...$args);
    }

    // ---------------------------------------------------------- findWithDetails()

    public function testFindWithDetailsReturnsTaskWithRelatedDataAndMetrics(): void
    {
        $row = (object) [
            'id' => 1,
            'title' => 'Task A',
            'priority' => 'high',
            'status_id' => 2,
            'estimated_time' => 7200,
            'time_spent' => 3600,
            'billable_time' => 1800,
            'due_date' => null,
            'start_date' => null,
            'is_subtask' => false,
        ];

        $timeEntry = (object) ['id' => 1, 'duration' => 300];

        $db = $this->dbSequenced([
            $this->statement(['fetch' => $row]),
            $this->statement(['fetchAll' => []]),
            $this->statement(['fetchAll' => [$timeEntry]]),
            $this->statement(['fetchAll' => []]),
            $this->statement(['fetchAll' => []]),
            $this->statement(['fetchAll' => []]),
        ]);
        $task = $this->newTaskWithDb($db);

        $result = $task->findWithDetails(1);

        $this->assertSame([], $result->subtasks);
        $this->assertSame('5m', $result->time_entries[0]->formatted_duration);
        $this->assertSame([], $result->comments);
        $this->assertSame([], $result->milestones);
        $this->assertSame([], $result->history);

        $this->assertSame(3600, $result->metrics['time_remaining']);
        $this->assertSame(50.0, $result->metrics['progress_percentage']);
        $this->assertFalse($result->metrics['is_overdue']);
        $this->assertSame('High', $result->metrics['complexity']);

        $this->assertSame('120m', $result->formatted_estimated_time);
        $this->assertSame('60m', $result->formatted_time_spent);
        $this->assertSame('30m', $result->formatted_billable_time);
    }

    public function testFindWithDetailsReturnsNullWhenNotFound(): void
    {
        $db = $this->dbSequenced([$this->blankStatement()]);
        $task = $this->newTaskWithDb($db);

        $this->assertNull($task->findWithDetails(999));
    }

    public function testFindWithDetailsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching task details: boom');

        $task->findWithDetails(1);
    }

    // ------------------------------------------------------ buildOrderByClause()

    public function testBuildOrderByClauseMapsKnownFieldAscending(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['title', 'asc']);

        $this->assertStringNotContainsString('ORDER BY', $clause);
        $this->assertStringContainsString('COALESCE(t.parent_task_id, t.id)', $clause);
        $this->assertStringContainsString('t.title ASC', $clause);
    }

    public function testBuildOrderByClauseNormalizesInvalidDirectionToAsc(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['title', 'sideways']);

        $this->assertStringContainsString('t.title ASC', $clause);
    }

    public function testBuildOrderByClauseUppercasesDescDirection(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['time_spent', 'desc']);

        $this->assertStringContainsString('t.time_spent DESC', $clause);
    }

    public function testBuildOrderByClauseUnmappedFieldFallsBackToDueDateColumn(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['nonexistent', 'asc']);

        $this->assertStringContainsString('t.due_date ASC', $clause);
        $this->assertStringNotContainsString('CASE WHEN t.due_date IS NULL', $clause);
    }

    public function testBuildOrderByClauseDueDateFieldPutsNullsLast(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['due_date', 'asc']);

        $this->assertStringContainsString(
            'CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC',
            $clause
        );
    }

    public function testBuildOrderByClausePriorityFieldUsesSortOrderCase(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['priority', 'desc']);

        $this->assertStringContainsString("WHEN t.priority = 'high' THEN 3", $clause);
        $this->assertStringContainsString("WHEN t.priority = 'medium' THEN 2", $clause);
        $this->assertStringContainsString("WHEN t.priority = 'low' THEN 1", $clause);
        $this->assertStringContainsString('ELSE 0', $clause);
        $this->assertStringEndsWith('DESC', trim($clause));
    }

    public function testBuildOrderByClauseAssignedToMapsToConcatExpression(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $clause = $this->invokePrivate($task, 'buildOrderByClause', ['assigned_to', 'asc']);

        $this->assertStringContainsString('CONCAT(u.first_name, " ", u.last_name) ASC', $clause);
    }

    // ------------------------------------------------- getAllWithDetails() family

    public function testGetAllWithDetailsReturnsFormattedTasks(): void
    {
        $rows = [(object) ['id' => 1, 'estimated_time' => 3600, 'time_spent' => 1800, 'billable_time' => null]];
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => $rows]));
        $task = $this->newTaskWithDb($db);

        $result = $task->getAllWithDetails(5, 2, 'title', 'desc');

        $this->assertCount(1, $result);
        $this->assertSame('30m', $result[0]->formatted_time_spent);
        $this->assertSame(5, $calls[0]['params'][':limit']);
        $this->assertSame(5, $calls[0]['params'][':offset']);
        $this->assertStringContainsString('p.is_deleted = 0', $calls[0]['sql']);
    }

    public function testGetAllWithDetailsReturnsNullWhenNoTasksFound(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $this->assertNull($task->getAllWithDetails());
    }

    public function testGetAllWithDetailsNoSubtasksExcludesSubtasksFilter(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $result = $task->getAllWithDetailsNoSubtasks();

        $this->assertSame([], $result);
        $this->assertStringContainsString('t.is_subtask = :where_0', $calls[0]['sql']);
        $this->assertSame(0, $calls[0]['params'][':where_0']);
    }

    public function testGetByUserIdFiltersByAssignedTo(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $task->getByUserId(7);

        $this->assertStringContainsString('t.assigned_to = :where_0', $calls[0]['sql']);
        $this->assertSame(7, $calls[0]['params'][':where_0']);
    }

    public function testGetUnassignedTasksFiltersNullOrZeroAssignee(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $task->getUnassignedTasks();

        $this->assertStringContainsString('(t.assigned_to IS NULL OR t.assigned_to = 0)', $calls[0]['sql']);
    }

    /**
     * project_id/status_id filters are supported by the private
     * getTasksWithDetails() but no current public wrapper (getAllWithDetails,
     * getAllWithDetailsNoSubtasks, getByUserId, getUnassignedTasks) ever passes
     * them — invoked directly via reflection to exercise those branches.
     */
    public function testGetTasksWithDetailsAppliesProjectIdAndStatusIdFilters(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $this->invokePrivate($task, 'getTasksWithDetails', [['project_id' => 5, 'status_id' => 2]]);

        $sql = $calls[0]['sql'];
        $this->assertStringContainsString('t.project_id = :where_0', $sql);
        $this->assertSame(5, $calls[0]['params'][':where_0']);
        $this->assertStringContainsString('t.status_id = :where_1', $sql);
        $this->assertSame(2, $calls[0]['params'][':where_1']);
    }

    // ----------------------------------------------------------- getByProjectId()

    public function testGetByProjectIdOrganizesTasksByNormalizedStatusKey(): void
    {
        $statuses = [
            (object) ['id' => 1, 'name' => 'Open'],
            (object) ['id' => 2, 'name' => 'In Progress'],
        ];
        $tasks = [
            (object) ['id' => 10, 'status_id' => 1],
            (object) ['id' => 11, 'status_id' => 2],
            (object) ['id' => 12, 'status_id' => 999],
        ];

        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => $tasks]),
            $this->statement(['fetchAll' => $statuses]),
        ]);
        $task = $this->newTaskWithDb($db);

        $result = $task->getByProjectId(4);

        $this->assertCount(1, $result['open']);
        $this->assertCount(1, $result['in_progress']);
        $this->assertCount(1, $result['other']);
        $this->assertSame([], $result['completed']);
    }

    public function testGetByProjectIdEnsuresDefaultStatusBucketsExistWhenMissing(): void
    {
        $statuses = [(object) ['id' => 1, 'name' => 'Closed']];
        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => []]),
            $this->statement(['fetchAll' => $statuses]),
        ]);
        $task = $this->newTaskWithDb($db);

        $result = $task->getByProjectId(1);

        $this->assertSame([], $result['open']);
        $this->assertSame([], $result['in_progress']);
        $this->assertSame([], $result['completed']);
        $this->assertArrayHasKey('closed', $result);
    }

    public function testGetByProjectIdWrapsExceptionWhenOrganizingFails(): void
    {
        $tasks = [(object) ['id' => 1, 'status_id' => 1]];
        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => $tasks]),
            new RuntimeException('status lookup failed'),
        ]);
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Error organizing tasks by status: Error fetching task statuses: status lookup failed'
        );

        $task->getByProjectId(1);
    }

    // -------------------------------------------------------- getSubtasks() etc.

    public function testGetSubtasksFormatsTimeFields(): void
    {
        $rows = [(object) ['id' => 1, 'time_spent' => 600, 'estimated_time' => 1200]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $subtasks = $task->getSubtasks(1);

        $this->assertSame('10m', $subtasks[0]->formatted_time_spent);
        $this->assertSame('20m', $subtasks[0]->formatted_estimated_time);
    }

    public function testGetSubtasksWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching subtasks: boom');

        $task->getSubtasks(1);
    }

    public function testGetSubtaskCountReturnsInteger(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => '4'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(4, $task->getSubtaskCount(1));
    }

    public function testGetSubtaskCountReturnsZeroOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->assertSame(0, $task->getSubtaskCount(1));
    }

    // ------------------------------------------------------------ getTaskStatuses()

    public function testGetTaskStatusesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'name' => 'Open']];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame($rows, $task->getTaskStatuses());
    }

    public function testGetTaskStatusesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching task statuses: boom');

        $task->getTaskStatuses();
    }

    // ------------------------------------------------------------ getRecentByUser()

    public function testGetRecentByUserPassesUserIdAndLimit(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $task->getRecentByUser(9, 3);

        $this->assertSame(9, $calls[0]['params'][':user_id']);
        $this->assertSame(3, $calls[0]['params'][':limit']);
    }

    public function testGetRecentByUserWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching recent tasks: boom');

        $task->getRecentByUser(9);
    }

    // ------------------------------------------------------------ getProductBacklog()

    public function testGetProductBacklogDefaultOrderingWithoutProjectFilter(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $task->getProductBacklog(5, 2);

        $sql = $calls[0]['sql'];
        $this->assertStringNotContainsString('t.project_id = :project_id', $sql);
        $this->assertStringContainsString('t.backlog_priority ASC', $sql);
        $this->assertSame(5, $calls[0]['params'][':offset']);
        $this->assertSame(5, $calls[0]['params'][':limit']);
    }

    public function testGetProductBacklogFiltersByProjectId(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $task->getProductBacklog(10, 1, 3);

        $this->assertStringContainsString('t.project_id = :project_id', $calls[0]['sql']);
        $this->assertSame(3, $calls[0]['params'][':project_id']);
    }

    public function testGetProductBacklogUsesBuildOrderByClauseForNonDefaultSortField(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $task = $this->newTaskWithDb($db);

        $task->getProductBacklog(10, 1, null, 'title', 'asc');

        $this->assertStringContainsString('ORDER BY COALESCE(t.parent_task_id, t.id)', $calls[0]['sql']);
    }

    public function testGetProductBacklogWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching product backlog: boom');

        $task->getProductBacklog();
    }

    // ----------------------------------------------------------- countProductBacklog()

    public function testCountProductBacklogWithoutProjectFilter(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchColumn' => '7']));
        $task = $this->newTaskWithDb($db);

        $this->assertSame(7, $task->countProductBacklog());
        $this->assertArrayNotHasKey(':project_id', $calls[0]['params']);
    }

    public function testCountProductBacklogWithProjectFilter(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchColumn' => '2']));
        $task = $this->newTaskWithDb($db);

        $this->assertSame(2, $task->countProductBacklog(5));
        $this->assertSame(5, $calls[0]['params'][':project_id']);
    }

    public function testCountProductBacklogWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error counting product backlog: boom');

        $task->countProductBacklog();
    }

    // ----------------------------------------------------------- countUnassignedTasks()

    public function testCountUnassignedTasksReturnsCount(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => '3'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(3, $task->countUnassignedTasks());
    }

    public function testCountUnassignedTasksWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error counting unassigned tasks: boom');

        $task->countUnassignedTasks();
    }

    // ------------------------------------------------------------ getAvailableForSprint()

    public function testGetAvailableForSprintReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame($rows, $task->getAvailableForSprint(3));
    }

    public function testGetAvailableForSprintWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching tasks available for sprint: boom');

        $task->getAvailableForSprint(3);
    }

    // -------------------------------------------------------------- getTaskTimeEntries()

    public function testGetTaskTimeEntriesFormatsDuration(): void
    {
        $rows = [(object) ['id' => 1, 'duration' => 300]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $entries = $task->getTaskTimeEntries(1);

        $this->assertSame('5m', $entries[0]->formatted_duration);
    }

    public function testGetTaskTimeEntriesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching time entries: boom');

        $task->getTaskTimeEntries(1);
    }

    // --------------------------------------------------------------------- createTimeEntry()

    public function testCreateTimeEntrySuccessReturnsNewId(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, true, 77);
        $task = $this->newTaskWithDb($db);

        $id = $task->createTimeEntry(['task_id' => 1, 'user_id' => 2, 'duration' => 60]);

        $this->assertSame(77, $id);
        $this->assertStringStartsWith('INSERT INTO time_entries', $insertCalls[0]['sql']);
    }

    /**
     * createTimeEntry()'s catch block builds a SecurityService-sanitized message
     * inside its own inner try, but that throw is immediately caught by the very
     * next catch(\Exception) — so the sanitized message is always discarded and
     * the generic fallback is what actually propagates, mirroring the documented
     * quirk in BaseModel::update().
     */
    public function testCreateTimeEntryAlwaysFallsBackToGenericMessageOnFailure(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, false);
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('this message is unreachable');
        $this->seedSecurityService($security);

        $task = new Task();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error creating time entry');

        $task->createTimeEntry(['task_id' => 1, 'user_id' => 2, 'duration' => 60]);
    }

    // ------------------------------------------------------------------------- addComment()

    public function testAddCommentSuccessReturnsNewId(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, true, 5);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(5, $task->addComment(1, 2, 'hello'));
        $this->assertSame('hello', $insertCalls[0]['params'][':content']);
    }

    public function testAddCommentThrowsWrappedExceptionOnFailure(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, false);
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error adding comment: Failed to add comment');

        $task->addComment(1, 2, 'hello');
    }

    // --------------------------------------------------------------------- addHistoryEntry()

    public function testAddHistoryEntrySuccessReturnsNewId(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, true, 9);
        $task = $this->newTaskWithDb($db);

        $id = $task->addHistoryEntry(1, 2, 'updated', 'Title', 'Old', 'New');

        $this->assertSame(9, $id);
        $this->assertSame('Title', $insertCalls[0]['params'][':field_changed']);
    }

    public function testAddHistoryEntryThrowsWrappedExceptionOnFailure(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, false);
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error adding history entry: Failed to add history entry');

        $task->addHistoryEntry(1, 2, 'updated');
    }

    public function testAddHistoryEntryWrapsUnderlyingException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('db gone'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error adding history entry: db gone');

        $task->addHistoryEntry(1, 2, 'updated');
    }

    // ------------------------------------------------------------------------ getTaskHistory()

    public function testGetTaskHistoryReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'action' => 'created']];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame($rows, $task->getTaskHistory(1));
    }

    public function testGetTaskHistoryWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching task history: boom');

        $task->getTaskHistory(1);
    }

    // ------------------------------------------------------------------------------- update()

    public function testUpdateTracksFieldChangesAndAddsHistoryEntries(): void
    {
        $currentTask = (object) [
            'id' => 5,
            'title' => 'Old Title',
            'priority' => 'low',
            'is_hourly' => 0,
            'estimated_time' => 3600,
        ];

        $insertCalls = [];
        $db = $this->makeDb(
            [$this->statement(['fetch' => $currentTask]), $this->blankStatement()],
            $insertCalls
        );
        $task = $this->newTaskWithDb($db);

        $result = $task->update(5, [
            'title' => 'New Title',
            'priority' => 'high',
            'is_hourly' => 1,
            'estimated_time' => 7200,
        ], 9);

        $this->assertTrue($result);
        $this->assertCount(5, $insertCalls);

        $this->assertSame('Title', $insertCalls[0]['params'][':field_changed']);
        $this->assertSame('Old Title', $insertCalls[0]['params'][':old_value']);
        $this->assertSame('New Title', $insertCalls[0]['params'][':new_value']);

        $this->assertSame('Priority', $insertCalls[1]['params'][':field_changed']);
        $this->assertSame('Low', $insertCalls[1]['params'][':old_value']);
        $this->assertSame('High', $insertCalls[1]['params'][':new_value']);

        $this->assertSame('Billable', $insertCalls[2]['params'][':field_changed']);
        $this->assertSame('No', $insertCalls[2]['params'][':old_value']);
        $this->assertSame('Yes', $insertCalls[2]['params'][':new_value']);

        $this->assertSame('Estimated Time', $insertCalls[3]['params'][':field_changed']);
        $this->assertSame('60m', $insertCalls[3]['params'][':old_value']);
        $this->assertSame('120m', $insertCalls[3]['params'][':new_value']);

        $updateCall = $insertCalls[4];
        $this->assertStringStartsWith('UPDATE tasks SET', $updateCall['sql']);
    }

    public function testUpdateUsesSessionUserIdWhenNotProvided(): void
    {
        $_SESSION['user']['id'] = 42;
        $currentTask = (object) ['id' => 6, 'title' => 'A'];
        $insertCalls = [];
        $db = $this->makeDb(
            [$this->statement(['fetch' => $currentTask]), $this->blankStatement()],
            $insertCalls
        );
        $task = $this->newTaskWithDb($db);

        $task->update(6, ['title' => 'B']);

        $this->assertSame(42, $insertCalls[0]['params'][':user_id']);
    }

    public function testUpdateThrowsWhenTaskNotFound(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([$this->blankStatement()], $insertCalls);
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error updating task: Task not found for update');

        $task->update(999, ['title' => 'x'], 1);
    }

    public function testUpdateWrapsExceptionFromParentUpdate(): void
    {
        $currentTask = (object) ['id' => 7, 'title' => 'Old'];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($this->statement(['fetch' => $currentTask]));
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('constraint failed'));
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('ignored safe message');
        $this->seedSecurityService($security);

        $task = new Task();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error updating task: Failed to update tasks record');

        $task->update(7, ['title' => 'New'], null);
    }

    // ------------------------------------------------------------------------------- create()

    public function testCreateAddsHistoryEntryAndReturnsNewId(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([$this->blankStatement()], $insertCalls, true, 42);
        $task = $this->newTaskWithDb($db);

        $newId = $task->create(['title' => 'New Task', 'project_id' => 1, 'status_id' => 1], 9);

        $this->assertSame(42, $newId);
        $this->assertCount(2, $insertCalls);
        $this->assertStringStartsWith('INSERT INTO tasks', $insertCalls[0]['sql']);
        $this->assertStringStartsWith('INSERT INTO task_history', $insertCalls[1]['sql']);
        $this->assertSame('created', $insertCalls[1]['params'][':action']);
    }

    public function testCreateSkipsHistoryEntryWhenNoUserId(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([$this->blankStatement()], $insertCalls, true, 5);
        $task = $this->newTaskWithDb($db);

        $newId = $task->create(['title' => 'X', 'project_id' => 1, 'status_id' => 1]);

        $this->assertSame(5, $newId);
        $this->assertCount(1, $insertCalls);
    }

    /**
     * Regression: parent::create() returns false when the INSERT fails, and
     * Task::create() declares `: int`. Returning that false raised a \TypeError,
     * which extends Error rather than Exception, so it slipped past this method's
     * own catch(\Exception) and propagated uncaught to the top-level handler —
     * surfacing as a 500 instead of the handled "Error creating task" failure the
     * method promises everywhere else. A failed insert now raises that
     * RuntimeException.
     */
    public function testCreateRaisesRuntimeExceptionWhenUnderlyingInsertFails(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, false);
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error creating task: the insert did not return an id');

        $task->create(['title' => 'X', 'project_id' => 1, 'status_id' => 1], 9);
    }

    public function testCreateWrapsExceptionWithSecuritySafeMessage(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('insert failed'));
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('safe insert error');
        $this->seedSecurityService($security);

        $task = new Task();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error creating task: safe insert error');

        $task->create(['title' => 'X'], 9);
    }

    // -------------------------------------------------------------- addTimer*History()

    public function testAddTimerStartHistoryRecordsEntry(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, true, 1);
        $task = $this->newTaskWithDb($db);

        $task->addTimerStartHistory(3, 4);

        $this->assertSame('timer_started', $insertCalls[0]['params'][':action']);
        $this->assertStringContainsString('Timer started at', $insertCalls[0]['params'][':new_value']);
    }

    public function testAddTimerStopHistoryRecordsEntryWithFormattedDuration(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, true, 1);
        $task = $this->newTaskWithDb($db);

        $task->addTimerStopHistory(3, 4, 120);

        $this->assertSame('timer_stopped', $insertCalls[0]['params'][':action']);
        $this->assertStringContainsString('Duration: 2m', $insertCalls[0]['params'][':new_value']);
    }

    // ------------------------------------------------------------------------ getTaskComments()

    public function testGetTaskCommentsReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'content' => 'hi']];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame($rows, $task->getTaskComments(1));
    }

    public function testGetTaskCommentsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching task comments: boom');

        $task->getTaskComments(1);
    }

    // ---------------------------------------------------------------------- getTaskMilestones()

    public function testGetTaskMilestonesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'title' => 'M1']];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame($rows, $task->getTaskMilestones(1));
    }

    public function testGetTaskMilestonesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching task milestones: boom');

        $task->getTaskMilestones(1);
    }

    // -------------------------------------------------------- aggregate time-spent getters

    public function testGetTotalTimeSpentReturnsSum(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => '120'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(120, $task->getTotalTimeSpent(1));
    }

    public function testGetTotalTimeSpentWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error calculating total time spent: boom');

        $task->getTotalTimeSpent(1);
    }

    public function testGetTotalBillableTimeReturnsSum(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => '60'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(60, $task->getTotalBillableTime(1));
    }

    public function testGetTotalBillableTimeWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error calculating total billable time: boom');

        $task->getTotalBillableTime(1);
    }

    public function testGetWeeklyTimeSpentReturnsSum(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => '30'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(30, $task->getWeeklyTimeSpent(1));
    }

    public function testGetWeeklyTimeSpentWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error calculating weekly time spent: boom');

        $task->getWeeklyTimeSpent(1);
    }

    public function testGetMonthlyTimeSpentReturnsSum(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => '90'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame(90, $task->getMonthlyTimeSpent(1));
    }

    public function testGetMonthlyTimeSpentWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $task = $this->newTaskWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error calculating monthly time spent: boom');

        $task->getMonthlyTimeSpent(1);
    }

    // ----------------------------------------------------------- calculateTaskMetrics()

    public function testCalculateTaskMetricsHandlesZeroEstimateWithoutDivisionByZero(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture()]);

        $this->assertSame(0, $metrics['time_remaining']);
        $this->assertSame(0, $metrics['progress_percentage']);
        $this->assertFalse($metrics['is_overdue']);
        $this->assertNull($metrics['days_until_due']);
        $this->assertSame(0, $metrics['days_in_progress']);
        $this->assertSame('Very Low', $metrics['complexity']);
    }

    public function testCalculateTaskMetricsClipsTimeRemainingToZeroWhenOverSpent(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture([
            'estimated_time' => 100,
            'time_spent' => 500,
        ])]);

        $this->assertSame(0, $metrics['time_remaining']);
        $this->assertSame(500.0, $metrics['progress_percentage']);
    }

    public function testCalculateTaskMetricsDetectsOverdueTask(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture([
            'due_date' => '2000-01-01',
            'status_id' => 1,
        ])]);

        $this->assertTrue($metrics['is_overdue']);
        $this->assertIsInt($metrics['days_until_due']);
    }

    public function testCalculateTaskMetricsNotOverdueWhenStatusIsCompleted(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture([
            'due_date' => '2000-01-01',
            'status_id' => 6,
        ])]);

        $this->assertFalse($metrics['is_overdue']);
    }

    public function testCalculateTaskMetricsComputesDaysInProgressFromStartDate(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture([
            'start_date' => '2000-01-01',
        ])]);

        $this->assertGreaterThan(0, $metrics['days_in_progress']);
    }

    public function testCalculateTaskMetricsComplexityReachesVeryHighWithAllFactors(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture([
            'estimated_time' => 20000,
            'priority' => 'high',
            'is_subtask' => true,
            'milestones' => [(object) ['id' => 1]],
        ])]);

        $this->assertSame('Very High', $metrics['complexity']);
    }

    public function testCalculateTaskMetricsComplexityMediumWithModerateFactors(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $metrics = $this->invokePrivate($task, 'calculateTaskMetrics', [$this->taskFixture([
            'estimated_time' => 5000,
            'priority' => 'medium',
        ])]);

        $this->assertSame('Medium', $metrics['complexity']);
    }

    // -------------------------------------------------------------------- formatFieldValue()

    public function testFormatFieldValueReturnsNoneForNullValue(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('None', $this->invokePrivate($task, 'formatFieldValue', ['title', null]));
    }

    public function testFormatFieldValueStatusIdUsesViewHelperLabel(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('In Progress', $this->invokePrivate($task, 'formatFieldValue', ['status_id', 2]));
        $this->assertSame('Unknown', $this->invokePrivate($task, 'formatFieldValue', ['status_id', 999]));
    }

    public function testFormatFieldValueProjectIdLooksUpProjectName(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 'Alpha'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Alpha', $this->invokePrivate($task, 'formatFieldValue', ['project_id', 1]));
    }

    public function testFormatFieldValueProjectIdFallsBackWhenNotFound(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => false])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Unknown Project', $this->invokePrivate($task, 'formatFieldValue', ['project_id', 1]));
    }

    public function testFormatFieldValueProjectIdFallsBackOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('down'));
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Unknown Project', $this->invokePrivate($task, 'formatFieldValue', ['project_id', 1]));
    }

    public function testFormatFieldValueAssignedToLooksUpUserName(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 'Ann Lee'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Ann Lee', $this->invokePrivate($task, 'formatFieldValue', ['assigned_to', 1]));
    }

    public function testFormatFieldValueAssignedToFallsBackOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('down'));
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Unknown User', $this->invokePrivate($task, 'formatFieldValue', ['assigned_to', 1]));
    }

    public function testFormatFieldValueParentTaskIdLooksUpTaskTitle(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 'Parent Task'])]);
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Parent Task', $this->invokePrivate($task, 'formatFieldValue', ['parent_task_id', 1]));
    }

    public function testFormatFieldValueParentTaskIdFallsBackOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('down'));
        $task = $this->newTaskWithDb($db);

        $this->assertSame('Unknown Task', $this->invokePrivate($task, 'formatFieldValue', ['parent_task_id', 1]));
    }

    public function testFormatFieldValuePriorityCapitalizes(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('High', $this->invokePrivate($task, 'formatFieldValue', ['priority', 'high']));
    }

    public function testFormatFieldValueIsHourlyRendersYesNo(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('Yes', $this->invokePrivate($task, 'formatFieldValue', ['is_hourly', true]));
        $this->assertSame('No', $this->invokePrivate($task, 'formatFieldValue', ['is_hourly', false]));
    }

    public function testFormatFieldValueEstimatedTimeFormatsSeconds(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('2m', $this->invokePrivate($task, 'formatFieldValue', ['estimated_time', 120]));
    }

    public function testFormatFieldValueDefaultCastsToString(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('123', $this->invokePrivate($task, 'formatFieldValue', ['title', 123]));
    }

    // -------------------------------------------------------------------- trackTaskChanges()

    public function testTrackTaskChangesSkipsUnmappedAndUnchangedFields(): void
    {
        $insertCalls = [];
        $db = $this->makeDb([], $insertCalls, true, 1);
        $task = $this->newTaskWithDb($db);

        $currentTask = (object) ['title' => 'Same', 'unmapped_field' => 'x'];
        $newData = [
            'title' => 'Same',
            'unmapped_field' => 'y',
            'description' => 'Changed',
        ];

        $this->invokePrivate($task, 'trackTaskChanges', [1, 2, $currentTask, $newData]);

        $this->assertCount(1, $insertCalls);
        $this->assertSame('Description', $insertCalls[0]['params'][':field_changed']);
    }

    // ------------------------------------------------------------------------ search index

    public function testSearchEntityTypeReturnsTask(): void
    {
        $task = $this->newTaskWithDb($this->createMock(Database::class));

        $this->assertSame('task', $task->searchEntityType());
    }

    public function testToSearchIndexRowProjectsFieldsAndTruncatesDescription(): void
    {
        $row = (object) [
            'title' => 'Fix bug',
            'description' => str_repeat('x', 250),
            'project_id' => 9,
        ];
        $db = $this->dbSequenced([$this->statement(['fetch' => $row])]);
        $task = $this->newTaskWithDb($db);

        $result = $task->toSearchIndexRow(1);

        $this->assertSame('Fix bug', $result[0]);
        $this->assertSame(200, strlen($result[1]));
        $this->assertSame(9, $result[2]);
        $this->assertStringStartsWith('Fix bug', $result[3]);
    }

    public function testToSearchIndexRowReturnsNullProjectIdWhenMissing(): void
    {
        $row = (object) ['title' => 'No project', 'description' => null];
        $db = $this->dbSequenced([$this->statement(['fetch' => $row])]);
        $task = $this->newTaskWithDb($db);

        $result = $task->toSearchIndexRow(1);

        $this->assertNull($result[2]);
    }

    public function testToSearchIndexRowReturnsNullWhenTaskNotFound(): void
    {
        $db = $this->dbSequenced([$this->blankStatement()]);
        $task = $this->newTaskWithDb($db);

        $this->assertNull($task->toSearchIndexRow(404));
    }
}
