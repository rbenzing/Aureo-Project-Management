<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use RuntimeException;

/**
 * Project Service
 *
 * Handles business logic for project operations
 */
class ProjectService
{
    private Project $projectModel;
    private User $userModel;
    private Task $taskModel;
    private LoggerService $logger;

    public function __construct(
        ?Project $projectModel = null,
        ?User $userModel = null,
        ?Task $taskModel = null,
        ?LoggerService $logger = null
    ) {
        $this->projectModel = $projectModel ?? new Project();
        $this->userModel = $userModel ?? new User();
        $this->taskModel = $taskModel ?? new Task();
        $this->logger = $logger ?? new LoggerService();
    }

    /**
     * Create a new project with validation
     *
     * @param array $data
     * @return int Project ID
     * @throws ValidationException
     */
    public function createProject(array $data): int
    {
        // Validate required fields
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = ['Project name is required'];
        }

        if (empty($data['owner_id'])) {
            $errors['owner_id'] = ['Project owner is required'];
        }

        if (!empty($errors)) {
            throw ValidationException::withErrors($errors);
        }

        // Verify owner exists
        try {
            $this->userModel->findOrFail($data['owner_id']);
        } catch (NotFoundException $e) {
            throw ValidationException::withErrors(['owner_id' => ['Owner user not found']]);
        }

        // Set defaults
        $data['status_id'] = $data['status_id'] ?? ProjectStatus::READY->value;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Generate unique key code if not provided
        if (empty($data['key_code'])) {
            $data['key_code'] = $this->generateKeyCode($data['name']);
        }

        $projectId = $this->projectModel->create($data);

        if (!$projectId) {
            throw new RuntimeException("Failed to create project");
        }

        $this->logger->info("Project #{$projectId} created: {$data['name']}");

