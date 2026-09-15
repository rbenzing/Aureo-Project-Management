<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\TaskService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for TaskService
 *
 * All collaborators (Task, User models and LoggerService) are mocked so no
 * live database connection is required.
 */
#[CoversClass(TaskService::class)]
#[UsesClass(BusinessRuleException::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(TaskStatus::class)]
#[UsesClass(ValidationException::class)]
final class TaskServiceTest extends TestCase
{
    private Task&MockObject $taskModel;
    private User&MockObject $userModel;
    private LoggerService&MockObject $logger;
    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taskModel = $this->createMock(Task::class);
        $this->userModel = $this->createMock(User::class);
        $this->logger = $this->createMock(LoggerService::class);

        $this->service = new TaskService(
            $this->taskModel,
            $this->userModel,
            $this->logger
        );
    }

    // ------------------------------------------------------------------
    // assignTask
    // ------------------------------------------------------------------

    public function testAssignTaskThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));
        $this->userModel->expects($this->never())->method('findOrFail');

        $this->expectException(NotFoundException::class);

        $this->service->assignTask(1, 5);
    }

    public function testAssignTaskThrowsWhenUserMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::OPEN->value]);
        $this->userModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('User', 5));

        $this->expectException(NotFoundException::class);

        $this->service->assignTask(1, 5);
    }

    public function testAssignTaskRejectsCompletedTask(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::COMPLETED->value]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot assign a completed task');

        $this->service->assignTask(1, 5);
    }

    public function testAssignTaskRejectsClosedTask(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::CLOSED->value]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot assign a closed task');

        $this->service->assignTask(1, 5);
    }

    public function testAssignTaskSucceeds(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::OPEN->value]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['assigned_to'] === 5 && isset($data['updated_at'])))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task #1 assigned to user #5'));

        $this->service->assignTask(1, 5);
    }

    public function testAssignTaskThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::OPEN->value]);
        $this->userModel->method('findOrFail')->willReturn((object) ['id' => 5]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to assign task');

        $this->service->assignTask(1, 5);
    }

    // ------------------------------------------------------------------
    // unassignTask
    // ------------------------------------------------------------------

    public function testUnassignTaskThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));

        $this->expectException(NotFoundException::class);

        $this->service->unassignTask(1);
    }

    public function testUnassignTaskSucceeds(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['assigned_to'] === null))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task #1 unassigned'));

        $this->service->unassignTask(1);
    }

    public function testUnassignTaskThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to unassign task');

        $this->service->unassignTask(1);
    }

    // ------------------------------------------------------------------
    // transitionStatus
    // ------------------------------------------------------------------

    public function testTransitionStatusThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));

        $this->expectException(NotFoundException::class);

        $this->service->transitionStatus(1, TaskStatus::IN_PROGRESS);
    }

    public function testTransitionStatusThrowsWhenCurrentStatusInvalid(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['status_id' => 999]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Task has invalid current status');

        $this->service->transitionStatus(1, TaskStatus::IN_PROGRESS);
    }

    public function testTransitionStatusRejectsInvalidTransition(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::OPEN->value]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage("Cannot transition from 'Open' to 'Completed'");

        $this->service->transitionStatus(1, TaskStatus::COMPLETED);
    }

    public function testTransitionStatusSucceedsWithoutCompletionDate(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::OPEN->value, 'complete_date' => null]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data): bool {
                return $data['status_id'] === TaskStatus::IN_PROGRESS->value
                    && !isset($data['complete_date']);
            }))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('transitioned from Open to In Progress'));

        $this->service->transitionStatus(1, TaskStatus::IN_PROGRESS);
    }

    public function testTransitionStatusSetsCompletionDateWhenEmpty(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::IN_PROGRESS->value, 'complete_date' => null]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn (array $data): bool => ($data['complete_date'] ?? null) === date('Y-m-d')
            ))
            ->willReturn(true);

        $this->service->transitionStatus(1, TaskStatus::COMPLETED);
    }

    public function testTransitionStatusKeepsExistingCompletionDate(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) [
                'status_id' => TaskStatus::IN_PROGRESS->value,
                'complete_date' => '2020-01-01',
            ]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => !isset($data['complete_date'])))
            ->willReturn(true);

        $this->service->transitionStatus(1, TaskStatus::COMPLETED);
    }

    public function testTransitionStatusAllowsReopeningClosedTask(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::CLOSED->value, 'complete_date' => null]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['status_id'] === TaskStatus::OPEN->value))
            ->willReturn(true);

        $this->service->transitionStatus(1, TaskStatus::OPEN);
    }

    public function testTransitionStatusThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::OPEN->value, 'complete_date' => null]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update task status');

        $this->service->transitionStatus(1, TaskStatus::IN_PROGRESS);
    }

    // ------------------------------------------------------------------
    // updateEstimate
    // ------------------------------------------------------------------

    public function testUpdateEstimateRejectsNegativeValue(): void
    {
        $this->taskModel->expects($this->never())->method('findOrFail');

        try {
            $this->service->updateEstimate(1, -5);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('estimated_time', $e->getErrors());
        }
    }

    public function testUpdateEstimateThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));

        $this->expectException(NotFoundException::class);

        $this->service->updateEstimate(1, 3600);
    }

    public function testUpdateEstimateSucceeds(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['estimated_time'] === 3600))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task #1 estimate updated to 3600s'));

        $this->service->updateEstimate(1, 3600);
    }

    public function testUpdateEstimateThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update task estimate');

        $this->service->updateEstimate(1, 3600);
    }

    // ------------------------------------------------------------------
    // completeTask
    // ------------------------------------------------------------------

    public function testCompleteTaskThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));

        $this->expectException(NotFoundException::class);

        $this->service->completeTask(1);
    }

    public function testCompleteTaskRejectsAlreadyCompletedTask(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::COMPLETED->value]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Task is already completed');

        $this->service->completeTask(1);
    }

    public function testCompleteTaskSucceeds(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::IN_PROGRESS->value]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data): bool {
                return $data['status_id'] === TaskStatus::COMPLETED->value
                    && $data['complete_date'] === date('Y-m-d');
            }))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task #1 marked as completed'));

        $this->service->completeTask(1);
    }

    public function testCompleteTaskThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')
            ->willReturn((object) ['status_id' => TaskStatus::IN_PROGRESS->value]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to complete task');

        $this->service->completeTask(1);
    }

    // ------------------------------------------------------------------
    // reopenTask
    // ------------------------------------------------------------------

    public function testReopenTaskThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));

        $this->expectException(NotFoundException::class);

        $this->service->reopenTask(1);
    }

    public function testReopenTaskSucceeds(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data): bool {
                return $data['status_id'] === TaskStatus::OPEN->value
                    && $data['complete_date'] === null;
            }))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task #1 reopened'));

        $this->service->reopenTask(1);
    }

    public function testReopenTaskThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to reopen task');

        $this->service->reopenTask(1);
    }

    // ------------------------------------------------------------------
    // updatePriority
    // ------------------------------------------------------------------

    public function testUpdatePriorityRejectsTooLowValue(): void
    {
        $this->taskModel->expects($this->never())->method('findOrFail');

        try {
            $this->service->updatePriority(1, 0);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('priority', $e->getErrors());
        }
    }

    public function testUpdatePriorityRejectsTooHighValue(): void
    {
        try {
            $this->service->updatePriority(1, 6);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('priority', $e->getErrors());
        }
    }

    public function testUpdatePriorityThrowsWhenTaskMissing(): void
    {
        $this->taskModel->method('findOrFail')
            ->willThrowException(NotFoundException::forModel('Task', 1));

        $this->expectException(NotFoundException::class);

        $this->service->updatePriority(1, 3);
    }

    public function testUpdatePrioritySucceeds(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);

        $this->taskModel->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['priority'] === 3))
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task #1 priority updated to 3'));

        $this->service->updatePriority(1, 3);
    }

    public function testUpdatePriorityThrowsWhenUpdateFails(): void
    {
        $this->taskModel->method('findOrFail')->willReturn((object) ['id' => 1]);
        $this->taskModel->method('update')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update task priority');

        $this->service->updatePriority(1, 3);
    }
}
