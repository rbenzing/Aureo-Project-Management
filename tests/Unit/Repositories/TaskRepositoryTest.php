<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\TaskStatus;
use App\Exceptions\NotFoundException;
use App\Models\Task;
use App\Repositories\TaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskRepository::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(TaskStatus::class)]
class TaskRepositoryTest extends TestCase
{
    /** @var Task&MockObject */
    private Task $modelMock;

    private TaskRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = $this->createMock(Task::class);
        $this->repository = new TaskRepository($this->modelMock);
    }

    // -------------------------------------------------------------------------
    // find() / findOrFail()
    // -------------------------------------------------------------------------

    public function testFindReturnsObjectWhenModelFindsRecord(): void
    {
        $task = (object)['id' => 1, 'title' => 'Fix bug'];

        $this->modelMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($task);

        $result = $this->repository->find(1);

        $this->assertSame($task, $result);
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
        $task = (object)['id' => 5, 'title' => 'Ship feature'];

        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(5)
            ->willReturn($task);

        $result = $this->repository->findOrFail(5);

        $this->assertSame($task, $result);
    }

    public function testFindOrFailPropagatesNotFoundException(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(404)
            ->willThrowException(NotFoundException::forModel('Task', 404));

        $this->expectException(NotFoundException::class);

        $this->repository->findOrFail(404);
    }

    // -------------------------------------------------------------------------
    // findWithDetails()
    // -------------------------------------------------------------------------

    public function testFindWithDetailsReturnsObjectWhenFound(): void
    {
        $task = (object)['id' => 2, 'title' => 'Detailed task'];

        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->with(2)
            ->willReturn($task);

        $result = $this->repository->findWithDetails(2);

        $this->assertSame($task, $result);
    }

    public function testFindWithDetailsReturnsNullWhenModelReturnsNull(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->with(3)
            ->willReturn(null);

        $result = $this->repository->findWithDetails(3);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getAll() — ignores $filters, delegates to getAllWithDetails($limit, $page)
    // -------------------------------------------------------------------------

    public function testGetAllUsesDefaultLimitAndPage(): void
    {
        $tasks = [(object)['id' => 1], (object)['id' => 2]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAllWithDetails')
            ->with(10, 1)
            ->willReturn($tasks);

        $result = $this->repository->getAll();

        $this->assertSame($tasks, $result);
    }

    public function testGetAllForwardsCustomPageAndLimitButIgnoresFilters(): void
    {
        $tasks = [(object)['id' => 1]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAllWithDetails')
            ->with(20, 2)
            ->willReturn($tasks);

        $result = $this->repository->getAll(['status_id' => 1], 2, 20);

        $this->assertSame($tasks, $result);
    }

    public function testGetAllReturnsEmptyArrayWhenModelReturnsNull(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('getAllWithDetails')
            ->willReturn(null);

        $result = $this->repository->getAll();

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // getByUserId()
    // -------------------------------------------------------------------------

    public function testGetByUserIdUsesDefaultLimitAndPage(): void
    {
        $tasks = [(object)['id' => 1, 'assigned_to' => 7]];

        $this->modelMock
            ->expects($this->once())
            ->method('getByUserId')
            ->with(7, 10, 1)
            ->willReturn($tasks);

        $result = $this->repository->getByUserId(7);

        $this->assertSame($tasks, $result);
    }

    public function testGetByUserIdForwardsCustomLimitAndPage(): void
    {
        $tasks = [(object)['id' => 1]];

        $this->modelMock
            ->expects($this->once())
            ->method('getByUserId')
            ->with(7, 5, 3)
            ->willReturn($tasks);

        $result = $this->repository->getByUserId(7, 5, 3);

        $this->assertSame($tasks, $result);
    }

    public function testGetByUserIdReturnsEmptyArrayWhenUserHasNoTasks(): void
    {
        $this->modelMock->method('getByUserId')->willReturn([]);

        $result = $this->repository->getByUserId(7);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // create() / update() / delete()
    // -------------------------------------------------------------------------

    public function testCreateDelegatesDataAndReturnsNewId(): void
    {
        $data = ['title' => 'New Task'];

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
        $data = ['title' => 'Renamed'];

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
    // getStatistics()
    // -------------------------------------------------------------------------

    public function testGetStatisticsWithoutProjectIdComputesRates(): void
    {
        $capturedConditions = [];

        $this->modelMock
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(function (array $conditions) use (&$capturedConditions): int {
                $capturedConditions[] = $conditions;

                if (!isset($conditions['status_id'])) {
                    return 20;
                }

                if ($conditions['status_id'] === TaskStatus::COMPLETED->value) {
                    return 8;
                }

                return 5;
            });

        $result = $this->repository->getStatistics();

        $this->assertSame(['is_deleted' => 0], $capturedConditions[0]);
        $this->assertArrayNotHasKey('project_id', $capturedConditions[0]);
        $this->assertSame(20, $result['total']);
        $this->assertSame(8, $result['completed']);
        $this->assertSame(5, $result['in_progress']);
        $this->assertSame(40.0, $result['completion_rate']);
    }

    public function testGetStatisticsWithProjectIdAddsProjectFilter(): void
    {
        $capturedConditions = [];

        $this->modelMock
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(function (array $conditions) use (&$capturedConditions): int {
                $capturedConditions[] = $conditions;

                if (!isset($conditions['status_id'])) {
                    return 10;
                }

                if ($conditions['status_id'] === TaskStatus::COMPLETED->value) {
                    return 4;
                }

                return 3;
            });

        $result = $this->repository->getStatistics(9);

        $this->assertSame(['is_deleted' => 0, 'project_id' => 9], $capturedConditions[0]);
        $this->assertSame(10, $result['total']);
        $this->assertSame(4, $result['completed']);
        $this->assertSame(3, $result['in_progress']);
        $this->assertSame(40.0, $result['completion_rate']);
    }

    public function testGetStatisticsReturnsZeroCompletionRateWhenNoTasksExist(): void
    {
        $this->modelMock->method('count')->willReturn(0);

        $result = $this->repository->getStatistics();

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['completed']);
        $this->assertSame(0, $result['in_progress']);
        $this->assertSame(0, $result['completion_rate']);
    }
}
