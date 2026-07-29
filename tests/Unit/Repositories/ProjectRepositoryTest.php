<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Exceptions\NotFoundException;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectRepository::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(ProjectStatus::class)]
#[UsesClass(TaskStatus::class)]
class ProjectRepositoryTest extends TestCase
{
    /** @var Project&MockObject */
    private Project $modelMock;

    private ProjectRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = $this->createMock(Project::class);
        $this->repository = new ProjectRepository($this->modelMock);
    }

    // -------------------------------------------------------------------------
    // find() / findOrFail()
    // -------------------------------------------------------------------------

    public function testFindReturnsObjectWhenModelFindsRecord(): void
    {
        $project = (object)['id' => 1, 'name' => 'Alpha'];

        $this->modelMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($project);

        $result = $this->repository->find(1);

        $this->assertSame($project, $result);
    }

    public function testFindReturnsNullWhenModelReturnsFalse(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(false);

        $result = $this->repository->find(999);

        $this->assertNull($result);
    }

    public function testFindOrFailReturnsObjectWhenModelSucceeds(): void
    {
        $project = (object)['id' => 5, 'name' => 'Beta'];

        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(5)
            ->willReturn($project);

        $result = $this->repository->findOrFail(5);

        $this->assertSame($project, $result);
    }

    public function testFindOrFailPropagatesNotFoundException(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(404)
            ->willThrowException(NotFoundException::forModel('Project', 404));

        $this->expectException(NotFoundException::class);

        $this->repository->findOrFail(404);
    }

    // -------------------------------------------------------------------------
    // findWithDetails()
    // -------------------------------------------------------------------------

    public function testFindWithDetailsDelegatesOptionsToModel(): void
    {
        $project = (object)['id' => 2, 'name' => 'Gamma'];
        $options = ['tasks' => true];

        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->with(2, $options)
            ->willReturn($project);

        $result = $this->repository->findWithDetails(2, $options);

        $this->assertSame($project, $result);
    }

    public function testFindWithDetailsReturnsNullWhenModelReturnsNull(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->with(3, [])
            ->willReturn(null);

        $result = $this->repository->findWithDetails(3);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getAll()
    // -------------------------------------------------------------------------

    public function testGetAllDelegatesFiltersPageAndLimitToModel(): void
    {
        $filters = ['company_id' => 7];
        $expected = ['records' => [(object)['id' => 1]], 'total' => 1];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with($filters, 2, 25)
            ->willReturn($expected);

        $result = $this->repository->getAll($filters, 2, 25);

        $this->assertSame($expected, $result);
    }

    public function testGetAllUsesDefaultPageAndLimit(): void
    {
        $expected = ['records' => [], 'total' => 0];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with([], 1, 10)
            ->willReturn($expected);

        $result = $this->repository->getAll();

        $this->assertSame($expected, $result);
    }

    // -------------------------------------------------------------------------
    // getByStatus() / getActiveProjects()
    // -------------------------------------------------------------------------

    public function testGetByStatusReturnsRecordsFromModel(): void
    {
        $records = [(object)['id' => 1, 'status_id' => ProjectStatus::COMPLETED->value]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['status_id' => ProjectStatus::COMPLETED->value])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getByStatus(ProjectStatus::COMPLETED);

        $this->assertSame($records, $result);
    }

    public function testGetByStatusReturnsEmptyArrayWhenRecordsKeyMissing(): void
    {
        $this->modelMock
            ->method('getAll')
            ->willReturn(['total' => 0]);

        $result = $this->repository->getByStatus(ProjectStatus::CANCELLED);

        $this->assertSame([], $result);
    }

    public function testGetActiveProjectsUsesInProgressStatus(): void
    {
        $records = [(object)['id' => 9]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['status_id' => ProjectStatus::IN_PROGRESS->value])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getActiveProjects();

        $this->assertSame($records, $result);
    }

    public function testGetActiveProjectsReturnsEmptyArrayWhenNoRecordsKey(): void
    {
        $this->modelMock->method('getAll')->willReturn([]);

        $result = $this->repository->getActiveProjects();

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // create() / update() / delete()
    // -------------------------------------------------------------------------

    public function testCreateDelegatesDataAndReturnsNewId(): void
    {
        $data = ['name' => 'New Project'];

        $this->modelMock
            ->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn(42);

        $result = $this->repository->create($data);

        $this->assertSame(42, $result);
    }

    public function testUpdateDelegatesAndReturnsTrueOnSuccess(): void
    {
        $data = ['name' => 'Renamed'];

        $this->modelMock
            ->expects($this->once())
            ->method('update')
            ->with(1, $data)
            ->willReturn(true);

        $result = $this->repository->update(1, $data);

        $this->assertTrue($result);
    }

    public function testUpdateReturnsFalseWhenModelFails(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('update')
            ->with(1, [])
            ->willReturn(false);

        $result = $this->repository->update(1, []);

        $this->assertFalse($result);
    }

    public function testDeleteDelegatesAndReturnsTrueOnSuccess(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $result = $this->repository->delete(1);

        $this->assertTrue($result);
    }

    public function testDeleteReturnsFalseWhenModelFails(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('delete')
            ->with(999)
            ->willReturn(false);

        $result = $this->repository->delete(999);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // count()
    // -------------------------------------------------------------------------

    public function testCountDelegatesConditionsToModel(): void
    {
        $conditions = ['company_id' => 3];

        $this->modelMock
            ->expects($this->once())
            ->method('count')
            ->with($conditions)
            ->willReturn(7);

        $result = $this->repository->count($conditions);

        $this->assertSame(7, $result);
    }

    public function testCountWithNoConditionsUsesEmptyArrayDefault(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(0);

        $result = $this->repository->count();

        $this->assertSame(0, $result);
    }

    // -------------------------------------------------------------------------
    // exists()
    // -------------------------------------------------------------------------

    public function testExistsReturnsTrueWhenRecordFound(): void
    {
        $this->modelMock
            ->method('find')
            ->with(1)
            ->willReturn((object)['id' => 1]);

        $this->assertTrue($this->repository->exists(1));
    }

    public function testExistsReturnsFalseWhenRecordNotFound(): void
    {
        $this->modelMock
            ->method('find')
            ->with(404)
            ->willReturn(false);

        $this->assertFalse($this->repository->exists(404));
    }

    // -------------------------------------------------------------------------
    // getStatistics()
    // -------------------------------------------------------------------------

    public function testGetStatisticsReturnsEmptyArrayWhenProjectNotFound(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->willReturn(null);

        $result = $this->repository->getStatistics(1);

        $this->assertSame([], $result);
    }

    public function testGetStatisticsComputesCountsAndCompletionRate(): void
    {
        $project = (object)[
            'id' => 1,
            'tasks' => [
                (object)['status_id' => TaskStatus::COMPLETED->value, 'time_spent' => 100],
                (object)['status_id' => TaskStatus::OPEN->value, 'time_spent' => 50],
            ],
            'milestones' => [(object)['id' => 1], (object)['id' => 2]],
            'sprints' => [(object)['id' => 1]],
            'team_members' => [(object)['id' => 1], (object)['id' => 2], (object)['id' => 3]],
        ];

        $this->modelMock->method('findWithDetails')->willReturn($project);

        $result = $this->repository->getStatistics(1);

        $this->assertSame(2, $result['total_tasks']);
        $this->assertSame(1, $result['completed_tasks']);
        $this->assertSame(2, $result['total_milestones']);
        $this->assertSame(1, $result['total_sprints']);
        $this->assertSame(3, $result['team_size']);
        $this->assertSame(150, $result['total_time_spent']);
        $this->assertSame(50.0, $result['completion_rate']);
    }

    public function testGetStatisticsReturnsZeroCompletionRateWhenNoTasks(): void
    {
        $project = (object)[
            'id' => 1,
            'milestones' => [],
            'sprints' => [],
            'team_members' => [],
        ];

        $this->modelMock->method('findWithDetails')->willReturn($project);

        $result = $this->repository->getStatistics(1);

        $this->assertSame(0, $result['total_tasks']);
        $this->assertSame(0, $result['completed_tasks']);
        $this->assertSame(0, $result['total_time_spent']);
        $this->assertSame(0, $result['completion_rate']);
    }

    public function testGetStatisticsDefaultsMissingTimeSpentToZero(): void
    {
        $project = (object)[
            'id' => 1,
            'tasks' => [
                (object)['status_id' => TaskStatus::COMPLETED->value],
            ],
        ];

        $this->modelMock->method('findWithDetails')->willReturn($project);

        $result = $this->repository->getStatistics(1);

        $this->assertSame(1, $result['total_tasks']);
        $this->assertSame(1, $result['completed_tasks']);
        $this->assertSame(0, $result['total_time_spent']);
        $this->assertSame(100.0, $result['completion_rate']);
    }
}
