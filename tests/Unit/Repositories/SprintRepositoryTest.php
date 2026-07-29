<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Exceptions\NotFoundException;
use App\Models\Sprint;
use App\Repositories\SprintRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SprintRepository::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(SprintStatus::class)]
#[UsesClass(TaskStatus::class)]
class SprintRepositoryTest extends TestCase
{
    /** @var Sprint&MockObject */
    private Sprint $modelMock;

    private SprintRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = $this->createMock(Sprint::class);
        $this->repository = new SprintRepository($this->modelMock);
    }

    // -------------------------------------------------------------------------
    // find() / findOrFail()
    // -------------------------------------------------------------------------

    public function testFindReturnsObjectWhenModelFindsRecord(): void
    {
        $sprint = (object)['id' => 1, 'name' => 'Sprint 1'];

        $this->modelMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sprint);

        $result = $this->repository->find(1);

        $this->assertSame($sprint, $result);
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
        $sprint = (object)['id' => 5, 'name' => 'Sprint 5'];

        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(5)
            ->willReturn($sprint);

        $result = $this->repository->findOrFail(5);

        $this->assertSame($sprint, $result);
    }

    public function testFindOrFailPropagatesNotFoundException(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(404)
            ->willThrowException(NotFoundException::forModel('Sprint', 404));

        $this->expectException(NotFoundException::class);

        $this->repository->findOrFail(404);
    }

    // -------------------------------------------------------------------------
    // findWithDetails()
    // -------------------------------------------------------------------------

    public function testFindWithDetailsDelegatesOptionsToModel(): void
    {
        $sprint = (object)['id' => 2, 'name' => 'Sprint 2'];
        $options = ['tasks' => true];

        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->with(2, $options)
            ->willReturn($sprint);

        $result = $this->repository->findWithDetails(2, $options);

        $this->assertSame($sprint, $result);
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
        $filters = ['project_id' => 7];
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
    // getByProject()
    // -------------------------------------------------------------------------

    public function testGetByProjectDelegatesToGetByProjectId(): void
    {
        $sprints = [(object)['id' => 1, 'project_id' => 9]];

        $this->modelMock
            ->expects($this->once())
            ->method('getByProjectId')
            ->with(9)
            ->willReturn($sprints);

        $result = $this->repository->getByProject(9);

        $this->assertSame($sprints, $result);
    }

    public function testGetByProjectReturnsEmptyArrayWhenNoneFound(): void
    {
        $this->modelMock
            ->method('getByProjectId')
            ->willReturn([]);

        $result = $this->repository->getByProject(1);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // getByStatus() / getActiveSprints()
    // -------------------------------------------------------------------------

    public function testGetByStatusReturnsRecordsFromModel(): void
    {
        $records = [(object)['id' => 1, 'status_id' => SprintStatus::COMPLETED->value]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['status_id' => SprintStatus::COMPLETED->value])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getByStatus(SprintStatus::COMPLETED);

        $this->assertSame($records, $result);
    }

    public function testGetByStatusReturnsEmptyArrayWhenRecordsKeyMissing(): void
    {
        $this->modelMock->method('getAll')->willReturn(['total' => 0]);

        $result = $this->repository->getByStatus(SprintStatus::CANCELLED);

        $this->assertSame([], $result);
    }

    public function testGetActiveSprintsUsesActiveStatus(): void
    {
        $records = [(object)['id' => 9]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['status_id' => SprintStatus::ACTIVE->value])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getActiveSprints();

        $this->assertSame($records, $result);
    }

    // -------------------------------------------------------------------------
    // getCurrentSprint()
    // -------------------------------------------------------------------------

    public function testGetCurrentSprintReturnsFirstActiveSprintForProject(): void
    {
        $sprint = (object)['id' => 3, 'project_id' => 1, 'status_id' => SprintStatus::ACTIVE->value];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with([
                'project_id' => 1,
                'status_id' => SprintStatus::ACTIVE->value,
            ])
            ->willReturn(['records' => [$sprint]]);

        $result = $this->repository->getCurrentSprint(1);

        $this->assertSame($sprint, $result);
    }

    public function testGetCurrentSprintReturnsNullWhenNoneActive(): void
    {
        $this->modelMock->method('getAll')->willReturn(['records' => []]);

        $result = $this->repository->getCurrentSprint(1);

        $this->assertNull($result);
    }

    public function testGetCurrentSprintReturnsNullWhenRecordsKeyMissing(): void
    {
        $this->modelMock->method('getAll')->willReturn([]);

        $result = $this->repository->getCurrentSprint(1);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // create() / update() / delete()
    // -------------------------------------------------------------------------

    public function testCreateDelegatesDataAndReturnsNewId(): void
    {
        $data = ['name' => 'New Sprint'];

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
        $conditions = ['project_id' => 3];

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
    // removeTask()
    // -------------------------------------------------------------------------

    public function testRemoveTaskDelegatesAndReturnsTrueOnSuccess(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('removeTask')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->repository->removeTask(1, 2);

        $this->assertTrue($result);
    }

    public function testRemoveTaskReturnsFalseWhenModelFails(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('removeTask')
            ->with(1, 2)
            ->willReturn(false);

        $result = $this->repository->removeTask(1, 2);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // getStatistics()
    // -------------------------------------------------------------------------

    public function testGetStatisticsReturnsEmptyArrayWhenSprintNotFound(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->willReturn(null);

        $result = $this->repository->getStatistics(1);

        $this->assertSame([], $result);
    }

    public function testGetStatisticsComputesTotalsAndVelocity(): void
    {
        $sprint = (object)[
            'id' => 1,
            'tasks' => [
                (object)['status_id' => TaskStatus::COMPLETED->value, 'story_points' => 5, 'time_spent' => 100],
                (object)['status_id' => TaskStatus::COMPLETED->value, 'story_points' => 3, 'time_spent' => 40],
                (object)['status_id' => TaskStatus::OPEN->value, 'story_points' => 2, 'time_spent' => 10],
            ],
        ];

        $this->modelMock->method('findWithDetails')->willReturn($sprint);

        $result = $this->repository->getStatistics(1);

        $this->assertSame(3, $result['total_tasks']);
        $this->assertSame(2, $result['completed_tasks']);
        $this->assertSame(10, $result['total_story_points']);
        $this->assertSame(8, $result['completed_story_points']);
        $this->assertSame(150, $result['total_time_spent']);
        $this->assertSame(66.67, $result['completion_rate']);
        $this->assertSame(8, $result['velocity']);
    }

    public function testGetStatisticsReturnsZeroCompletionRateWhenNoTasks(): void
    {
        $sprint = (object)['id' => 1];

        $this->modelMock->method('findWithDetails')->willReturn($sprint);

        $result = $this->repository->getStatistics(1);

        $this->assertSame(0, $result['total_tasks']);
        $this->assertSame(0, $result['completed_tasks']);
        $this->assertSame(0, $result['total_story_points']);
        $this->assertSame(0, $result['completed_story_points']);
        $this->assertSame(0, $result['total_time_spent']);
        $this->assertSame(0, $result['completion_rate']);
        $this->assertSame(0, $result['velocity']);
    }

    public function testGetStatisticsDefaultsMissingStoryPointsAndTimeSpentToZero(): void
    {
        $sprint = (object)[
            'id' => 1,
            'tasks' => [
                (object)['status_id' => TaskStatus::COMPLETED->value],
            ],
        ];

        $this->modelMock->method('findWithDetails')->willReturn($sprint);

        $result = $this->repository->getStatistics(1);

        $this->assertSame(1, $result['total_tasks']);
        $this->assertSame(1, $result['completed_tasks']);
        $this->assertSame(0, $result['total_story_points']);
        $this->assertSame(0, $result['completed_story_points']);
        $this->assertSame(0, $result['total_time_spent']);
        $this->assertSame(100.0, $result['completion_rate']);
        $this->assertSame(0, $result['velocity']);
    }
}
