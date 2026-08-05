<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Models\BaseModel;
use App\Models\Concerns\Searchable;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Sprint;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Sprint model: lifecycle/status transitions,
 * task and milestone association queries, hierarchy/velocity/capacity
 * computations, and the search-index row projection.
 *
 * The process-wide Database singleton is swapped for a mock via reflection
 * (mirroring BaseModelTest/MilestoneTest) so no real MySQL connection is
 * attempted, and restored afterwards. SecurityService is likewise seeded
 * only for the exception paths that reach BaseModel::create()/update()'s
 * catch blocks. Any method reached through BaseModel::create()/update()
 * (startSprint, completeSprint, createFromMilestones) triggers the
 * Searchable trait's afterSave() hook, so a mocked SearchIndex is injected
 * via setSearchIndex() to avoid constructing a real one.
 *
 * Config/Setting/SettingsService/LoggerService are declared broadly per
 * project convention: they are process-wide singletons, so which test
 * first executes their bodies moves with execution order across the whole
 * suite, not just this file.
 */
#[CoversClass(Sprint::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Project::class)]
#[UsesClass(Searchable::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(SprintStatus::class)]
#[UsesClass(TaskStatus::class)]
final class SprintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
        $this->seedSecurityService(null);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);
        $this->seedSecurityService(null);

        parent::tearDown();
    }

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function seedSecurityService(?SecurityService $service): void
    {
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, $service);
    }

    private function statement(array $methodReturns): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);

        foreach ($methodReturns as $method => $value) {
            $stmt->method($method)->willReturn($value);
        }

        return $stmt;
    }

    private function dbWithConsecutiveStatements(array $statements): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnOnConsecutiveCalls(...$statements);

        return $db;
    }

    private function normalize(string $sql): string
    {
        return trim(preg_replace('/\s+/', ' ', $sql));
    }

    /**
     * Builds a Sprint model with a mocked SearchIndex injected, so writes
     * that go through BaseModel::create()/update() (and thus trigger the
     * Searchable trait's afterSave hook) never construct a real SearchIndex.
     */
    private function sprintWithMockedSearchIndex(): Sprint
    {
        $model = new Sprint();
        $model->setSearchIndex($this->createMock(\App\Models\SearchIndex::class));

        return $model;
    }

    // ----------------------------------------------------------- constructor

    public function testConstructorDefaultsStatusToPlanning(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $sprint = new Sprint();

        $this->assertSame(SprintStatus::PLANNING->value, $sprint->status_id);
    }

    // ------------------------------------------------------- getAllWithTasks()

    public function testGetAllWithTasksBuildsPaginatedQueryWithJoinsAndOffset(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured) {
                $captured[] = ['sql' => $this->normalize($sql), 'params' => $params];

                return $this->statement(['fetchAll' => [(object) ['id' => 1]]]);
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->getAllWithTasks(5, 3);

        $this->assertCount(1, $result);
        $sql = $captured[0]['sql'];
        $this->assertStringContainsString('FROM sprints s', $sql);
        $this->assertStringContainsString('LEFT JOIN projects p ON s.project_id = p.id', $sql);
        $this->assertStringContainsString('GROUP BY s.id', $sql);
        $this->assertStringContainsString('ORDER BY s.start_date DESC', $sql);
        $this->assertSame(5, $captured[0]['params'][':limit']);
        $this->assertSame(10, $captured[0]['params'][':offset']); // (page 3 - 1) * limit 5
    }

    // ----------------------------------------------------------- findWithDetails()

    public function testFindWithDetailsLoadsAllRelatedDataByDefault(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5];
        $taskRow = (object) ['id' => 10];
        $projectRow = (object) ['id' => 5, 'name' => 'Project A'];
        $milestoneRow = (object) ['id' => 20, 'milestone_type' => 'milestone'];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => [$sprintRow]]), // main queryBuilder select
            $this->statement(['fetchAll' => [$taskRow]]), // getSprintTasks
            $this->statement(['fetch' => ['total_tasks' => 4, 'completed_tasks' => 2]]), // getSprintVelocity
            $this->statement(['fetch' => $sprintRow]), // relationships: find(id)
            $this->statement(['fetch' => $projectRow]), // relationships: project find
            $this->statement(['fetchAll' => [$milestoneRow]]), // relationships: getSprintMilestones
            $this->statement(['fetchAll' => [$milestoneRow]]), // milestones option: getSprintMilestones
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->findWithDetails(1);

        $this->assertSame([$taskRow], $result->tasks);
        $this->assertSame(50.0, $result->velocity['velocity_percentage']);
        $this->assertSame(Sprint::RELATIONSHIP_MILESTONE, $result->relationships['type']);
        $this->assertSame($projectRow, $result->relationships['project']);
        $this->assertSame([$milestoneRow], $result->milestones);
    }

    public function testFindWithDetailsReturnsNullWhenSprintNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => []]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertNull($model->findWithDetails(999));
    }

    public function testFindWithDetailsSkipsAllRelatedDataWhenOptionsDisabled(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => [$sprintRow]]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->findWithDetails(1, [
            'tasks' => false,
            'velocity' => false,
            'relationships' => false,
            'milestones' => false,
        ]);

        $this->assertFalse(property_exists($result, 'tasks'));
        $this->assertFalse(property_exists($result, 'velocity'));
        $this->assertFalse(property_exists($result, 'relationships'));
        $this->assertFalse(property_exists($result, 'milestones'));
    }

    public function testFindWithDetailsRespectsMixedOptions(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5];
        $taskRow = (object) ['id' => 10];
        $milestoneRow = (object) ['id' => 20, 'milestone_type' => 'epic'];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => [$sprintRow]]), // main
            $this->statement(['fetchAll' => [$taskRow]]), // tasks
            $this->statement(['fetchAll' => [$milestoneRow]]), // milestones
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->findWithDetails(1, ['velocity' => false, 'relationships' => false]);

        $this->assertSame([$taskRow], $result->tasks);
        $this->assertSame([$milestoneRow], $result->milestones);
        $this->assertFalse(property_exists($result, 'velocity'));
        $this->assertFalse(property_exists($result, 'relationships'));
    }

    // --------------------------------------------------------------- findBasic()

    public function testFindBasicOmitsAllRelatedData(): void
    {
        $sprintRow = (object) ['id' => 3, 'project_id' => 5];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => [$sprintRow]]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->findBasic(3);

        $this->assertFalse(property_exists($result, 'tasks'));
        $this->assertFalse(property_exists($result, 'milestones'));
    }

    // ----------------------------------------------------------- getSprintStatuses()

    public function testGetSprintStatusesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'name' => 'Planning']];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getSprintStatuses());
    }

    // -------------------------------------------------------------- getSprintTasks()

    public function testGetSprintTasksReturnsRows(): void
    {
        $rows = [(object) ['id' => 7]];
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured, $rows) {
                $captured[] = ['sql' => $this->normalize($sql), 'params' => $params];

                return $this->statement(['fetchAll' => $rows]);
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->getSprintTasks(9);

        $this->assertSame($rows, $result);
        $this->assertSame(9, $captured[0]['params'][':sprint_id']);
        $this->assertStringContainsString('WHERE st.sprint_id = :sprint_id', $captured[0]['sql']);
    }

    // ------------------------------------------------------------ getSprintHierarchy()

    public function testGetSprintHierarchyBuildsFullNestedStructure(): void
    {
        $epic = (object) ['id' => 100, 'title' => 'Epic 1'];
        $milestone = (object) ['id' => 200, 'title' => 'Milestone 1'];
        $standaloneMilestone = (object) ['id' => 300, 'title' => 'Standalone'];
        $milestoneTask = (object) ['id' => 1];
        $epicTask = (object) ['id' => 2];
        $standaloneTask = (object) ['id' => 3];
        $unassignedTask = (object) ['id' => 4];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => [$epic]]), // getSprintEpics
            $this->statement(['fetchAll' => [$milestone]]), // getSprintEpicMilestones
            $this->statement(['fetchAll' => [$milestoneTask]]), // getSprintMilestoneTasks (for epic's milestone)
            $this->statement(['fetchAll' => [$epicTask]]), // getSprintEpicTasks
            $this->statement(['fetchAll' => [$standaloneMilestone]]), // getSprintStandaloneMilestones
            $this->statement(['fetchAll' => [$standaloneTask]]), // getSprintMilestoneTasks (standalone)
            $this->statement(['fetchAll' => [$unassignedTask]]), // getSprintUnassignedTasks
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $hierarchy = $model->getSprintHierarchy(1);

        $this->assertSame('epic', $hierarchy[0]['type']);
        $this->assertSame($epic, $hierarchy[0]['data']);
        $this->assertSame('milestone', $hierarchy[0]['milestones'][0]['type']);
        $this->assertSame([$milestoneTask], $hierarchy[0]['milestones'][0]['tasks']);
        $this->assertSame([$epicTask], $hierarchy[0]['tasks']);

        $this->assertSame('milestone', $hierarchy[1]['type']);
        $this->assertSame($standaloneMilestone, $hierarchy[1]['data']);
        $this->assertSame([$standaloneTask], $hierarchy[1]['tasks']);

        $this->assertSame('unassigned_tasks', $hierarchy[2]['type']);
        $this->assertSame([$unassignedTask], $hierarchy[2]['tasks']);
    }

    public function testGetSprintHierarchyReturnsEmptyArrayWhenNothingFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => []]), // getSprintEpics
            $this->statement(['fetchAll' => []]), // getSprintStandaloneMilestones
            $this->statement(['fetchAll' => []]), // getSprintUnassignedTasks
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getSprintHierarchy(1));
    }

    public function testGetSprintHierarchySwallowsEpicLookupExceptionAndContinues(): void
    {
        $call = 0;
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function () use (&$call) {
            $call++;
            if ($call === 1) {
                throw new RuntimeException('epics query failed');
            }

            return $this->statement(['fetchAll' => []]);
        });
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getSprintHierarchy(1));
    }

    public function testGetSprintHierarchySwallowsUnassignedTasksExceptionAndReturnsPartialResult(): void
    {
        $call = 0;
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function () use (&$call) {
            $call++;
            // Calls: 1=epics, 2=standalone milestones, 3=unassigned tasks (throws)
            if ($call === 3) {
                throw new RuntimeException('unassigned query failed');
            }

            return $this->statement(['fetchAll' => []]);
        });
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getSprintHierarchy(1));
    }

    public function testGetSprintHierarchySwallowsMilestoneTasksLookupException(): void
    {
        $epic = (object) ['id' => 100];
        $milestone = (object) ['id' => 200];

        $call = 0;
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function () use (&$call, $epic, $milestone) {
            $call++;
            // Calls: 1=epics, 2=epic milestones, 3=milestone tasks (throws),
            // 4=epic tasks, 5=standalone milestones, 6=unassigned tasks.
            if ($call === 1) {
                return $this->statement(['fetchAll' => [$epic]]);
            }
            if ($call === 2) {
                return $this->statement(['fetchAll' => [$milestone]]);
            }
            if ($call === 3) {
                throw new RuntimeException('milestone tasks query failed');
            }

            return $this->statement(['fetchAll' => []]);
        });
        $this->seedDatabase($db);

        $model = new Sprint();
        $hierarchy = $model->getSprintHierarchy(1);

        $this->assertSame([], $hierarchy[0]['milestones'][0]['tasks']);
    }

    public function testGetSprintHierarchySwallowsEpicMilestonesEpicTasksAndStandaloneMilestonesExceptions(): void
    {
        $epic = (object) ['id' => 100];

        $call = 0;
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function () use (&$call, $epic) {
            $call++;
            // Calls: 1=epics, 2=epic milestones (throws), 3=epic tasks (throws),
            // 4=standalone milestones (throws), 5=unassigned tasks.
            if ($call === 1) {
                return $this->statement(['fetchAll' => [$epic]]);
            }
            if (in_array($call, [2, 3, 4], true)) {
                throw new RuntimeException("query {$call} failed");
            }

            return $this->statement(['fetchAll' => []]);
        });
        $this->seedDatabase($db);

        $model = new Sprint();
        $hierarchy = $model->getSprintHierarchy(1);

        $this->assertSame([], $hierarchy[0]['milestones']);
        $this->assertSame([], $hierarchy[0]['tasks']);
        // Standalone milestones swallowed its exception too, so only the epic
        // entry is present in the hierarchy (no standalone / unassigned entries).
        $this->assertCount(1, $hierarchy);
    }

    // ------------------------------------------------------------------- addTasks()

    public function testAddTasksReplacesAssociationsAndCommits(): void
    {
        $calls = [];
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$calls): bool {
                $calls[] = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $db->expects($this->once())->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->addTasks(1, [11, 12]));
        $this->assertCount(2, $calls);
        $this->assertStringContainsString('DELETE FROM sprint_tasks', $calls[0]['sql']);
        $this->assertStringContainsString('INSERT INTO sprint_tasks', $calls[1]['sql']);
        $this->assertSame(11, $calls[1]['params'][':task_id_0']);
        $this->assertSame(12, $calls[1]['params'][':task_id_1']);
        $this->assertSame(1, $calls[1]['params'][':sprint_id']);
    }

    public function testAddTasksWithEmptyArraySkipsInsert(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->expects($this->once())->method('executeInsertUpdate')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->addTasks(1, []));
    }

    public function testAddTasksRollsBackAndThrowsOnFailure(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('insert failed'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add tasks to sprint:');

        $model->addTasks(1, [11]);
    }

    // ------------------------------------------------------------------ removeTask()

    public function testRemoveTaskDeletesAssociation(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->removeTask(1, 22));
        $this->assertStringContainsString('DELETE FROM sprint_tasks', $captured['sql']);
        $this->assertSame(1, $captured['params'][':sprint_id']);
        $this->assertSame(22, $captured['params'][':task_id']);
    }

    // -------------------------------------------------------- getActiveSprintForProject()

    public function testGetActiveSprintForProjectReturnsSprint(): void
    {
        $row = (object) ['id' => 1, 'status_id' => 2];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $row]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($row, $model->getActiveSprintForProject(5));
    }

    public function testGetActiveSprintForProjectReturnsNullWhenNoneActive(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertNull($model->getActiveSprintForProject(5));
    }

    // ------------------------------------------------------------------ getByProjectId()

    public function testGetByProjectIdReturnsRows(): void
    {
        $rows = [(object) ['id' => 1], (object) ['id' => 2]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getByProjectId(7));
    }

    public function testGetByProjectIdWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching sprints for project:');

        $model->getByProjectId(7);
    }

    // ------------------------------------------------------------ getPlannableByProjectId()

    public function testGetPlannableByProjectIdFiltersToPlanningAndActiveStatuses(): void
    {
        $rows = [(object) ['id' => 1]];
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured, $rows) {
                $captured = ['sql' => $this->normalize($sql), 'params' => $params];

                return $this->statement(['fetchAll' => $rows]);
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->getPlannableByProjectId(9);

        $this->assertSame($rows, $result);
        $this->assertStringContainsString('s.status_id IN (:status_planning, :status_active)', $captured['sql']);
        $this->assertSame(SprintStatus::PLANNING->value, $captured['params'][':status_planning']);
        $this->assertSame(SprintStatus::ACTIVE->value, $captured['params'][':status_active']);
    }

    public function testGetPlannableByProjectIdWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching plannable sprints for project:');

        $model->getPlannableByProjectId(9);
    }

    // ---------------------------------------------------------------- getProjectSprints()

    public function testGetProjectSprintsWithoutStatusOmitsFilter(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured) {
                $captured = ['sql' => $this->normalize($sql), 'params' => $params];

                return $this->statement(['fetchAll' => []]);
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();
        $model->getProjectSprints(4);

        $this->assertStringNotContainsString('ss.name = :status', $captured['sql']);
        $this->assertArrayNotHasKey(':status', $captured['params']);
    }

    public function testGetProjectSprintsWithStatusAppliesFilter(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured) {
                $captured = ['sql' => $this->normalize($sql), 'params' => $params];

                return $this->statement(['fetchAll' => []]);
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();
        $model->getProjectSprints(4, 'active');

        $this->assertStringContainsString('ss.name = :status', $captured['sql']);
        $this->assertSame('active', $captured['params'][':status']);
    }

    public function testGetProjectSprintsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get user sprints:');

        $model->getProjectSprints(4);
    }

    // ------------------------------------------------------------------ getSprintVelocity()

    public function testGetSprintVelocityComputesPercentage(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => ['total_tasks' => 3, 'completed_tasks' => 2]]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->getSprintVelocity(1);

        $this->assertSame(3, $result['total_tasks']);
        $this->assertSame(2, $result['completed_tasks']);
        $this->assertSame(66.67, $result['velocity_percentage']);
    }

    public function testGetSprintVelocityReturnsZeroPercentageWhenNoTasks(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => ['total_tasks' => 0, 'completed_tasks' => 0]]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->getSprintVelocity(1);

        $this->assertSame(0, $result['velocity_percentage']);
    }

    // ----------------------------------------------------------------------- startSprint()

    public function testStartSprintActivatesWhenNoOtherActiveSprint(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5, 'name' => 'S1', 'sprint_goal' => null];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $sprintRow]), // find($sprintId)
            $this->statement(['fetch' => false]), // getActiveSprintForProject: none active
            $this->statement(['fetch' => $sprintRow]), // afterSave->toSearchIndexRow->find
        ]);
        $db->method('executeInsertUpdate')->willReturn(true);
        $this->seedDatabase($db);

        $model = $this->sprintWithMockedSearchIndex();

        $this->assertTrue($model->startSprint(1));
    }

    public function testStartSprintThrowsWhenSprintNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sprint not found');

        $model->startSprint(1);
    }

    public function testStartSprintThrowsWhenAnotherSprintIsActive(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5];
        $activeSprintRow = (object) ['id' => 2, 'project_id' => 5];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $sprintRow]), // find($sprintId)
            $this->statement(['fetch' => $activeSprintRow]), // getActiveSprintForProject: a different sprint
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Another sprint is already active for this project');

        $model->startSprint(1);
    }

    public function testStartSprintAllowsReactivatingTheSameSprint(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5, 'name' => 'S1', 'sprint_goal' => null];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $sprintRow]), // find($sprintId)
            $this->statement(['fetch' => $sprintRow]), // getActiveSprintForProject: same sprint id
            $this->statement(['fetch' => $sprintRow]), // afterSave->toSearchIndexRow->find
        ]);
        $db->method('executeInsertUpdate')->willReturn(true);
        $this->seedDatabase($db);

        $model = $this->sprintWithMockedSearchIndex();

        $this->assertTrue($model->startSprint(1));
    }

    // -------------------------------------------------------------------- completeSprint()

    public function testCompleteSprintUpdatesStatusToCompleted(): void
    {
        $sprintRow = (object) ['id' => 1, 'name' => 'S1', 'sprint_goal' => null, 'project_id' => 5];
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $db->method('executeQuery')->willReturn($this->statement(['fetch' => $sprintRow]));
        $this->seedDatabase($db);

        $model = $this->sprintWithMockedSearchIndex();

        $this->assertTrue($model->completeSprint(1));
        $this->assertStringContainsString('status_id = :status_id', $captured['sql']);
        $this->assertSame(3, $captured['params'][':status_id']);
    }

    // -------------------------------------------------------------------------- getTaskSprint()

    public function testGetTaskSprintReturnsSprint(): void
    {
        $row = (object) ['id' => 1, 'assigned_at' => '2024-01-01'];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $row]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($row, $model->getTaskSprint(9));
    }

    public function testGetTaskSprintReturnsNullWhenNoneAssigned(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertNull($model->getTaskSprint(9));
    }

    public function testGetTaskSprintWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error getting task sprint:');

        $model->getTaskSprint(9);
    }

    // ----------------------------------------------------------------------------- assignTask()

    public function testAssignTaskReturnsTrueWhenAlreadyAssigned(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('beginTransaction')->willReturn(true);
        $db->method('executeQuery')->willReturn($this->statement(['fetchColumn' => 1]));
        $db->expects($this->never())->method('executeInsertUpdate');
        $db->expects($this->once())->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->assignTask(1, 10));
    }

    public function testAssignTaskAssignsMainTaskAndSubtasks(): void
    {
        $subtask = (object) ['id' => 55];
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeQuery')->willReturnOnConsecutiveCalls(
            $this->statement(['fetchColumn' => 0]), // not yet assigned
            $this->statement(['fetchAll' => [$subtask]]), // subtasks of parent
        );
        $insertCalls = 0;
        $db->method('executeInsertUpdate')->willReturnCallback(function () use (&$insertCalls): bool {
            $insertCalls++;

            return true;
        });
        $db->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->assignTask(1, 10, true));
        // removeTaskFromActiveSprints(main) + insert(main) + removeTaskFromActiveSprints(subtask) + insert(subtask)
        $this->assertSame(4, $insertCalls);
    }

    public function testAssignTaskWithoutSubtasksSkipsSubtaskLookup(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->expects($this->once())->method('executeQuery')->willReturn($this->statement(['fetchColumn' => 0]));
        $db->method('executeInsertUpdate')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->assignTask(1, 10, false));
    }

    public function testAssignTaskRollsBackAndReturnsFalseWhenInsertFails(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeQuery')->willReturn($this->statement(['fetchColumn' => 0]));
        $db->method('executeInsertUpdate')->willReturnOnConsecutiveCalls(true, false);
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertFalse($model->assignTask(1, 10, false));
    }

    public function testAssignTaskRollsBackAndThrowsOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error assigning task to sprint:');

        $model->assignTask(1, 10);
    }

    public function testAssignTaskWrapsExceptionFromRemoveTaskFromActiveSprints(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeQuery')->willReturn($this->statement(['fetchColumn' => 0]));
        // removeTaskFromActiveSprints() wraps this in its own RuntimeException,
        // which then propagates into assignTask()'s catch block.
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('delete failed'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error assigning task to sprint: Error removing task from active sprints:');

        $model->assignTask(1, 10, false);
    }

    // ------------------------------------------------------------- assignSubtasksToSprint()

    public function testAssignSubtasksToSprintAssignsEachSubtask(): void
    {
        $subtasks = [(object) ['id' => 1], (object) ['id' => 2]];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($this->statement(['fetchAll' => $subtasks]));
        $insertCalls = 0;
        $db->method('executeInsertUpdate')->willReturnCallback(function () use (&$insertCalls): bool {
            $insertCalls++;

            return true;
        });
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->assignSubtasksToSprint(1, 99));
        $this->assertSame(4, $insertCalls); // 2 subtasks x (remove + insert)
    }

    public function testAssignSubtasksToSprintReturnsFalseOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertFalse($model->assignSubtasksToSprint(1, 99));
    }

    // ------------------------------------------------------------------ removeTaskFromSprint()

    public function testRemoveTaskFromSprintRemovesMainAndSubtasksAndReturnsMainResult(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willReturnOnConsecutiveCalls(true, true);
        $db->expects($this->once())->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->removeTaskFromSprint(1, 10, true));
    }

    public function testRemoveTaskFromSprintWithoutSubtasksOnlyRemovesMainTask(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->expects($this->once())->method('executeInsertUpdate')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->removeTaskFromSprint(1, 10, false));
    }

    public function testRemoveTaskFromSprintReturnsFalseOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willThrowException(new RuntimeException('tx failed'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertFalse($model->removeTaskFromSprint(1, 10));
    }

    // -------------------------------------------------------------- removeSubtasksFromSprint()

    public function testRemoveSubtasksFromSprintDeletesJoinedRows(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured): bool {
                $captured = ['sql' => $this->normalize($sql), 'params' => $params];

                return true;
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->removeSubtasksFromSprint(1, 10));
        $this->assertStringContainsString('parent_task_id = :parent_task_id', $captured['sql']);
        $this->assertSame(1, $captured['params'][':sprint_id']);
        $this->assertSame(10, $captured['params'][':parent_task_id']);
    }

    public function testRemoveSubtasksFromSprintReturnsFalseOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertFalse($model->removeSubtasksFromSprint(1, 10));
    }

    // ------------------------------------------------------------- getSprintTasksWithSubtasks()

    public function testGetSprintTasksWithSubtasksReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'is_subtask' => 0]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getSprintTasksWithSubtasks(1));
    }

    public function testGetSprintTasksWithSubtasksReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getSprintTasksWithSubtasks(1));
    }

    // ----------------------------------------------------------------- getActiveSprintsForUser()

    public function testGetActiveSprintsForUserReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getActiveSprintsForUser(4));
    }

    public function testGetActiveSprintsForUserReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getActiveSprintsForUser(4));
    }

    // ------------------------------------------------------------ getActiveSprintsInUserProjects()

    public function testGetActiveSprintsInUserProjectsReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getActiveSprintsInUserProjects(4));
    }

    public function testGetActiveSprintsInUserProjectsReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getActiveSprintsInUserProjects(4));
    }

    // ------------------------------------------------------------------------- addMilestones()

    public function testAddMilestonesReplacesAssociationsAndCommits(): void
    {
        $calls = [];
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$calls): bool {
                $calls[] = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $db->expects($this->once())->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->addMilestones(1, [30, 31]));
        $this->assertCount(2, $calls);
        $this->assertStringContainsString('DELETE FROM sprint_milestones', $calls[0]['sql']);
        $this->assertStringContainsString('INSERT INTO sprint_milestones', $calls[1]['sql']);
        $this->assertSame(30, $calls[1]['params'][':milestone_id_0']);
        $this->assertSame(31, $calls[1]['params'][':milestone_id_1']);
    }

    public function testAddMilestonesWithEmptyArraySkipsInsert(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->expects($this->once())->method('executeInsertUpdate')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertTrue($model->addMilestones(1, []));
    }

    public function testAddMilestonesReturnsFalseOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('db down'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertFalse($model->addMilestones(1, [30]));
    }

    // -------------------------------------------------------------------- getSprintMilestones()

    public function testGetSprintMilestonesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getSprintMilestones(1));
    }

    public function testGetSprintMilestonesReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getSprintMilestones(1));
    }

    // -------------------------------------------------------------------- getSprintsByMilestone()

    public function testGetSprintsByMilestoneReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame($rows, $model->getSprintsByMilestone(1));
    }

    public function testGetSprintsByMilestoneReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getSprintsByMilestone(1));
    }

    // ------------------------------------------------------------------- getSprintRelationships()

    public function testGetSprintRelationshipsDeterminesEpicTypeWhenEpicsPresent(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5];
        $projectRow = (object) ['id' => 5, 'name' => 'Proj'];
        $milestones = [
            (object) ['id' => 10, 'milestone_type' => 'epic'],
            (object) ['id' => 11, 'milestone_type' => 'milestone'],
        ];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $sprintRow]), // find
            $this->statement(['fetch' => $projectRow]), // Project::find
            $this->statement(['fetchAll' => $milestones]), // getSprintMilestones
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $relationships = $model->getSprintRelationships(1);

        $this->assertSame(Sprint::RELATIONSHIP_EPIC, $relationships['type']);
        $this->assertSame($projectRow, $relationships['project']);
        $this->assertCount(1, $relationships['epics']);
        $this->assertCount(1, $relationships['milestones']);
    }

    public function testGetSprintRelationshipsDeterminesMilestoneTypeWhenOnlyMilestonesPresent(): void
    {
        $sprintRow = (object) ['id' => 1, 'project_id' => 5];
        $projectRow = (object) ['id' => 5];
        $milestones = [(object) ['id' => 11, 'milestone_type' => 'milestone']];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $sprintRow]),
            $this->statement(['fetch' => $projectRow]),
            $this->statement(['fetchAll' => $milestones]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $relationships = $model->getSprintRelationships(1);

        $this->assertSame(Sprint::RELATIONSHIP_MILESTONE, $relationships['type']);
    }

    public function testGetSprintRelationshipsDefaultsToProjectTypeWhenSprintNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]), // find: not found
            $this->statement(['fetchAll' => []]), // getSprintMilestones still runs
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $relationships = $model->getSprintRelationships(1);

        $this->assertSame(Sprint::RELATIONSHIP_PROJECT, $relationships['type']);
        $this->assertNull($relationships['project']);
    }

    public function testGetSprintRelationshipsReturnsDefaultStructureOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();
        $relationships = $model->getSprintRelationships(1);

        $this->assertSame([
            'type' => Sprint::RELATIONSHIP_PROJECT,
            'project' => null,
            'milestones' => [],
            'epics' => [],
        ], $relationships);
    }

    // ---------------------------------------------------------------------- getTasksFromMilestones()

    public function testGetTasksFromMilestonesReturnsEmptyArrayForEmptyInput(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Sprint();

        $this->assertSame([], $model->getTasksFromMilestones([]));
    }

    public function testGetTasksFromMilestonesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured, $rows) {
                $captured = ['sql' => $this->normalize($sql), 'params' => $params];

                return $this->statement(['fetchAll' => $rows]);
            }
        );
        $this->seedDatabase($db);

        $model = new Sprint();
        $result = $model->getTasksFromMilestones([10, 20, 30]);

        $this->assertSame($rows, $result);
        $this->assertStringContainsString('IN (?,?,?)', $captured['sql']);
        $this->assertSame([10, 20, 30], $captured['params']);
    }

    public function testGetTasksFromMilestonesReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertSame([], $model->getTasksFromMilestones([10]));
    }

    // -------------------------------------------------------------------------- createFromMilestones()

    public function testCreateFromMilestonesCreatesSprintAndAssociatesMilestonesAndTasks(): void
    {
        $sprintRow = (object) ['id' => 42, 'name' => 'S', 'sprint_goal' => null, 'project_id' => 1];
        $insertCalls = [];

        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $db->method('lastInsertId')->willReturn(42);
        $db->method('executeQuery')->willReturn($this->statement(['fetch' => $sprintRow]));
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$insertCalls): bool {
                $insertCalls[] = $sql;

                return true;
            }
        );
        $this->seedDatabase($db);

        $model = $this->sprintWithMockedSearchIndex();

        $sprintId = $model->createFromMilestones(
            ['project_id' => 1, 'name' => 'S', 'start_date' => '2024-01-01', 'end_date' => '2024-01-14', 'status_id' => 1],
            [30],
            [11],
        );

        $this->assertSame(42, $sprintId);
        $joined = implode(' | ', $insertCalls);
        $this->assertStringContainsString('INSERT INTO sprints', $joined);
        $this->assertStringContainsString('INSERT INTO sprint_milestones', $joined);
        $this->assertStringContainsString('INSERT INTO sprint_tasks', $joined);
    }

    public function testCreateFromMilestonesRollsBackAndThrowsOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('insert failed'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('safe message');
        $this->seedSecurityService($security);

        $model = new Sprint();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create sprint from milestones:');

        $model->createFromMilestones(
            ['project_id' => 1, 'name' => 'S', 'start_date' => '2024-01-01', 'end_date' => '2024-01-14', 'status_id' => 1],
        );
    }

    // ---------------------------------------------------------------------- getSprintCapacityBreakdown()

    public function testGetSprintCapacityBreakdownAggregatesByMilestoneEpicAndDirectTasks(): void
    {
        $tasks = [
            (object) [
                'id' => 1, 'title' => 'A', 'story_points' => 3, 'estimated_time' => 7200,
                'milestone_id' => 10, 'milestone_title' => 'M1', 'milestone_type' => 'milestone',
            ],
            (object) [
                'id' => 2, 'title' => 'B', 'story_points' => 2, 'estimated_time' => 3600,
                'milestone_id' => 10, 'milestone_title' => 'M1', 'milestone_type' => 'milestone',
            ],
            (object) [
                'id' => 3, 'title' => 'C', 'story_points' => 5, 'estimated_time' => 10800,
                'milestone_id' => 20, 'milestone_title' => 'E1', 'milestone_type' => 'epic',
            ],
            (object) [
                'id' => 99, 'title' => 'Direct Task', 'story_points' => 1, 'estimated_time' => 1800,
                'milestone_id' => null, 'milestone_title' => null, 'milestone_type' => null,
            ],
        ];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $tasks]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $breakdown = $model->getSprintCapacityBreakdown(1);

        $this->assertSame(11, $breakdown['total_story_points']);
        $this->assertSame(6.5, $breakdown['total_estimated_hours']);
        // PHP's / operator returns int (not float) when both operands are int
        // and evenly divisible: 7200/3600=2, 3600/3600=1, so 2+1=3 stays int.
        $this->assertSame([
            'title' => 'M1',
            'story_points' => 5,
            'estimated_hours' => 3,
            'task_count' => 2,
        ], $breakdown['by_milestone'][10]);
        $this->assertSame([
            'title' => 'E1',
            'story_points' => 5,
            'estimated_hours' => 3,
            'task_count' => 1,
        ], $breakdown['by_epic'][20]);
        $this->assertSame([[
            'id' => 99,
            'title' => 'Direct Task',
            'story_points' => 1,
            'estimated_hours' => 0.5,
        ]], $breakdown['direct_tasks']);
    }

    public function testGetSprintCapacityBreakdownReturnsDefaultStructureOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Sprint();
        $breakdown = $model->getSprintCapacityBreakdown(1);

        $this->assertSame([
            'total_story_points' => 0,
            'total_estimated_hours' => 0,
            'by_milestone' => [],
            'by_epic' => [],
            'direct_tasks' => [],
        ], $breakdown);
    }

    // ---------------------------------------------------------------------------- search index

    public function testSearchEntityTypeReturnsSprint(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Sprint();

        $this->assertSame('sprint', $model->searchEntityType());
    }

    public function testToSearchIndexRowReturnsProjectedFieldsAndTruncatesGoal(): void
    {
        $goal = str_repeat('g', 250);
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object) [
                'id' => 1,
                'name' => 'Sprint 1',
                'sprint_goal' => $goal,
                'project_id' => 8,
            ]]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame('Sprint 1', $row[0]);
        $this->assertSame(200, strlen($row[1]));
        $this->assertSame(8, $row[2]);
        $this->assertStringStartsWith('Sprint 1', $row[3]);
    }

    public function testToSearchIndexRowReturnsNullProjectIdWhenMissing(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object) [
                'id' => 1,
                'name' => 'No Project Sprint',
                'sprint_goal' => null,
            ]]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();
        $row = $model->toSearchIndexRow(1);

        $this->assertNull($row[2]);
    }

    public function testToSearchIndexRowReturnsNullWhenSprintNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Sprint();

        $this->assertNull($model->toSearchIndexRow(999));
    }
}
