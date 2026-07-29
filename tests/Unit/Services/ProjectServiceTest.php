<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\ProjectService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for ProjectService
 *
 * All collaborators (Project, User, Task models and LoggerService) are mocked so no
 * live database connection is required. Project has no getTeamMembers()/addTeamMember()/
 * removeTeamMember() methods of its own (those live on ProjectRepository), so the mock
 * adds them via addMethods() purely to exercise ProjectService's own team-member logic.
 */
#[CoversClass(ProjectService::class)]
#[UsesClass(ProjectStatus::class)]
#[UsesClass(TaskStatus::class)]
#[UsesClass(BusinessRuleException::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(ValidationException::class)]
final class ProjectServiceTest extends TestCase
{
    private Project&\PHPUnit\Framework\MockObject\MockObject $projectModel;
    private User&\PHPUnit\Framework\MockObject\MockObject $userModel;
    private Task&\PHPUnit\Framework\MockObject\MockObject $taskModel;
    private LoggerService&\PHPUnit\Framework\MockObject\MockObject $logger;
    private ProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectModel = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOrFail', 'create', 'update', 'count', 'findWithDetails'])
            ->addMethods(['getTeamMembers', 'addTeamMember', 'removeTeamMember'])
            ->getMock();

        $this->userModel = $this->createMock(User::class);
        $this->taskModel = $this->createMock(Task::class);
        $this->logger = $this->createMock(LoggerService::class);

