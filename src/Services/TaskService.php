<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Task;
use App\Models\User;
use RuntimeException;

/**
 * Task Service
 *
 * Handles business logic for task operations
 */
class TaskService
{
    private Task $taskModel;
    private User $userModel;
    private LoggerService $logger;

    public function __construct(
        ?Task $taskModel = null,
        ?User $userModel = null,
        ?LoggerService $logger = null
    ) {
        $this->taskModel = $taskModel ?? new Task();
        $this->userModel = $userModel ?? new User();
        $this->logger = $logger ?? new LoggerService();
    }

    /**
     * Assign a task to a user
     *
     * @param int $taskId
     * @param int $userId
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function assignTask(int $taskId, int $userId): void
    {
        // Verify task exists
        $task = $this->taskModel->findOrFail($taskId);

        // Verify user exists
        $user = $this->userModel->findOrFail($userId);

        // Business rule: Cannot assign completed tasks
        if ($task->status_id === TaskStatus::COMPLETED->value) {
            throw new BusinessRuleException("Cannot assign a completed task");
        }

        // Business rule: Cannot assign closed tasks
        if ($task->status_id === TaskStatus::CLOSED->value) {
            throw new BusinessRuleException("Cannot assign a closed task");
        }

        // Perform assignment
        $updated = $this->taskModel->update($taskId, [
            'assigned_to' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to assign task");
        }

        // Log the action
        $this->logger->info("Task #{$taskId} assigned to user #{$userId}");
    }

    /**
     * Unassign a task from its current user
     *
     * @param int $taskId
     * @throws NotFoundException
     */
    public function unassignTask(int $taskId): void
    {
        $task = $this->taskModel->findOrFail($taskId);

        $updated = $this->taskModel->update($taskId, [
            'assigned_to' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to unassign task");
        }

        $this->logger->info("Task #{$taskId} unassigned");
    }

    /**
     * Transition task status with validation
     *
     * @param int $taskId
     * @param TaskStatus $newStatus
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function transitionStatus(int $taskId, TaskStatus $newStatus): void
    {
        $task = $this->taskModel->findOrFail($taskId);
        $currentStatus = TaskStatus::tryFrom($task->status_id);

        if (!$currentStatus) {
            throw new BusinessRuleException("Task has invalid current status");
        }

        // Validate status transition
        if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
            throw BusinessRuleException::invalidStatusTransition(
                $currentStatus->label(),
                $newStatus->label()
            );
        }

        $updateData = [
            'status_id' => $newStatus->value,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Auto-set completion date when transitioning to completed
        if ($newStatus === TaskStatus::COMPLETED && empty($task->completed_at)) {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
        }

        $updated = $this->taskModel->update($taskId, $updateData);

        if (!$updated) {
            throw new RuntimeException("Failed to update task status");
        }

        $this->logger->info("Task #{$taskId} transitioned from {$currentStatus->label()} to {$newStatus->label()}");
    }

    /**
     * Validate status transition
     *
     * @param TaskStatus $from
     * @param TaskStatus $to
     * @return bool
     */
    private function isValidStatusTransition(TaskStatus $from, TaskStatus $to): bool
    {
        // Define valid transitions
        $validTransitions = [
            TaskStatus::OPEN->value => [
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::ON_HOLD->value,
                TaskStatus::CLOSED->value,
            ],
            TaskStatus::IN_PROGRESS->value => [
                TaskStatus::OPEN->value,
                TaskStatus::ON_HOLD->value,
                TaskStatus::IN_REVIEW->value,
                TaskStatus::COMPLETED->value,
            ],
            TaskStatus::ON_HOLD->value => [
                TaskStatus::OPEN->value,
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::CLOSED->value,
            ],
            TaskStatus::IN_REVIEW->value => [
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::COMPLETED->value,
            ],
            TaskStatus::CLOSED->value => [
                TaskStatus::OPEN->value, // Can reopen closed tasks
            ],
            TaskStatus::COMPLETED->value => [
                TaskStatus::IN_PROGRESS->value, // Can reopen completed tasks
            ],
        ];

        return in_array($to->value, $validTransitions[$from->value] ?? [], true);
    }

    /**
     * Start time tracking for a task
     *
     * @param int $taskId
     * @param int $userId
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function startTimer(int $taskId, int $userId): void
    {
        $task = $this->taskModel->findOrFail($taskId);

        // Business rule: Task must be assigned to the user
        if ($task->assigned_to !== $userId) {
            throw new BusinessRuleException("Cannot start timer on task not assigned to you");
        }

        // Business rule: Cannot track time on completed tasks
        if ($task->status_id === TaskStatus::COMPLETED->value) {
            throw new BusinessRuleException("Cannot track time on completed tasks");
        }

        // Business rule: Cannot track time on closed tasks
        if ($task->status_id === TaskStatus::CLOSED->value) {
            throw new BusinessRuleException("Cannot track time on closed tasks");
        }

        // Check if timer is already running
        if (!empty($task->timer_start)) {
            throw new BusinessRuleException("Timer is already running for this task");
        }

        // Auto-transition to IN_PROGRESS if task is OPEN
        $updateData = [
            'timer_start' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($task->status_id === TaskStatus::OPEN->value) {
            $updateData['status_id'] = TaskStatus::IN_PROGRESS->value;
        }

        $updated = $this->taskModel->update($taskId, $updateData);

        if (!$updated) {
            throw new RuntimeException("Failed to start timer");
        }

        $this->logger->info("Timer started for task #{$taskId} by user #{$userId}");
    }

    /**
     * Stop time tracking for a task
     *
     * @param int $taskId
     * @param int $userId
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function stopTimer(int $taskId, int $userId): void
    {
        $task = $this->taskModel->findOrFail($taskId);

        // Business rule: Task must be assigned to the user
        if ($task->assigned_to !== $userId) {
            throw new BusinessRuleException("Cannot stop timer on task not assigned to you");
        }

        // Check if timer is running
        if (empty($task->timer_start)) {
            throw new BusinessRuleException("No timer running for this task");
        }

        // Calculate elapsed time
        $startTime = strtotime($task->timer_start);
        $elapsed = time() - $startTime;

        // Update time spent
        $currentTimeSpent = $task->time_spent ?? 0;
        $newTimeSpent = $currentTimeSpent + $elapsed;

        $updated = $this->taskModel->update($taskId, [
            'time_spent' => $newTimeSpent,
            'timer_start' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to stop timer");
        }

        $this->logger->info("Timer stopped for task #{$taskId} by user #{$userId}. Elapsed: {$elapsed}s");
    }

    /**
     * Update task estimated time
     *
     * @param int $taskId
     * @param int $estimatedSeconds
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateEstimate(int $taskId, int $estimatedSeconds): void
    {
        if ($estimatedSeconds < 0) {
            throw ValidationException::withErrors(['estimated_time' => ['Estimated time cannot be negative']]);
        }

        $this->taskModel->findOrFail($taskId);

        $updated = $this->taskModel->update($taskId, [
            'estimated_time' => $estimatedSeconds,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to update task estimate");
        }

        $this->logger->info("Task #{$taskId} estimate updated to {$estimatedSeconds}s");
    }

    /**
     * Mark task as completed
     *
     * @param int $taskId
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function completeTask(int $taskId): void
    {
        $task = $this->taskModel->findOrFail($taskId);

        // Stop any running timer
        if (!empty($task->timer_start)) {
            throw new BusinessRuleException("Cannot complete task with running timer. Stop the timer first.");
        }

        // Business rule: Cannot complete already completed tasks
        if ($task->status_id === TaskStatus::COMPLETED->value) {
            throw new BusinessRuleException("Task is already completed");
        }

        $updated = $this->taskModel->update($taskId, [
            'status_id' => TaskStatus::COMPLETED->value,
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to complete task");
        }

        $this->logger->info("Task #{$taskId} marked as completed");
    }

    /**
     * Reopen a completed or closed task
     *
     * @param int $taskId
     * @throws NotFoundException
     */
    public function reopenTask(int $taskId): void
    {
        $task = $this->taskModel->findOrFail($taskId);

        $updated = $this->taskModel->update($taskId, [
            'status_id' => TaskStatus::OPEN->value,
            'completed_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to reopen task");
        }

        $this->logger->info("Task #{$taskId} reopened");
    }

    /**
     * Update task priority
     *
     * @param int $taskId
     * @param int $priority
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updatePriority(int $taskId, int $priority): void
    {
        if ($priority < 1 || $priority > 5) {
            throw ValidationException::withErrors(['priority' => ['Priority must be between 1 and 5']]);
        }

        $this->taskModel->findOrFail($taskId);

        $updated = $this->taskModel->update($taskId, [
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to update task priority");
        }

        $this->logger->info("Task #{$taskId} priority updated to {$priority}");
    }
}
