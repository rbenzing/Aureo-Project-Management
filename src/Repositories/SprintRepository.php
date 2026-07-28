<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SprintStatus;
use App\Exceptions\NotFoundException;
use App\Models\Sprint;

/**
 * Sprint Repository
 *
 * Handles data access for sprints
 */
class SprintRepository implements RepositoryInterface
{
    private Sprint $model;

    public function __construct(?Sprint $model = null)
    {
        $this->model = $model ?? new Sprint();
    }

    /**
     * Find a sprint by ID
     *
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object
    {
        $result = $this->model->find($id);

        return $result === false ? null : $result;
    }

    /**
     * Find a sprint by ID or throw exception
     *
     * @param int $id
     * @return object
     * @throws NotFoundException
     */
    public function findOrFail(int $id): object
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Find sprint with full details
     *
     * @param int $id
     * @param array $options Selective loading options
     * @return object|null
     */
    public function findWithDetails(int $id, array $options = []): ?object
    {
        return $this->model->findWithDetails($id, $options);
    }

    /**
     * Get all sprints with filters
     *
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 10): array
    {
        return $this->model->getAll($filters, $page, $limit);
    }

    /**
     * Get sprints by project
     *
     * @param int $projectId
     * @return array
     */
    public function getByProject(int $projectId): array
    {
        return $this->model->getByProjectId($projectId);
    }

    /**
     * Get sprints by status
     *
     * @param SprintStatus $status
     * @return array
     */
    public function getByStatus(SprintStatus $status): array
    {
        return $this->model->getAll(['status_id' => $status->value])['records'] ?? [];
    }

    /**
     * Get active sprints
     *
     * @return array
     */
    public function getActiveSprints(): array
    {
        return $this->getByStatus(SprintStatus::ACTIVE);
    }

    /**
     * Get current sprint for project
     *
     * @param int $projectId
     * @return object|null
     */
    public function getCurrentSprint(int $projectId): ?object
    {
        $activeSprints = $this->model->getAll([
            'project_id' => $projectId,
            'status_id' => SprintStatus::ACTIVE->value,
        ])['records'] ?? [];

        return $activeSprints[0] ?? null;
    }

    /**
     * Create a new sprint
     *
     * @param array $data
     * @return int Sprint ID
     */
    public function create(array $data): int
    {
        return $this->model->create($data);
    }

    /**
     * Update a sprint
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $data);
    }

    /**
     * Delete a sprint (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    /**
     * Count sprints matching criteria
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->model->count($conditions);
    }

    /**
     * Check if sprint exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Add task to sprint
     *
     * @param int $sprintId
     * @param int $taskId
     * @return bool
     */
    public function addTask(int $sprintId, int $taskId): bool
    {
        return $this->model->addTask($sprintId, $taskId);
    }

    /**
     * Remove task from sprint
     *
     * @param int $sprintId
     * @param int $taskId
     * @return bool
     */
    public function removeTask(int $sprintId, int $taskId): bool
    {
        return $this->model->removeTask($sprintId, $taskId);
    }

    /**
     * Get sprint statistics
     *
     * @param int $sprintId
     * @return array
     */
    public function getStatistics(int $sprintId): array
    {
        $sprint = $this->findWithDetails($sprintId, [
            'tasks' => true,
            'milestones' => false,
        ]);

        if (!$sprint) {
            return [];
        }

        $totalTasks = count($sprint->tasks ?? []);
        $completedTasks = 0;
        $totalStoryPoints = 0;
        $completedStoryPoints = 0;
        $totalTimeSpent = 0;

        foreach (($sprint->tasks ?? []) as $task) {
            if ($task->status_id === \App\Enums\TaskStatus::COMPLETED->value) {
                $completedTasks++;
                $completedStoryPoints += $task->story_points ?? 0;
            }
            $totalStoryPoints += $task->story_points ?? 0;
            $totalTimeSpent += $task->time_spent ?? 0;
        }

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'total_story_points' => $totalStoryPoints,
            'completed_story_points' => $completedStoryPoints,
            'total_time_spent' => $totalTimeSpent,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
            'velocity' => $completedStoryPoints,
        ];
    }
}
