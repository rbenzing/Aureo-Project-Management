<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BaseModel;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\ProjectService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\DomainFixtures;
use Tests\Support\TestCase;

/**
 * Integration tests for the project status machine against a real database.
 *
 * Added alongside the M1 work because ProjectService carried the same defect as
 * TaskService: transitionStatus() wrote a completed_at field when a project was
 * completed, and `projects` has no such column — it has start_date and end_date
 * and nothing else. Since prepareSaveData() filters only $guarded, that unknown
 * field reached the SQL and every completion failed. Unlike tasks there was no
 * column to rename it to, so the write is gone; status_id is what records that
 * a project is complete.
 *
 * ProjectService has no controller callers today, so the breakage was latent —
 * which is exactly why it needed a test rather than a fix on its own.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(ProjectService::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Project::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(ProjectStatus::class)]
#[UsesClass(BusinessRuleException::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class ProjectWorkflowTest extends TestCase
{
    use DomainFixtures;

    private int $companyId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->companyId = $this->createCompany();
    }

    protected function tearDown(): void
    {
        $this->removeFixtures();

        parent::tearDown();
    }

    private function projectWithStatus(int $statusId): int
    {
        $projectId = $this->createProject($this->companyId);

        $this->db->executeInsertUpdate(
            'UPDATE projects SET status_id = :status WHERE id = :id',
            [':status' => $statusId, ':id' => $projectId]
        );

        return $projectId;
    }

    private function statusOf(int $projectId): int
    {
        return (int) $this->db->executeQuery(
            'SELECT status_id FROM projects WHERE id = :id',
            [':id' => $projectId]
        )->fetch(\PDO::FETCH_OBJ)->status_id;
    }

    public function testAPermittedTransitionIsPersisted(): void
    {
        $projectId = $this->projectWithStatus(ProjectStatus::READY->value);

        (new ProjectService())->transitionStatus($projectId, ProjectStatus::IN_PROGRESS);

        $this->assertSame(ProjectStatus::IN_PROGRESS->value, $this->statusOf($projectId));
    }

    /**
     * The case that used to fail outright: completing a project wrote a column
     * that does not exist, so the statement was rejected and the project stayed
     * in progress for ever.
     */
    public function testAProjectCanBeCompleted(): void
    {
        $projectId = $this->projectWithStatus(ProjectStatus::IN_PROGRESS->value);

        (new ProjectService())->transitionStatus($projectId, ProjectStatus::COMPLETED);

        $this->assertSame(ProjectStatus::COMPLETED->value, $this->statusOf($projectId));
    }

    public function testACompletedProjectCanBeReopened(): void
    {
        $projectId = $this->projectWithStatus(ProjectStatus::COMPLETED->value);

        (new ProjectService())->transitionStatus($projectId, ProjectStatus::IN_PROGRESS);

        $this->assertSame(ProjectStatus::IN_PROGRESS->value, $this->statusOf($projectId));
    }

    public function testAForbiddenTransitionIsRefusedAndChangesNothing(): void
    {
        $projectId = $this->projectWithStatus(ProjectStatus::READY->value);

        try {
            (new ProjectService())->transitionStatus($projectId, ProjectStatus::COMPLETED);
            $this->fail('Ready straight to completed must not be permitted.');
        } catch (BusinessRuleException) {
            // expected
        }

        $this->assertSame(ProjectStatus::READY->value, $this->statusOf($projectId));
    }
}
