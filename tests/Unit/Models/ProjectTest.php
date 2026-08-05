<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Models\BaseModel;
use App\Models\Concerns\Searchable;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Behavioural tests for the Project model: detail loading with selective
 * eager-loading options, health-metric calculation, the epic/milestone/task
 * hierarchy builder, key-code generation and the search-index row projection.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest/
 * TaskTest). Project does not override create()/update(), so those paths
 * (and the Searchable::afterSave chain they trigger) are exercised by
 * BaseModelTest instead — this file only calls the write-free read methods
 * Project.php actually defines, plus the two Searchable contract methods it
 * implements directly (searchEntityType/toSearchIndexRow).
 */
#[CoversClass(Project::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(MilestoneStatus::class)]
#[UsesClass(Searchable::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(TaskStatus::class)]
final class ProjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
        $this->seedSettingsCache([]);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);
        $this->seedSettingsCache(null);

        parent::tearDown();
    }

    // --------------------------------------------------------------- helpers

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function seedSettingsCache(?array $cache): void
    {
        (new ReflectionClass(SettingsService::class))->getProperty('cache')->setValue(null, $cache);
    }

    private function newProjectWithDb(Database $db): Project
    {
        $this->seedDatabase($db);

        return new Project();
    }

    private function statement(array $methodReturns): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);

        foreach ($methodReturns as $method => $value) {
            $stmt->method($method)->willReturn($value);
        }

        return $stmt;
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

    private function invokePrivate(Project $project, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod(Project::class, $method))->invoke($project, ...$args);
    }

    // ------------------------------------------------------------ findWithDetails()

    public function testFindWithDetailsLoadsAllRelatedDataWithDefaultOptions(): void
    {
        $projectRow = (object) ['id' => 1, 'name' => 'Aureo'];
        $tasks = [(object) ['id' => 10]];
        $milestones = [(object) ['id' => 20]];
        $sprints = [(object) ['id' => 30]];
        $team = [(object) ['id' => 40]];

        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => [$projectRow]]),
            $this->statement(['fetchAll' => $tasks]),
            $this->statement(['fetchAll' => $milestones]),
            $this->statement(['fetchAll' => $sprints]),
            $this->statement(['fetchAll' => $team]),
            $this->statement(['fetchAll' => []]), // getProjectEpics
            $this->statement(['fetchAll' => []]), // getStandaloneMilestones
            $this->statement(['fetchAll' => []]), // getUnassignedTasks
            $this->statement(['fetch' => ['total_tasks' => 4, 'completed_tasks' => 2, 'overdue_tasks' => 1]]),
            $this->statement(['fetch' => ['total_milestones' => 2, 'completed_milestones' => 1, 'overdue_milestones' => 0]]),
        ]);
        $project = $this->newProjectWithDb($db);

        $result = $project->findWithDetails(1);

        $this->assertSame($tasks, $result->tasks);
        $this->assertSame($milestones, $result->milestones);
        $this->assertSame($sprints, $result->sprints);
        $this->assertSame($team, $result->team_members);
        $this->assertSame([], $result->hierarchy);
        $this->assertSame(50.0, $result->health_metrics['task_completion_rate']);
        $this->assertSame(50.0, $result->health_metrics['milestone_completion_rate']);
        $this->assertSame('At Risk', $result->health_metrics['overall_health']);
    }

    public function testFindWithDetailsReturnsNullWhenProjectNotFound(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchAll' => []])]);
        $project = $this->newProjectWithDb($db);

        $this->assertNull($project->findWithDetails(999));
    }

    public function testFindWithDetailsRespectsSelectivelyDisabledOptions(): void
    {
        $projectRow = (object) ['id' => 2, 'name' => 'Beta'];
        $tasks = [(object) ['id' => 11]];

        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => [$projectRow]]),
            $this->statement(['fetchAll' => $tasks]),
        ]);
        $project = $this->newProjectWithDb($db);

        $result = $project->findWithDetails(2, [
            'milestones' => false,
            'sprints' => false,
            'team_members' => false,
            'hierarchy' => false,
            'health_metrics' => false,
        ]);

        $this->assertSame($tasks, $result->tasks);
        $this->assertFalse(property_exists($result, 'milestones'));
        $this->assertFalse(property_exists($result, 'sprints'));
        $this->assertFalse(property_exists($result, 'team_members'));
        $this->assertFalse(property_exists($result, 'hierarchy'));
        $this->assertFalse(property_exists($result, 'health_metrics'));
    }

    public function testFindBasicOmitsAllRelatedData(): void
    {
        $projectRow = (object) ['id' => 3, 'name' => 'Gamma'];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => [$projectRow]])]);
        $project = $this->newProjectWithDb($db);

        $result = $project->findBasic(3);

        $this->assertFalse(property_exists($result, 'tasks'));
        $this->assertFalse(property_exists($result, 'milestones'));
        $this->assertFalse(property_exists($result, 'sprints'));
        $this->assertFalse(property_exists($result, 'team_members'));
        $this->assertFalse(property_exists($result, 'hierarchy'));
        $this->assertFalse(property_exists($result, 'health_metrics'));
    }

    // ---------------------------------------------------------- getAllWithDetails()

    public function testGetAllWithDetailsComputesOffsetAndOrdersByUpdatedAt(): void
    {
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => []]));
        $project = $this->newProjectWithDb($db);

        $project->getAllWithDetails(5, 3);

        $this->assertSame(10, $calls[0]['params'][':offset']);
        $this->assertSame(5, $calls[0]['params'][':limit']);
        $this->assertStringContainsString('ORDER BY p.updated_at DESC', $calls[0]['sql']);
    }

    // ------------------------------------------------------- calculateProjectHealth()

    public function testCalculateProjectHealthComputesRatesAndExcellentHealth(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetch' => ['total_tasks' => 10, 'completed_tasks' => 10, 'overdue_tasks' => 0]]),
            $this->statement(['fetch' => ['total_milestones' => 4, 'completed_milestones' => 4, 'overdue_milestones' => 0]]),
        ]);
        $project = $this->newProjectWithDb($db);

        $health = $project->calculateProjectHealth(1);

        $this->assertSame(100.0, $health['task_completion_rate']);
        $this->assertSame(100.0, $health['milestone_completion_rate']);
        $this->assertSame('Excellent', $health['overall_health']);
    }

    public function testCalculateProjectHealthGoodWhenAverageAtSeventy(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetch' => ['total_tasks' => 10, 'completed_tasks' => 8, 'overdue_tasks' => 0]]),
            $this->statement(['fetch' => ['total_milestones' => 10, 'completed_milestones' => 6, 'overdue_milestones' => 0]]),
        ]);
        $project = $this->newProjectWithDb($db);

        $health = $project->calculateProjectHealth(1);

        $this->assertSame('Good', $health['overall_health']);
    }

    public function testCalculateProjectHealthAtRiskWhenAverageAtFifty(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetch' => ['total_tasks' => 2, 'completed_tasks' => 1, 'overdue_tasks' => 0]]),
            $this->statement(['fetch' => ['total_milestones' => 2, 'completed_milestones' => 1, 'overdue_milestones' => 0]]),
        ]);
        $project = $this->newProjectWithDb($db);

        $health = $project->calculateProjectHealth(1);

        $this->assertSame('At Risk', $health['overall_health']);
    }

    public function testCalculateProjectHealthCriticalWhenNoCompletion(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetch' => ['total_tasks' => 5, 'completed_tasks' => 0, 'overdue_tasks' => 3]]),
            $this->statement(['fetch' => ['total_milestones' => 0, 'completed_milestones' => 0, 'overdue_milestones' => 0]]),
        ]);
        $project = $this->newProjectWithDb($db);

        $health = $project->calculateProjectHealth(1);

        $this->assertSame(0, $health['milestone_completion_rate']);
        $this->assertSame('Critical', $health['overall_health']);
    }

    public function testCalculateProjectHealthAvoidsDivisionByZeroWithNoTasks(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetch' => ['total_tasks' => 0, 'completed_tasks' => 0, 'overdue_tasks' => 0]]),
            $this->statement(['fetch' => ['total_milestones' => 4, 'completed_milestones' => 4, 'overdue_milestones' => 0]]),
        ]);
        $project = $this->newProjectWithDb($db);

        $health = $project->calculateProjectHealth(1);

        $this->assertSame(0, $health['task_completion_rate']);
    }

    public function testCalculateProjectHealthWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to calculate project health: boom');

        $project->calculateProjectHealth(1);
    }

    // ------------------------------------------------------------- getProjectTasks()

    public function testGetProjectTasksReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $project = $this->newProjectWithDb($db);

        $this->assertSame($rows, $project->getProjectTasks(1));
    }

    public function testGetProjectTasksReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $project->getProjectTasks(1));
    }

    // --------------------------------------------------------- getProjectHierarchy()

    public function testGetProjectHierarchyBuildsFullEpicMilestoneTaskTree(): void
    {
        $epic = (object) ['id' => 100, 'milestone_type' => 'epic'];
        $epicMilestone = (object) ['id' => 200, 'milestone_type' => 'milestone'];
        $epicMilestoneTasks = [(object) ['id' => 1]];
        $epicDirectTasks = [(object) ['id' => 2]];
        $standaloneMilestone = (object) ['id' => 300];
        $standaloneMilestoneTasks = [(object) ['id' => 3]];
        $unassignedTasks = [(object) ['id' => 4]];

        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => [$epic]]),                    // getProjectEpics
            $this->statement(['fetchAll' => [$epicMilestone]]),           // getEpicMilestones
            $this->statement(['fetchAll' => $epicMilestoneTasks]),        // getMilestoneTasks (epic milestone)
            $this->statement(['fetchAll' => $epicDirectTasks]),           // getEpicTasks
            $this->statement(['fetchAll' => [$standaloneMilestone]]),     // getStandaloneMilestones
            $this->statement(['fetchAll' => $standaloneMilestoneTasks]),  // getMilestoneTasks (standalone)
            $this->statement(['fetchAll' => $unassignedTasks]),           // getUnassignedTasks
        ]);
        $project = $this->newProjectWithDb($db);

        $hierarchy = $project->getProjectHierarchy(1);

        $this->assertCount(3, $hierarchy);

        $this->assertSame('epic', $hierarchy[0]['type']);
        $this->assertSame($epic, $hierarchy[0]['data']);
        $this->assertSame($epicMilestoneTasks, $hierarchy[0]['milestones'][0]['tasks']);
        $this->assertSame($epicDirectTasks, $hierarchy[0]['tasks']);

        $this->assertSame('milestone', $hierarchy[1]['type']);
        $this->assertSame($standaloneMilestone, $hierarchy[1]['data']);
        $this->assertSame($standaloneMilestoneTasks, $hierarchy[1]['tasks']);

        $this->assertSame('unassigned_tasks', $hierarchy[2]['type']);
        $this->assertSame('Unassigned Tasks', $hierarchy[2]['data']->title);
        $this->assertSame($unassignedTasks, $hierarchy[2]['tasks']);
    }

    public function testGetProjectHierarchyOmitsUnassignedBucketWhenEmpty(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => []]), // getProjectEpics
            $this->statement(['fetchAll' => []]), // getStandaloneMilestones
            $this->statement(['fetchAll' => []]), // getUnassignedTasks
        ]);
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $project->getProjectHierarchy(1));
    }

    // ------------------------------------------------- hierarchy private sub-fetchers

    public function testGetProjectEpicsReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $this->invokePrivate($project, 'getProjectEpics', [1]));
    }

    public function testGetEpicMilestonesReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $this->invokePrivate($project, 'getEpicMilestones', [1]));
    }

    public function testGetMilestoneTasksReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $this->invokePrivate($project, 'getMilestoneTasks', [1]));
    }

    public function testGetEpicTasksReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $this->invokePrivate($project, 'getEpicTasks', [1]));
    }

    public function testGetStandaloneMilestonesReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $this->invokePrivate($project, 'getStandaloneMilestones', [1]));
    }

    public function testGetUnassignedTasksReturnsEmptyArrayOnException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->assertSame([], $this->invokePrivate($project, 'getUnassignedTasks', [1]));
    }

    // ---------------------------------------------------------- getProjectMilestones()

    public function testGetProjectMilestonesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $project = $this->newProjectWithDb($db);

        $this->assertSame($rows, $project->getProjectMilestones(1));
    }

    public function testGetProjectMilestonesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get project milestones: boom');

        $project->getProjectMilestones(1);
    }

    // ------------------------------------------------------------- getProjectSprints()

    public function testGetProjectSprintsReturnsRows(): void
    {
        $rows = [(object) ['id' => 1]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $project = $this->newProjectWithDb($db);

        $this->assertSame($rows, $project->getProjectSprints(1));
    }

    public function testGetProjectSprintsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get project sprints: boom');

        $project->getProjectSprints(1);
    }

    // -------------------------------------------------------- getProjectTeamMembers()

    public function testGetProjectTeamMembersReturnsRowsAndSharesProjectIdAcrossBothPlaceholders(): void
    {
        $rows = [(object) ['id' => 1]];
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => $rows]));
        $project = $this->newProjectWithDb($db);

        $this->assertSame($rows, $project->getProjectTeamMembers(9));
        $this->assertSame(9, $calls[0]['params'][':project_id']);
        $this->assertSame(9, $calls[0]['params'][':project_id_2']);
    }

    public function testGetProjectTeamMembersWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get project team members: boom');

        $project->getProjectTeamMembers(1);
    }

    // ------------------------------------------------------------ transformKeyCodeFormat()

    public function testTransformKeyCodeFormatUsesInitialsForMultiWordName(): void
    {
        $project = $this->newProjectWithDb($this->createMock(Database::class));

        $this->assertSame('PMS', $project->transformKeyCodeFormat('Project Management System'));
    }

    public function testTransformKeyCodeFormatFallsBackToFirstThreeLettersForShortSingleWord(): void
    {
        $project = $this->newProjectWithDb($this->createMock(Database::class));

        $this->assertSame('WEB', $project->transformKeyCodeFormat('Website'));
    }

    public function testTransformKeyCodeFormatReturnsPrjForEmptyName(): void
    {
        $project = $this->newProjectWithDb($this->createMock(Database::class));

        $this->assertSame('PRJ', $project->transformKeyCodeFormat(''));
    }

    public function testTransformKeyCodeFormatReturnsPrjWhenNameHasNoAlphanumericCharacters(): void
    {
        $project = $this->newProjectWithDb($this->createMock(Database::class));

        $this->assertSame('PRJ', $project->transformKeyCodeFormat('!!!'));
    }

    public function testTransformKeyCodeFormatKeepsMultipleSingleLetterWords(): void
    {
        $project = $this->newProjectWithDb($this->createMock(Database::class));

        $this->assertSame('ABCD', $project->transformKeyCodeFormat('A B C D'));
    }

    // ---------------------------------------------------------------- getAllStatuses()

    public function testGetAllStatusesReturnsRows(): void
    {
        $rows = [(object) ['id' => 1, 'name' => 'Active']];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $project = $this->newProjectWithDb($db);

        $this->assertSame($rows, $project->getAllStatuses());
    }

    public function testGetAllStatusesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch project statuses: boom');

        $project->getAllStatuses();
    }

    // ---------------------------------------------------------------- getRecentByUser()

    public function testGetRecentByUserSharesUserIdAcrossAllPlaceholders(): void
    {
        $rows = [(object) ['id' => 1]];
        $calls = [];
        $db = $this->capturingQueryDb($calls, $this->statement(['fetchAll' => $rows]));
        $project = $this->newProjectWithDb($db);

        $result = $project->getRecentByUser(7, 5);

        $this->assertSame($rows, $result);
        $this->assertSame(7, $calls[0]['params'][':user_id1']);
        $this->assertSame(7, $calls[0]['params'][':user_id2']);
        $this->assertSame(7, $calls[0]['params'][':user_id3']);
        $this->assertSame(5, $calls[0]['params'][':limit']);
    }

    public function testGetRecentByUserWrapsExceptionInRuntimeException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $project = $this->newProjectWithDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error fetching recent projects: boom');

        $project->getRecentByUser(7);
    }

    // ------------------------------------------------------------------------ search index

    public function testSearchEntityTypeReturnsProject(): void
    {
        $project = $this->newProjectWithDb($this->createMock(Database::class));

        $this->assertSame('project', $project->searchEntityType());
    }

    public function testToSearchIndexRowProjectsFieldsUsingOwnIdAsProjectId(): void
    {
        $row = (object) ['name' => 'Aureo', 'description' => str_repeat('y', 250)];
        $db = $this->dbSequenced([$this->statement(['fetch' => $row])]);
        $project = $this->newProjectWithDb($db);

        $result = $project->toSearchIndexRow(12);

        $this->assertSame('Aureo', $result[0]);
        $this->assertSame(200, strlen($result[1]));
        $this->assertSame(12, $result[2]);
        $this->assertStringStartsWith('Aureo', $result[3]);
    }

    public function testToSearchIndexRowReturnsNullWhenProjectNotFound(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetch' => false])]);
        $project = $this->newProjectWithDb($db);

        $this->assertNull($project->toSearchIndexRow(404));
    }
}
