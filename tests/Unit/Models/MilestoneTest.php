<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Milestone;
use App\Services\SecurityService;
use InvalidArgumentException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Milestone model: detail loading with selective
 * eager-loading options, progress/status computation, epic hierarchy checks
 * and the search-index row projection.
 *
 * The process-wide Database singleton is swapped for a mock via reflection
 * (mirroring BaseModelQueryBuilderTest/BreadcrumbTest) so no real MySQL
 * connection is attempted, and restored afterwards. SecurityService is
 * likewise seeded for the one branch (findWithDetails' catch block) that
 * reaches it.
 */
// Config is declared because Database consults Config::isProduction(). It is a
// process-wide singleton, so only the first test in a run to reach it executes
// its body — which test that is moves with execution order, and this surfaced
// only in the full suite, not when running tests/Unit/Models alone.
#[CoversClass(Milestone::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(SecurityService::class)]
final class MilestoneTest extends TestCase
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

    // --------------------------------------------------------- findWithDetails()

    public function testFindWithDetailsLoadsTasksEpicRelationsAndSprintsWhenOptionsEnabled(): void
    {
        $milestone = (object)['id' => 1, 'milestone_type' => 'epic', 'status_name' => null];
        $tasks = [(object)['id' => 10]];
        $epicMilestones = [(object)['id' => 20]];
        $lookup = (object)['project_id' => 5];
        $sprints = [(object)['id' => 30]];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $milestone]),
            $this->statement(['fetchAll' => $tasks]),
            $this->statement(['fetchAll' => $epicMilestones]),
            $this->statement(['fetch' => $lookup]),
            $this->statement(['fetchAll' => $sprints]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $result = $model->findWithDetails(1, ['related_sprints' => true]);

        $this->assertSame($tasks, $result->tasks);
        $this->assertSame($epicMilestones, $result->related_milestones);
        $this->assertSame($sprints, $result->related_sprints);
        $this->assertSame('Unknown', $result->status_name);
    }

    public function testFindWithDetailsDefaultOptionsSkipEpicLookupForPlainMilestone(): void
    {
        $milestone = (object)['id' => 2, 'milestone_type' => 'milestone', 'status_name' => 'Active'];
        $tasks = [(object)['id' => 11]];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $milestone]),
            $this->statement(['fetchAll' => $tasks]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $result = $model->findWithDetails(2);

        $this->assertSame($tasks, $result->tasks);
        $this->assertFalse(property_exists($result, 'related_milestones'));
        $this->assertSame('Active', $result->status_name);
    }

    public function testFindWithDetailsReturnsNullWhenMilestoneNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertNull($model->findWithDetails(99));
    }

    public function testFindWithDetailsWrapsExceptionUsingSecurityService(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('safe-message');
        $this->seedSecurityService($security);

        $model = new Milestone();

        // The inner try re-throws the RuntimeException built from
        // getSafeErrorMessage()'s return value inside its own try block, so it is
        // immediately caught by the following catch(\Exception) and replaced with
        // the generic fallback — the "safe" message from SecurityService never
        // actually reaches the caller. This documents that real (if surprising)
        // behaviour rather than the apparent intent.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find milestone details');

        $model->findWithDetails(1);
    }

    public function testFindBasicOmitsRelatedData(): void
    {
        $milestone = (object)['id' => 3, 'milestone_type' => 'milestone', 'status_name' => null];

        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => $milestone]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $result = $model->findBasic(3);

        $this->assertFalse(property_exists($result, 'tasks'));
        $this->assertSame('Unknown', $result->status_name);
    }

    // --------------------------------------------------------------------- find()

    public function testFindReturnsRowWithDefaultedStatusName(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object)['id' => 4, 'status_name' => null]]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $result = $model->find(4);

        $this->assertSame('Unknown', $result->status_name);
    }

    public function testFindReturnsFalseWhenNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertFalse($model->find(404));
    }

    public function testFindThrowsRuntimeExceptionOnDatabaseFailure(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find milestone:');

        $model->find(1);
    }

    // ------------------------------------------------------- getAllWithProgress()

    public function testGetAllWithProgressAppliesOperatorsAndSkipsInvalidColumns(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured) {
                $captured[] = ['sql' => $sql, 'params' => $params];

                return $this->statement(['fetchAll' => []]);
            }
        );
        $this->seedDatabase($db);

        $model = new Milestone();
        $model->getAllWithProgress(5, 2, [
            'due_date' => ['>' => '2024-01-01'],
            'start_date' => ['<' => '2024-06-01'],
            'status_id' => 2,
            'bad col!' => 'ignored',
        ]);

        $sql = $captured[0]['sql'];
        $params = $captured[0]['params'];

        $this->assertStringContainsString('m.due_date > :due_date', $sql);
        $this->assertStringContainsString('m.start_date < :start_date', $sql);
        $this->assertStringContainsString('m.status_id = :status_id', $sql);
        $this->assertArrayNotHasKey(':bad col!', $params);
        $this->assertSame(5, $params[':limit']);
        $this->assertSame(5, $params[':offset']); // (page 2 - 1) * limit 5
    }

    public function testGetAllWithProgressDefaultsToNoExtraConditions(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => [(object)['id' => 1]]]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $result = $model->getAllWithProgress();

        $this->assertCount(1, $result);
    }

    // ----------------------------------------------------------- getByProjectId()

    public function testGetByProjectIdReturnsRows(): void
    {
        $rows = [(object)['id' => 1], (object)['id' => 2]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($rows, $model->getByProjectId(7));
    }

    public function testGetByProjectIdWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('fail'));
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching milestones for project:');

        $model->getByProjectId(7);
    }

    // ------------------------------------------------------- simple read methods

    public function testGetMilestoneStatusesReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'name' => 'Open']];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($rows, $model->getMilestoneStatuses());
    }

    public function testGetProjectEpicsReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'milestone_type' => 'epic']];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($rows, $model->getProjectEpics(9));
    }

    public function testGetEpicMilestonesReturnsRows(): void
    {
        $rows = [(object)['id' => 2, 'milestone_type' => 'milestone']];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($rows, $model->getEpicMilestones(9));
    }

    public function testGetTasksReturnsRows(): void
    {
        $rows = [(object)['id' => 3]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($rows, $model->getTasks(9));
    }

    // ---------------------------------------------------------- getRelatedSprints()

    public function testGetRelatedSprintsReturnsEmptyWhenMilestoneNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame([], $model->getRelatedSprints(1));
    }

    public function testGetRelatedSprintsReturnsSprintsForMilestoneProject(): void
    {
        $sprints = [(object)['id' => 1, 'shared_tasks' => 2]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object)['project_id' => 5]]),
            $this->statement(['fetchAll' => $sprints]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($sprints, $model->getRelatedSprints(1));
    }

    public function testGetRelatedSprintsReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame([], $model->getRelatedSprints(1));
    }

    // ------------------------------------------------------------------ addTasks()

    public function testAddTasksReplacesAssociationsAndCommits(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('beginTransaction')->willReturn(true);
        $db->expects($this->exactly(3))->method('executeInsertUpdate')->willReturn(true);
        $db->expects($this->once())->method('commit')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertTrue($model->addTasks(1, [10, 11]));
    }

    public function testAddTasksRollsBackAndThrowsOnFailure(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('insert failed'));
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add tasks to milestone:');

        $model->addTasks(1, [10]);
    }

    // ----------------------------------------------------------- calculateTaskProgress()

    public function testCalculateTaskProgressReturnsZeroWhenNoTasks(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object)['total_tasks' => 0, 'completed_tasks' => 0]]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame(0.0, $model->calculateTaskProgress(1));
    }

    public function testCalculateTaskProgressReturnsZeroWhenRowMissing(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame(0.0, $model->calculateTaskProgress(1));
    }

    public function testCalculateTaskProgressComputesPercentage(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object)['total_tasks' => 4, 'completed_tasks' => 2]]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame(50.0, $model->calculateTaskProgress(1));
    }

    // ---------------------------------------------------- checkCircularEpicReference()

    public function testCheckCircularEpicReferenceAllowsNonCircularHierarchy(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchColumn' => 0]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $model->checkCircularEpicReference(1, 2);

        $this->addToAssertionCount(1);
    }

    public function testCheckCircularEpicReferenceThrowsWhenCircular(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchColumn' => 1]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular epic reference detected');

        $model->checkCircularEpicReference(1, 2);
    }

    // ------------------------------------------------------------- getAssociatedSprints()

    public function testGetAssociatedSprintsReturnsRows(): void
    {
        $rows = [(object)['id' => 1]];
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame($rows, $model->getAssociatedSprints(1));
    }

    public function testGetAssociatedSprintsReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame([], $model->getAssociatedSprints(1));
    }

    // -------------------------------------------------------- getAvailableForSprint()

    public function testGetAvailableForSprintDefaultTypeOmitsTypeFilter(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured) {
                $captured = ['sql' => $sql, 'params' => $params];

                return $this->statement(['fetchAll' => []]);
            }
        );
        $this->seedDatabase($db);

        $model = new Milestone();
        $model->getAvailableForSprint(3);

        $this->assertStringNotContainsString('m.milestone_type = :type', $captured['sql']);
        $this->assertArrayNotHasKey(':type', $captured['params']);
    }

    public function testGetAvailableForSprintWithTypeAddsFilter(): void
    {
        $captured = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured) {
                $captured = ['sql' => $sql, 'params' => $params];

                return $this->statement(['fetchAll' => []]);
            }
        );
        $this->seedDatabase($db);

        $model = new Milestone();
        $model->getAvailableForSprint(3, 'epic');

        $this->assertStringContainsString('m.milestone_type = :type', $captured['sql']);
        $this->assertSame('epic', $captured['params'][':type']);
    }

    public function testGetAvailableForSprintReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertSame([], $model->getAvailableForSprint(3));
    }

    // --------------------------------------------------------------- search index

    public function testSearchEntityTypeReturnsMilestone(): void
    {
        $model = new Milestone();

        $this->assertSame('milestone', $model->searchEntityType());
    }

    public function testToSearchIndexRowReturnsProjectedFieldsAndTruncatesDescription(): void
    {
        $description = str_repeat('x', 250);
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object)[
                'id' => 1,
                'title' => 'Launch',
                'description' => $description,
                'project_id' => 8,
                'status_name' => null,
            ]]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame('Launch', $row[0]);
        $this->assertSame(200, strlen($row[1]));
        $this->assertSame(8, $row[2]);
        $this->assertStringStartsWith('Launch', $row[3]);
    }

    public function testToSearchIndexRowReturnsNullProjectIdWhenMissing(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => (object)[
                'id' => 1,
                'title' => 'No Project',
                'description' => null,
                'status_name' => null,
            ]]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();
        $row = $model->toSearchIndexRow(1);

        $this->assertNull($row[2]);
    }

    public function testToSearchIndexRowReturnsNullWhenMilestoneNotFound(): void
    {
        $db = $this->dbWithConsecutiveStatements([
            $this->statement(['fetch' => false]),
        ]);
        $this->seedDatabase($db);

        $model = new Milestone();

        $this->assertNull($model->toSearchIndexRow(999));
    }
}
