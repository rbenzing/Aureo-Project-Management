<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TaskStatus;
use App\Exceptions\NotFoundException;
use App\Models\Task;

/**
 * Task Repository
 *
 * Handles data access for tasks
 */
class TaskRepository implements RepositoryInterface
{
    private Task $model;

    public function __construct(?Task $model = null)
    {
        $this->model = $model ?? new Task();
    }

    /**
     * Find a task by ID
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
     * Find a task by ID or throw exception
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
     * Find task with full details
     *
     * @param int $id
     * @return object|null
     */
    public function findWithDetails(int $id): ?object
    {
        return $this->model->findWithDetails($id);
    }

    /**
     * Get all tasks with filters
     *
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 10): array
    {
        return $this->model->getAllWithDetails($limit, $page) ?? [];
    }

    /**
     * Get tasks by project ID
     *
     * @param int $projectId
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getByProjectId(int $projectId, int $limit = 10, int $page = 1): array
    {
        return $this->model->getByProjectId($projectId, $limit, $page);
    }

    /**
     * Get tasks assigned to user
     *
     * @param int $userId
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getByUserId(int $userId, int $limit = 10, int $page = 1): array
    {
        return $this->model->getByUserId($userId, $limit, $page);
    }

    /**
     * Get overdue tasks
     *
     * @param int|null $projectId Optional project filter
     * @return array
     */
    public function getOverdueTasks(?int $projectId = null): array
    {
        $filters = ['overdue' => true];
        if ($projectId !== null) {
            $filters['project_id'] = $projectId;
        }

        return $this->model->getOverdueTasks($filters);
    }

    /**
     * Get tasks by status
     *
     * @param TaskStatus $status
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getByStatus(TaskStatus $status, int $limit = 10, int $page = 1): array
    {
        return $this->model->getByStatus($status->value, $limit, $page);
    }

    /**
     * Create a new task
     *
     * @param array $data
     * @return int Task ID
     */
    public function create(array $data): int
    {
        return $this->model->create($data);
    }

    /**
     * Update a task
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
     * Delete a task (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    /**
     * Count tasks matching criteria
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->model->count($conditions);
    }

    /**
     * Check if task exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Get task statistics
     *
     * @param int|null $projectId Optional project filter
     * @return array
     */
    public function getStatistics(?int $projectId = null): array
    {
        $conditions = ['is_deleted' => 0];
        if ($projectId !== null) {
            $conditions['project_id'] = $projectId;
        }

        $total = $this->count($conditions);
        $completed = $this->count(array_merge($conditions, ['status_id' => TaskStatus::COMPLETED->value]));
        $inProgress = $this->count(array_merge($conditions, ['status_id' => TaskStatus::IN_PROGRESS->value]));

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }
}