        return $projectId;
    }

    /**
     * Generate unique project key code
     *
     * @param string $projectName
     * @return string
     */
    private function generateKeyCode(string $projectName): string
    {
        // Extract first letters of words
        $words = preg_split('/\s+/', strtoupper($projectName));
        $keyCode = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $keyCode .= $word[0];
            }
            if (strlen($keyCode) >= 4) {
                break;
            }
        }

        // Pad with additional letters if needed
        if (strlen($keyCode) < 2) {
            $keyCode = strtoupper(substr($projectName, 0, 3));
        }

        // Ensure uniqueness by adding number if needed
        $baseKeyCode = $keyCode;
        $counter = 1;

        while ($this->keyCodeExists($keyCode)) {
            $keyCode = $baseKeyCode . $counter;
            $counter++;
        }

        return $keyCode;
    }

    /**
     * Check if key code already exists
     *
     * @param string $keyCode
     * @return bool
     */
    private function keyCodeExists(string $keyCode): bool
    {
        return $this->projectModel->count(['key_code' => $keyCode]) > 0;
    }

    /**
     * Update project key code
     *
     * @param int $projectId
     * @param string|null $newKeyCode If null, generates automatically
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function updateKeyCode(int $projectId, ?string $newKeyCode = null): void
    {
        $project = $this->projectModel->findOrFail($projectId);

        if ($newKeyCode === null) {
            $newKeyCode = $this->generateKeyCode($project->name);
        } else {
            // Validate format (2-10 uppercase letters/numbers)
            if (!preg_match('/^[A-Z0-9]{2,10}$/', $newKeyCode)) {
                throw ValidationException::withErrors([
                    'key_code' => ['Key code must be 2-10 uppercase letters or numbers'],
                ]);
            }

            // Check uniqueness
            if ($this->keyCodeExists($newKeyCode) && $project->key_code !== $newKeyCode) {
                throw BusinessRuleException::duplicateResource('Key code', $newKeyCode);
            }
        }

        $updated = $this->projectModel->update($projectId, [
            'key_code' => $newKeyCode,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to update key code");
        }

        $this->logger->info("Project #{$projectId} key code updated to {$newKeyCode}");
    }

    /**
     * Add team member to project
     *
     * @param int $projectId
     * @param int $userId
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function addTeamMember(int $projectId, int $userId): void
    {
        // Verify project exists
        $this->projectModel->findOrFail($projectId);

        // Verify user exists
        $this->userModel->findOrFail($userId);

        // Check if already a member
        $existingMembers = $this->projectModel->getTeamMembers($projectId);
        foreach ($existingMembers as $member) {
            if ($member->id === $userId) {
                throw new BusinessRuleException("User is already a team member");
            }
        }

        // Add to project
        $added = $this->projectModel->addTeamMember($projectId, $userId);

        if (!$added) {
            throw new RuntimeException("Failed to add team member");
        }

        $this->logger->info("User #{$userId} added to project #{$projectId}");
    }

    /**
     * Remove team member from project
     *
     * @param int $projectId
     * @param int $userId
     * @throws NotFoundException
     */
    public function removeTeamMember(int $projectId, int $userId): void
    {
        $this->projectModel->findOrFail($projectId);
        $this->userModel->findOrFail($userId);

        $removed = $this->projectModel->removeTeamMember($projectId, $userId);

        if (!$removed) {
            throw new RuntimeException("Failed to remove team member");
        }

        $this->logger->info("User #{$userId} removed from project #{$projectId}");
    }

    /**
     * Calculate project health metrics
     *
     * @param int $projectId
     * @return array Health metrics
     * @throws NotFoundException
     */
    public function calculateHealth(int $projectId): array
    {
        $project = $this->projectModel->findWithDetails($projectId, [
            'tasks' => true,
            'milestones' => true,
            'sprints' => false,
            'team_members' => false,
            'hierarchy' => false,
        ]);

        if (!$project) {
            throw new NotFoundException("Project not found");
        }

        $tasks = $project->tasks ?? [];
        $totalTasks = count($tasks);

        if ($totalTasks === 0) {
            return [
                'overall_health' => 'good',
                'completion_rate' => 0,
                'overdue_tasks' => 0,
                'on_track' => true,
                'metrics' => [
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'in_progress_tasks' => 0,
                    'overdue_tasks' => 0,
                ],
            ];
        }

        // Count task statuses
        $completedTasks = 0;
        $inProgressTasks = 0;
        $overdueTasks = 0;
        $today = date('Y-m-d');

        foreach ($tasks as $task) {
            if ($task->status_id === TaskStatus::COMPLETED->value) {
                $completedTasks++;
            } elseif ($task->status_id === TaskStatus::IN_PROGRESS->value) {
                $inProgressTasks++;
            }

            // Check if overdue
            if (!empty($task->due_date) && $task->due_date < $today) {
                if ($task->status_id !== TaskStatus::COMPLETED->value) {
                    $overdueTasks++;
                }
            }
        }

        $completionRate = ($completedTasks / $totalTasks) * 100;
        $overdueRate = ($overdueTasks / $totalTasks) * 100;

        // Determine overall health
        $overallHealth = 'good';
        if ($overdueRate > 20 || $completionRate < 30) {
            $overallHealth = 'poor';
        } elseif ($overdueRate > 10 || $completionRate < 50) {
            $overallHealth = 'fair';
        }

        $onTrack = $overdueRate < 10;

        return [
            'overall_health' => $overallHealth,
            'completion_rate' => round($completionRate, 2),
            'overdue_tasks' => $overdueTasks,
            'on_track' => $onTrack,
            'metrics' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'overdue_tasks' => $overdueTasks,
            ],
        ];
    }

    /**
     * Transition project status with validation
     *
     * @param int $projectId
     * @param ProjectStatus $newStatus
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function transitionStatus(int $projectId, ProjectStatus $newStatus): void
    {
        $project = $this->projectModel->findOrFail($projectId);
        $currentStatus = ProjectStatus::tryFrom($project->status_id);

        if (!$currentStatus) {
            throw new BusinessRuleException("Project has invalid current status");
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
        if ($newStatus === ProjectStatus::COMPLETED && empty($project->completed_at)) {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
        }

        $updated = $this->projectModel->update($projectId, $updateData);

        if (!$updated) {
            throw new RuntimeException("Failed to update project status");
        }

        $this->logger->info("Project #{$projectId} transitioned from {$currentStatus->label()} to {$newStatus->label()}");
    }

    /**
     * Validate project status transition
     *
     * @param ProjectStatus $from
     * @param ProjectStatus $to
     * @return bool
     */
    private function isValidStatusTransition(ProjectStatus $from, ProjectStatus $to): bool
    {
        // Define valid transitions
        $validTransitions = [
            ProjectStatus::READY->value => [
                ProjectStatus::IN_PROGRESS->value,
                ProjectStatus::ON_HOLD->value,
                ProjectStatus::CANCELLED->value,
            ],
            ProjectStatus::IN_PROGRESS->value => [
                ProjectStatus::ON_HOLD->value,
                ProjectStatus::DELAYED->value,
                ProjectStatus::COMPLETED->value,
            ],
            ProjectStatus::COMPLETED->value => [
                ProjectStatus::IN_PROGRESS->value, // Can reopen
            ],
            ProjectStatus::ON_HOLD->value => [
                ProjectStatus::IN_PROGRESS->value,
                ProjectStatus::CANCELLED->value,
            ],
            ProjectStatus::DELAYED->value => [
                ProjectStatus::IN_PROGRESS->value,
                ProjectStatus::ON_HOLD->value,
            ],
            ProjectStatus::CANCELLED->value => [
                ProjectStatus::READY->value, // Can restart
            ],
        ];

        return in_array($to->value, $validTransitions[$from->value] ?? [], true);
    }

    /**
     * Archive a project
     *
     * @param int $projectId
     * @throws NotFoundException
     * @throws BusinessRuleException
     */
    public function archiveProject(int $projectId): void
    {
        $project = $this->projectModel->findOrFail($projectId);

        // Business rule: Only completed or cancelled projects can be archived
        if (!in_array($project->status_id, [ProjectStatus::COMPLETED->value, ProjectStatus::CANCELLED->value])) {
            throw new BusinessRuleException("Only completed or cancelled projects can be archived");
        }

        $updated = $this->projectModel->update($projectId, [
            'is_archived' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            throw new RuntimeException("Failed to archive project");
        }

        $this->logger->info("Project #{$projectId} archived");
    }
}
