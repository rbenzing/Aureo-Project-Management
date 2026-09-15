<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Setting;
use App\Models\Sprint;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

/**
 * Integration tests for Sprint's hand-written task SQL.
 *
 * getSprintTasksWithSubtasks() named :sprint_id twice in one statement — once
 * in the subtasks_in_sprint subquery, once in the outer WHERE — while binding
 * it once. A native prepare rejects that with "SQLSTATE[HY093]: Invalid
 * parameter number".
 *
 * This one fails *silently*: the method catches \Exception, writes to
 * error_log and returns []. A caller sees a sprint with no tasks rather than an
 * error, so the failure mode is missing data, not a stack trace. SprintTest
 * mocks Database and asserts exactly that empty array for its failure case,
 * which is why the defect survived — a mocked driver accepts any SQL.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(Sprint::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class SprintTaskQueryTest extends TestCase
{
    private int $companyId = 0;
    private int $projectId = 0;
    private int $sprintId = 0;
    private int $parentTaskId = 0;
    private int $subtaskId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $conn = $this->db->getConnection();

        $this->db->executeInsertUpdate(
            'INSERT INTO companies (guid, name, email) VALUES (UUID(), :name, :email)',
            [':name' => 'Sprint SQL Co ' . uniqid(), ':email' => 'sprint-' . uniqid() . '@example.test']
        );
        $this->companyId = (int) $conn->lastInsertId();

        $this->db->executeInsertUpdate(
            'INSERT INTO projects (guid, company_id, owner_id, status_id, name)
             VALUES (UUID(), :company, 1, 1, :name)',
            [':company' => $this->companyId, ':name' => 'Sprint SQL Project']
        );
        $this->projectId = (int) $conn->lastInsertId();

        $this->db->executeInsertUpdate(
            'INSERT INTO sprints (guid, project_id, name, start_date, end_date)
             VALUES (UUID(), :project, :name, CURDATE(), CURDATE())',
            [':project' => $this->projectId, ':name' => 'Sprint SQL Sprint']
        );
        $this->sprintId = (int) $conn->lastInsertId();

        $this->db->executeInsertUpdate(
            'INSERT INTO tasks (guid, project_id, title, is_subtask) VALUES (UUID(), :project, :title, 0)',
            [':project' => $this->projectId, ':title' => 'Parent task']
        );
        $this->parentTaskId = (int) $conn->lastInsertId();

        $this->db->executeInsertUpdate(
            'INSERT INTO tasks (guid, project_id, title, is_subtask, parent_task_id)
             VALUES (UUID(), :project, :title, 1, :parent)',
            [':project' => $this->projectId, ':title' => 'Child task', ':parent' => $this->parentTaskId]
        );
        $this->subtaskId = (int) $conn->lastInsertId();

        foreach ([$this->parentTaskId, $this->subtaskId] as $taskId) {
            $this->db->executeInsertUpdate(
                'INSERT INTO sprint_tasks (sprint_id, task_id) VALUES (:sprint, :task)',
                [':sprint' => $this->sprintId, ':task' => $taskId]
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->sprintId !== 0) {
            $this->db?->executeQuery('DELETE FROM sprint_tasks WHERE sprint_id = :id', [':id' => $this->sprintId]);
            $this->db?->executeQuery('DELETE FROM sprints WHERE id = :id', [':id' => $this->sprintId]);
        }
        foreach ([$this->subtaskId, $this->parentTaskId] as $taskId) {
            if ($taskId !== 0) {
                $this->db?->executeQuery('DELETE FROM tasks WHERE id = :id', [':id' => $taskId]);
            }
        }
        if ($this->projectId !== 0) {
            $this->db?->executeQuery('DELETE FROM projects WHERE id = :id', [':id' => $this->projectId]);
        }
        if ($this->companyId !== 0) {
            $this->db?->executeQuery('DELETE FROM companies WHERE id = :id', [':id' => $this->companyId]);
        }

        parent::tearDown();
    }

    /**
     * The empty array is what a broken statement also produces, so asserting
     * "not empty" is the only assertion that separates the two.
     */
    public function testGetSprintTasksWithSubtasksReturnsTheSprintsTasks(): void
    {
        $rows = (new Sprint())->getSprintTasksWithSubtasks($this->sprintId);

        $this->assertNotSame([], $rows, 'A sprint with tasks must not come back empty.');
        $this->assertCount(2, $rows);
    }

    /**
     * The repeated placeholder lived in the subtasks_in_sprint subquery, so the
     * count it produces is the value most worth pinning.
     */
    public function testSubtaskCountsAreScopedToTheSprint(): void
    {
        $rows = (new Sprint())->getSprintTasksWithSubtasks($this->sprintId);

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->id] = $row;
        }

        $this->assertSame(1, (int) $byId[$this->parentTaskId]->subtask_count);
        $this->assertSame(1, (int) $byId[$this->parentTaskId]->subtasks_in_sprint);
        $this->assertSame(0, (int) $byId[$this->subtaskId]->subtask_count);
    }
}
