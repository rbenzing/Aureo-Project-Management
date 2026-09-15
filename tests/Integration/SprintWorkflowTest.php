<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\SprintStatus;
use App\Models\BaseModel;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Models\Sprint;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\DomainFixtures;
use Tests\Support\TestCase;

/**
 * Integration tests for sprint planning against a real database — the second
 * half of audit finding M1.
 *
 * Sprint's planning methods manage their own transactions and cascade across
 * sprint_tasks, so a mocked driver can say nothing useful about them: whether a
 * subtask followed its parent, whether a task left the sprint it used to belong
 * to, and whether a rollback actually unwound anything are all properties of the
 * database, not of the call sequence.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(Sprint::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(SprintStatus::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class SprintWorkflowTest extends TestCase
{
    use DomainFixtures;

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

    /** @return list<int> task ids currently in the sprint */
    private function taskIdsIn(int $sprintId): array
    {
        $rows = $this->db->executeQuery(
            'SELECT task_id FROM sprint_tasks WHERE sprint_id = :id ORDER BY task_id',
            [':id' => $sprintId]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    private function statusOf(int $sprintId): int
    {
        return (int) $this->db->executeQuery(
            'SELECT status_id FROM sprints WHERE id = :id',
            [':id' => $sprintId]
        )->fetch(\PDO::FETCH_OBJ)->status_id;
    }

    // ---- assigning work to a sprint --------------------------------------

    public function testAssigningATaskPutsItInTheSprint(): void
    {
        $sprintId = $this->createSprint($this->projectId);
        $taskId = $this->createTask($this->projectId);

        $this->assertTrue((new Sprint())->assignTask($sprintId, $taskId));
        $this->assertSame([$taskId], $this->taskIdsIn($sprintId));
    }

    /**
     * Subtasks follow their parent by default. Leaving them behind would put a
     * parent in the sprint while its children stayed in the backlog, which is
     * the kind of split nobody notices until the burndown is wrong.
     */
    public function testAssigningAParentTaskCarriesItsSubtasks(): void
    {
        $sprintId = $this->createSprint($this->projectId);
        $parentId = $this->createTask($this->projectId, ['title' => 'Parent']);
        $childId = $this->createTask($this->projectId, [
            'title' => 'Child',
            'is_subtask' => 1,
            'parent_task_id' => $parentId,
        ]);

        (new Sprint())->assignTask($sprintId, $parentId);

        $inSprint = $this->taskIdsIn($sprintId);

        $this->assertContains($parentId, $inSprint);
        $this->assertContains($childId, $inSprint, 'A subtask must follow its parent into the sprint.');
    }

    public function testSubtasksCanBeLeftBehindOnRequest(): void
    {
        $sprintId = $this->createSprint($this->projectId);
        $parentId = $this->createTask($this->projectId);
        $childId = $this->createTask($this->projectId, [
            'is_subtask' => 1,
            'parent_task_id' => $parentId,
        ]);

        (new Sprint())->assignTask($sprintId, $parentId, includeSubtasks: false);

        $this->assertSame([$parentId], $this->taskIdsIn($sprintId));
        $this->assertNotContains($childId, $this->taskIdsIn($sprintId));
    }

    /**
     * sprint_tasks carries a unique key on the pair, so a second assignment has
     * to be recognised rather than attempted — otherwise planning the same task
     * twice is a duplicate-key error in the user's face.
     */
    public function testAssigningTheSameTaskTwiceIsIdempotent(): void
    {
        $sprintId = $this->createSprint($this->projectId);
        $taskId = $this->createTask($this->projectId);

        $sprint = new Sprint();
        $sprint->assignTask($sprintId, $taskId);

        $this->assertTrue($sprint->assignTask($sprintId, $taskId));
        $this->assertSame([$taskId], $this->taskIdsIn($sprintId), 'The task must appear once, not twice.');
    }

    /**
     * A task belongs to at most one active sprint. Without this, re-planning a
     * task into the current sprint would leave it counted in the previous one
     * as well.
     */
    public function testAssigningATaskRemovesItFromAnotherActiveSprint(): void
    {
        $activeSprintId = $this->createSprint($this->projectId, SprintStatus::ACTIVE->value, 'Active');
        $nextSprintId = $this->createSprint($this->projectId, SprintStatus::PLANNING->value, 'Next');
        $taskId = $this->createTask($this->projectId);

        $sprint = new Sprint();
        $sprint->assignTask($activeSprintId, $taskId);
        $sprint->assignTask($nextSprintId, $taskId);

        $this->assertSame([], $this->taskIdsIn($activeSprintId), 'The task must leave the active sprint.');
        $this->assertSame([$taskId], $this->taskIdsIn($nextSprintId));
    }

    // ---- removing work ---------------------------------------------------

    public function testRemovingAParentTaskAlsoRemovesItsSubtasks(): void
    {
        $sprintId = $this->createSprint($this->projectId);
        $parentId = $this->createTask($this->projectId);
        $childId = $this->createTask($this->projectId, [
            'is_subtask' => 1,
            'parent_task_id' => $parentId,
        ]);

        $sprint = new Sprint();
        $sprint->assignTask($sprintId, $parentId);
        $sprint->removeTaskFromSprint($sprintId, $parentId);

        $this->assertSame([], $this->taskIdsIn($sprintId));
        $this->assertNotContains($childId, $this->taskIdsIn($sprintId));
    }

    public function testRemovingLeavesOtherTasksAlone(): void
    {
        $sprintId = $this->createSprint($this->projectId);
        $keptId = $this->createTask($this->projectId, ['title' => 'Kept']);
        $droppedId = $this->createTask($this->projectId, ['title' => 'Dropped']);

        $sprint = new Sprint();
        $sprint->assignTask($sprintId, $keptId);
        $sprint->assignTask($sprintId, $droppedId);
        $sprint->removeTaskFromSprint($sprintId, $droppedId);

        $this->assertSame([$keptId], $this->taskIdsIn($sprintId));
    }

    // ---- sprint lifecycle ------------------------------------------------

    public function testStartingASprintMakesItActive(): void
    {
        $sprintId = $this->createSprint($this->projectId, SprintStatus::PLANNING->value);

        $this->assertTrue((new Sprint())->startSprint($sprintId));
        $this->assertSame(SprintStatus::ACTIVE->value, $this->statusOf($sprintId));
    }

    /**
     * One active sprint per project is the rule the whole planning model rests
     * on — getActiveSprintForProject() returns a single row, so a second active
     * sprint would make "the current sprint" ambiguous.
     */
    public function testASecondSprintCannotBeStartedWhileOneIsActive(): void
    {
        $firstId = $this->createSprint($this->projectId, SprintStatus::PLANNING->value, 'First');
        $secondId = $this->createSprint($this->projectId, SprintStatus::PLANNING->value, 'Second');

        $sprint = new Sprint();
        $sprint->startSprint($firstId);

        try {
            $sprint->startSprint($secondId);
            $this->fail('A second sprint must not start while one is active.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already active', $e->getMessage());
        }

        $this->assertSame(
            SprintStatus::PLANNING->value,
            $this->statusOf($secondId),
            'The refused sprint must stay in planning.'
        );
    }

    public function testStartingTheAlreadyActiveSprintAgainIsAllowed(): void
    {
        $sprintId = $this->createSprint($this->projectId, SprintStatus::ACTIVE->value);

        $this->assertTrue(
            (new Sprint())->startSprint($sprintId),
            'The guard excludes the sprint being started, so this is not a conflict.'
        );
    }

    public function testCompletingASprintFreesTheProjectForTheNextOne(): void
    {
        $firstId = $this->createSprint($this->projectId, SprintStatus::ACTIVE->value, 'First');
        $secondId = $this->createSprint($this->projectId, SprintStatus::PLANNING->value, 'Second');

        $sprint = new Sprint();
        $sprint->completeSprint($firstId);

        $this->assertSame(SprintStatus::COMPLETED->value, $this->statusOf($firstId));
        $this->assertTrue($sprint->startSprint($secondId));
        $this->assertSame(SprintStatus::ACTIVE->value, $this->statusOf($secondId));
    }

    public function testActiveSprintLookupIgnoresOtherProjects(): void
    {
        $otherProjectId = $this->createProject($this->createCompany('Other Co'), 'Other Project');
        $this->createSprint($otherProjectId, SprintStatus::ACTIVE->value, 'Other Active');

        $this->assertNull(
            (new Sprint())->getActiveSprintForProject($this->projectId),
            'Another project having an active sprint must not make this one look busy.'
        );
    }

    public function testTheActiveSprintIsFoundForItsOwnProject(): void
    {
        $sprintId = $this->createSprint($this->projectId, SprintStatus::ACTIVE->value);

        $found = (new Sprint())->getActiveSprintForProject($this->projectId);

        $this->assertNotNull($found);
        $this->assertSame($sprintId, (int) $found->id);
    }
}