        $this->service = new ProjectService(
            $this->projectModel,
            $this->userModel,
            $this->taskModel,
            $this->logger
        );
    }

    public function testCreateProjectFailsWithoutName(): void
    {
        $this->userModel->expects($this->never())->method('findOrFail');

        try {
            $this->service->createProject(['owner_id' => 1]);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors());
            $this->assertArrayNotHasKey('owner_id', $e->getErrors());
        }
    }

    public function testCreateProjectFailsWithoutOwner(): void
    {
        try {
            $this->service->createProject(['name' => 'New Project']);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('owner_id', $e->getErrors());
            $this->assertArrayNotHasKey('name', $e->getErrors());
        }
    }

    public function testCreateProjectFailsWithoutNameAndOwner(): void
    {
        try {
            $this->service->createProject([]);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors());
            $this->assertArrayHasKey('owner_id', $e->getErrors());
        }
    }

    public function testCreateProjectFailsWhenOwnerDoesNotExist(): void
    {
        $this->userModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('User', 99));

        try {
            $this->service->createProject(['name' => 'New Project', 'owner_id' => 99]);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertSame(['Owner user not found'], $e->getFieldErrors('owner_id'));
        }
    }

    public function testCreateProjectGeneratesKeyCodeAndDefaults(): void
    {
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->expects($this->once())->method('count')->willReturn(0);

        $this->projectModel->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['name'] === 'Website Revamp'
                    && $data['owner_id'] === 5
                    && $data['status_id'] === ProjectStatus::READY->value
                    && $data['key_code'] === 'WR'
                    && isset($data['created_at'], $data['updated_at']);
            }))
            ->willReturn(42);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Project #42 created'));

        $id = $this->service->createProject(['name' => 'Website Revamp', 'owner_id' => 5]);

        $this->assertSame(42, $id);
    }

    public function testCreateProjectKeepsProvidedKeyCodeWithoutGenerating(): void
    {
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->expects($this->never())->method('count');

        $this->projectModel->expects($this->once())
            ->method('create')
            ->with($this->callback(fn (array $data): bool => $data['key_code'] === 'CUSTOM'))
            ->willReturn(10);

        $id = $this->service->createProject([
            'name' => 'A Project',
            'owner_id' => 5,
            'key_code' => 'CUSTOM',
        ]);

        $this->assertSame(10, $id);
    }

    public function testCreateProjectThrowsWhenCreateFails(): void
    {
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->method('count')->willReturn(0);
        $this->projectModel->method('create')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create project');

        $this->service->createProject(['name' => 'Broken', 'owner_id' => 5]);
    }

    public function testUpdateKeyCodeThrowsWhenProjectMissing(): void
    {
        $this->projectModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Project', 1));

        $this->expectException(NotFoundException::class);

        $this->service->updateKeyCode(1, 'ABCD');
    }

    public function testUpdateKeyCodeGeneratesAutomaticallyWhenNull(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'X', 'key_code' => 'OLD']);
        $this->projectModel->method('count')->willReturn(0);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['key_code'] === 'X'))
            ->willReturn(true);

        $this->service->updateKeyCode(1, null);
    }

    public function testUpdateKeyCodeRejectsInvalidFormat(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'X', 'key_code' => 'OLD']);

        try {
            $this->service->updateKeyCode(1, 'lowercase');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key_code', $e->getErrors());
        }
    }

    public function testUpdateKeyCodeRejectsDuplicate(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'X', 'key_code' => 'OLD']);
        $this->projectModel->method('count')->willReturn(1);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage("Key code 'NEW1' already exists");

        $this->service->updateKeyCode(1, 'NEW1');
    }

    public function testUpdateKeyCodeAllowsReassigningSameKeyCode(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'X', 'key_code' => 'SAME']);
        $this->projectModel->method('count')->willReturn(1);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['key_code'] === 'SAME'))
            ->willReturn(true);

        $this->service->updateKeyCode(1, 'SAME');
    }

    public function testUpdateKeyCodeThrowsWhenUpdateFails(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'X', 'key_code' => 'OLD']);
        $this->projectModel->method('count')->willReturn(0);
        $this->projectModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update key code');

        $this->service->updateKeyCode(1, 'NEW1');
    }

    public function testGenerateKeyCodePadsShortSingleWordNames(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'X', 'key_code' => 'OLD']);
        $this->projectModel->method('count')->willReturn(0);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['key_code'] === 'X'))
            ->willReturn(true);

        $this->service->updateKeyCode(1, null);
    }

    public function testGenerateKeyCodeAppendsCounterOnCollision(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['name' => 'AB CD', 'key_code' => 'OLD']);
        $this->projectModel->method('count')->willReturnOnConsecutiveCalls(1, 0);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['key_code'] === 'AC1'))
            ->willReturn(true);

        $this->service->updateKeyCode(1, null);
    }

    public function testAddTeamMemberThrowsWhenProjectMissing(): void
    {
        $this->projectModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Project', 1));

        $this->expectException(NotFoundException::class);

        $this->service->addTeamMember(1, 5);
    }

    public function testAddTeamMemberThrowsWhenUserMissing(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('User', 5));

        $this->expectException(NotFoundException::class);

        $this->service->addTeamMember(1, 5);
    }

    public function testAddTeamMemberRejectsExistingMember(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->method('getTeamMembers')->willReturn([(object) ['id' => 5]]);
        $this->projectModel->expects($this->never())->method('addTeamMember');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('User is already a team member');

        $this->service->addTeamMember(1, 5);
    }

    public function testAddTeamMemberSucceeds(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->method('getTeamMembers')->willReturn([]);
        $this->projectModel->expects($this->once())
            ->method('addTeamMember')
            ->with(1, 5)
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('User #5 added to project #1'));

        $this->service->addTeamMember(1, 5);
    }

    public function testAddTeamMemberThrowsWhenAddFails(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->method('getTeamMembers')->willReturn([]);
        $this->projectModel->method('addTeamMember')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add team member');

        $this->service->addTeamMember(1, 5);
    }

    public function testRemoveTeamMemberThrowsWhenProjectMissing(): void
    {
        $this->projectModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Project', 1));

        $this->expectException(NotFoundException::class);

        $this->service->removeTeamMember(1, 5);
    }

    public function testRemoveTeamMemberThrowsWhenUserMissing(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('User', 5));

        $this->expectException(NotFoundException::class);

        $this->service->removeTeamMember(1, 5);
    }

    public function testRemoveTeamMemberSucceeds(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->expects($this->once())
            ->method('removeTeamMember')
            ->with(1, 5)
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('User #5 removed from project #1'));

        $this->service->removeTeamMember(1, 5);
    }

    public function testRemoveTeamMemberThrowsWhenRemoveFails(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->projectModel->method('removeTeamMember')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove team member');

        $this->service->removeTeamMember(1, 5);
    }

    public function testCalculateHealthThrowsWhenProjectMissing(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Project not found');

        $this->service->calculateHealth(1);
    }

    public function testCalculateHealthWithNoTasksIsGood(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn((object) ['tasks' => []]);

        $health = $this->service->calculateHealth(1);

        $this->assertSame('good', $health['overall_health']);
        $this->assertSame(0, $health['completion_rate']);
        $this->assertTrue($health['on_track']);
        $this->assertSame(0, $health['metrics']['total_tasks']);
    }

    public function testCalculateHealthIsGoodWithHighCompletionAndNoOverdue(): void
    {
        $tasks = [];
        for ($i = 0; $i < 8; $i++) {
            $tasks[] = (object) ['status_id' => TaskStatus::COMPLETED->value, 'due_date' => null];
        }
        for ($i = 0; $i < 2; $i++) {
            $tasks[] = (object) ['status_id' => TaskStatus::OPEN->value, 'due_date' => null];
        }

        $this->projectModel->method('findWithDetails')->willReturn((object) ['tasks' => $tasks]);

        $health = $this->service->calculateHealth(1);

        $this->assertSame('good', $health['overall_health']);
        $this->assertSame(80.0, $health['completion_rate']);
        $this->assertTrue($health['on_track']);
        $this->assertSame(0, $health['overdue_tasks']);
    }

    public function testCalculateHealthIsFairWithLowCompletion(): void
    {
        $tasks = [];
        for ($i = 0; $i < 4; $i++) {
            $tasks[] = (object) ['status_id' => TaskStatus::COMPLETED->value, 'due_date' => null];
        }
        for ($i = 0; $i < 6; $i++) {
            $tasks[] = (object) ['status_id' => TaskStatus::OPEN->value, 'due_date' => null];
        }

        $this->projectModel->method('findWithDetails')->willReturn((object) ['tasks' => $tasks]);

        $health = $this->service->calculateHealth(1);

        $this->assertSame('fair', $health['overall_health']);
        $this->assertSame(40.0, $health['completion_rate']);
        $this->assertTrue($health['on_track']);
    }

    public function testCalculateHealthIsPoorWithHighOverdueRate(): void
    {
        $tasks = [];
        for ($i = 0; $i < 6; $i++) {
            $tasks[] = (object) ['status_id' => TaskStatus::COMPLETED->value, 'due_date' => '2000-01-01'];
        }
        for ($i = 0; $i < 3; $i++) {
            $tasks[] = (object) ['status_id' => TaskStatus::OPEN->value, 'due_date' => '2000-01-01'];
        }
        $tasks[] = (object) ['status_id' => TaskStatus::IN_PROGRESS->value, 'due_date' => null];

        $this->projectModel->method('findWithDetails')->willReturn((object) ['tasks' => $tasks]);

        $health = $this->service->calculateHealth(1);

        $this->assertSame('poor', $health['overall_health']);
        $this->assertSame(3, $health['overdue_tasks']);
        $this->assertFalse($health['on_track']);
        $this->assertSame(1, $health['metrics']['in_progress_tasks']);
        $this->assertSame(6, $health['metrics']['completed_tasks']);
    }

    public function testTransitionStatusThrowsWhenProjectMissing(): void
    {
        $this->projectModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Project', 1));

        $this->expectException(NotFoundException::class);

        $this->service->transitionStatus(1, ProjectStatus::IN_PROGRESS);
    }

    public function testTransitionStatusThrowsWhenCurrentStatusInvalid(): void
    {
        $this->projectModel->method('findOrFail')->willReturn((object) ['status_id' => 999]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Project has invalid current status');

        $this->service->transitionStatus(1, ProjectStatus::IN_PROGRESS);
    }

    public function testTransitionStatusRejectsInvalidTransition(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::READY->value]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage("Cannot transition from 'Ready' to 'Completed'");

        $this->service->transitionStatus(1, ProjectStatus::COMPLETED);
    }

    public function testTransitionStatusSucceedsWithoutCompletionDate(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::READY->value, 'completed_at' => null]);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data): bool {
                return $data['status_id'] === ProjectStatus::IN_PROGRESS->value
                    && !isset($data['completed_at']);
            }))
            ->willReturn(true);

        $this->service->transitionStatus(1, ProjectStatus::IN_PROGRESS);
    }

    public function testTransitionStatusSetsCompletionDateWhenNotAlreadySet(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::IN_PROGRESS->value, 'completed_at' => null]);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => isset($data['completed_at'])))
            ->willReturn(true);

        $this->service->transitionStatus(1, ProjectStatus::COMPLETED);
    }

    public function testTransitionStatusKeepsExistingCompletionDate(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) [
                'status_id' => ProjectStatus::IN_PROGRESS->value,
                'completed_at' => '2020-01-01 00:00:00',
            ]);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => !isset($data['completed_at'])))
            ->willReturn(true);

        $this->service->transitionStatus(1, ProjectStatus::COMPLETED);
    }

    public function testTransitionStatusThrowsWhenUpdateFails(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::READY->value, 'completed_at' => null]);
        $this->projectModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update project status');

        $this->service->transitionStatus(1, ProjectStatus::IN_PROGRESS);
    }

    public function testArchiveProjectThrowsWhenProjectMissing(): void
    {
        $this->projectModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Project', 1));

        $this->expectException(NotFoundException::class);

        $this->service->archiveProject(1);
    }

    public function testArchiveProjectRejectsActiveProject(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::IN_PROGRESS->value]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only completed or cancelled projects can be archived');

        $this->service->archiveProject(1);
    }

    public function testArchiveProjectSucceedsForCompletedProject(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::COMPLETED->value]);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['is_archived'] === 1))
            ->willReturn(true);

        $this->service->archiveProject(1);
    }

    public function testArchiveProjectSucceedsForCancelledProject(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::CANCELLED->value]);

        $this->projectModel->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $this->service->archiveProject(1);
    }

    public function testArchiveProjectThrowsWhenUpdateFails(): void
    {
        $this->projectModel->method('findOrFail')
            ->willReturn((object) ['status_id' => ProjectStatus::COMPLETED->value]);
        $this->projectModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to archive project');

        $this->service->archiveProject(1);
    }
}
