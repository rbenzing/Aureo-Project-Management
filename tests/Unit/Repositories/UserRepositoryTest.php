<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserRepository::class)]
#[UsesClass(NotFoundException::class)]
class UserRepositoryTest extends TestCase
{
    /** @var User&MockObject */
    private User $modelMock;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = $this->createMock(User::class);
        $this->repository = new UserRepository($this->modelMock);
    }

    // -------------------------------------------------------------------------
    // find() / findOrFail()
    // -------------------------------------------------------------------------

    public function testFindReturnsObjectWhenModelFindsRecord(): void
    {
        $user = (object)['id' => 1, 'email' => 'a@example.com'];

        $this->modelMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $result = $this->repository->find(1);

        $this->assertSame($user, $result);
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
        $user = (object)['id' => 5, 'email' => 'b@example.com'];

        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(5)
            ->willReturn($user);

        $result = $this->repository->findOrFail(5);

        $this->assertSame($user, $result);
    }

    public function testFindOrFailPropagatesNotFoundException(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findOrFail')
            ->with(404)
            ->willThrowException(NotFoundException::forModel('User', 404));

        $this->expectException(NotFoundException::class);

        $this->repository->findOrFail(404);
    }

    // -------------------------------------------------------------------------
    // findWithDetails() / findByEmail()
    // -------------------------------------------------------------------------

    public function testFindWithDetailsDelegatesOptionsToModel(): void
    {
        $user = (object)['id' => 2, 'email' => 'c@example.com'];
        $options = ['projects' => true];

        $this->modelMock
            ->expects($this->once())
            ->method('findWithDetails')
            ->with(2, $options)
            ->willReturn($user);

        $result = $this->repository->findWithDetails(2, $options);

        $this->assertSame($user, $result);
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

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $user = (object)['id' => 1, 'email' => 'd@example.com'];

        $this->modelMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with('d@example.com')
            ->willReturn($user);

        $result = $this->repository->findByEmail('d@example.com');

        $this->assertSame($user, $result);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with('missing@example.com')
            ->willReturn(null);

        $result = $this->repository->findByEmail('missing@example.com');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getAll() — branches on default vs. custom filters/page/limit
    // -------------------------------------------------------------------------

    public function testGetAllWithNoArgumentsUsesGetAllUsersFastPath(): void
    {
        $users = [(object)['id' => 1], (object)['id' => 2]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAllUsers')
            ->willReturn($users);

        $this->modelMock->expects($this->never())->method('getAll');

        $result = $this->repository->getAll();

        $this->assertSame($users, $result);
    }

    public function testGetAllWithFiltersUsesGenericGetAll(): void
    {
        $filters = ['role_id' => 2];
        $expected = ['records' => [], 'total' => 0];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with($filters, 1, 10)
            ->willReturn($expected);

        $this->modelMock->expects($this->never())->method('getAllUsers');

        $result = $this->repository->getAll($filters);

        $this->assertSame($expected, $result);
    }

    public function testGetAllWithNonDefaultPageUsesGenericGetAll(): void
    {
        $expected = ['records' => [], 'total' => 0];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with([], 2, 10)
            ->willReturn($expected);

        $this->modelMock->expects($this->never())->method('getAllUsers');

        $result = $this->repository->getAll([], 2);

        $this->assertSame($expected, $result);
    }

    public function testGetAllWithNonDefaultLimitUsesGenericGetAll(): void
    {
        $expected = ['records' => [], 'total' => 0];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with([], 1, 50)
            ->willReturn($expected);

        $this->modelMock->expects($this->never())->method('getAllUsers');

        $result = $this->repository->getAll([], 1, 50);

        $this->assertSame($expected, $result);
    }

    // -------------------------------------------------------------------------
    // getByRole() / getByCompany() / getActiveUsers()
    // -------------------------------------------------------------------------

    public function testGetByRoleReturnsRecordsFromModel(): void
    {
        $records = [(object)['id' => 1, 'role_id' => 3]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['role_id' => 3])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getByRole(3);

        $this->assertSame($records, $result);
    }

    public function testGetByRoleReturnsEmptyArrayWhenRecordsKeyMissing(): void
    {
        $this->modelMock->method('getAll')->willReturn(['total' => 0]);

        $result = $this->repository->getByRole(3);

        $this->assertSame([], $result);
    }

    public function testGetByCompanyReturnsRecordsFromModel(): void
    {
        $records = [(object)['id' => 1, 'company_id' => 8]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['company_id' => 8])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getByCompany(8);

        $this->assertSame($records, $result);
    }

    public function testGetByCompanyReturnsEmptyArrayWhenRecordsKeyMissing(): void
    {
        $this->modelMock->method('getAll')->willReturn([]);

        $result = $this->repository->getByCompany(8);

        $this->assertSame([], $result);
    }

    public function testGetActiveUsersReturnsRecordsFromModel(): void
    {
        $records = [(object)['id' => 1, 'is_active' => 1]];

        $this->modelMock
            ->expects($this->once())
            ->method('getAll')
            ->with(['is_active' => 1])
            ->willReturn(['records' => $records]);

        $result = $this->repository->getActiveUsers();

        $this->assertSame($records, $result);
    }

    public function testGetActiveUsersReturnsEmptyArrayWhenRecordsKeyMissing(): void
    {
        $this->modelMock->method('getAll')->willReturn([]);

        $result = $this->repository->getActiveUsers();

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // create() / update() / delete()
    // -------------------------------------------------------------------------

    public function testCreateDelegatesDataAndReturnsNewId(): void
    {
        $data = ['email' => 'new@example.com'];

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
        $data = ['email' => 'updated@example.com'];

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
    // getUserProjects() / getUserActiveTasks() / getRolesAndPermissions()
    // -------------------------------------------------------------------------

    public function testGetUserProjectsDelegatesToModel(): void
    {
        $projects = [(object)['id' => 1]];

        $this->modelMock
            ->expects($this->once())
            ->method('getUserProjects')
            ->with(5)
            ->willReturn($projects);

        $result = $this->repository->getUserProjects(5);

        $this->assertSame($projects, $result);
    }

    public function testGetUserProjectsReturnsEmptyArrayWhenUserHasNone(): void
    {
        $this->modelMock->method('getUserProjects')->willReturn([]);

        $result = $this->repository->getUserProjects(5);

        $this->assertSame([], $result);
    }

    public function testGetUserActiveTasksUsesDefaultLimit(): void
    {
        $tasks = [(object)['id' => 1]];

        $this->modelMock
            ->expects($this->once())
            ->method('getUserActiveTasks')
            ->with(5, 5)
            ->willReturn($tasks);

        $result = $this->repository->getUserActiveTasks(5);

        $this->assertSame($tasks, $result);
    }

    public function testGetUserActiveTasksRespectsCustomLimit(): void
    {
        $tasks = [(object)['id' => 1], (object)['id' => 2]];

        $this->modelMock
            ->expects($this->once())
            ->method('getUserActiveTasks')
            ->with(5, 20)
            ->willReturn($tasks);

        $result = $this->repository->getUserActiveTasks(5, 20);

        $this->assertSame($tasks, $result);
    }

    public function testGetRolesAndPermissionsDelegatesToModel(): void
    {
        $expected = ['roles' => ['Admin'], 'permissions' => ['manage_users']];

        $this->modelMock
            ->expects($this->once())
            ->method('getRolesAndPermissions')
            ->with(5)
            ->willReturn($expected);

        $result = $this->repository->getRolesAndPermissions(5);

        $this->assertSame($expected, $result);
    }

    // -------------------------------------------------------------------------
    // addProject() / removeProject()
    // -------------------------------------------------------------------------

    public function testAddProjectDelegatesAndReturnsTrueOnSuccess(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('addProject')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->repository->addProject(1, 2);

        $this->assertTrue($result);
    }

    public function testAddProjectReturnsFalseWhenModelFails(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('addProject')
            ->with(1, 2)
            ->willReturn(false);

        $result = $this->repository->addProject(1, 2);

        $this->assertFalse($result);
    }

    public function testRemoveProjectDelegatesAndReturnsTrueOnSuccess(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('removeProject')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->repository->removeProject(1, 2);

        $this->assertTrue($result);
    }

    public function testRemoveProjectReturnsFalseWhenModelFails(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('removeProject')
            ->with(1, 2)
            ->willReturn(false);

        $result = $this->repository->removeProject(1, 2);

        $this->assertFalse($result);
    }
}
