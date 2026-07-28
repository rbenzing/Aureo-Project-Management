<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProjectStatus;
use App\Exceptions\NotFoundException;
use App\Models\Project;

/**
 * Project Repository
 *
 * Handles data access for projects
 */
class ProjectRepository implements RepositoryInterface
{
    private Project $model;

    public function __construct(?Project $model = null)
    {
        $this->model = $model ?? new Project();
    }

    /**
     * Find a project by ID
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
     * Find a project by ID or throw exception
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
     * Find project with full details
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
     * Get all projects with filters
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
     * Get projects by owner
     *
     * @param int $ownerId
     * @return array
     */
    public function getByOwner(int $ownerId): array
    {
        return $this->model->getByOwner($ownerId);
    }

    /**
     * Get projects by company
     *
     * @param int $companyId
     * @return array
     */
    public function getByCompany(int $companyId): array
    {
        return $this->model->getByCompany($companyId);
    }

    /**
     * Get projects by status
     *
     * @param ProjectStatus $status
     * @return array
     */
    public function getByStatus(ProjectStatus $status): array
    {
        return $this->model->getAll(['status_id' => $status->value])['records'] ?? [];
    }

    /**
     * Get active projects
     *
     * @return array
     */
    public function getActiveProjects(): array
    {
        return $this->model->getAll(['status_id' => ProjectStatus::IN_PROGRESS->value])['records'] ?? [];
    }

    /**
     * Create a new project
     *
     * @param array $data
     * @return int Project ID
     */
    public function create(array $data): int
    {
        return $this->model->create($data);
    }

    /**
     * Update a project
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
     * Delete a project (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    /**
     * Count projects matching criteria
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->model->count($conditions);
    }

    /**
     * Check if project exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Add team member to project
     *
     * @param int $projectId
     * @param int $userId
     * @return bool
     */
    public function addTeamMember(int $projectId, int $userId): bool
    {
        return $this->model->addTeamMember($projectId, $userId);
    }

    /**
     * Remove team member from project
     *
     * @param int $projectId
     * @param int $userId
     * @return bool
     */
    public function removeTeamMember(int $projectId, int $userId): bool
    {
        return $this->model->removeTeamMember($projectId, $userId);
    }

    /**
     * Get team members for project
     *
     * @param int $projectId
     * @return array
     */
    public function getTeamMembers(int $projectId): array
    {
        return $this->model->getTeamMembers($projectId);
    }

    /**
     * Get project statistics
     *
     * @param int $projectId
     * @return array
     */
    public function getStatistics(int $projectId): array
    {
        $project = $this->findWithDetails($projectId, [
            'tasks' => true,
            'milestones' => true,
            'sprints' => true,
            'team_members' => true,
        ]);

        if (!$project) {
            return [];
        }

        $totalTasks = count($project->tasks ?? []);
        $completedTasks = 0;
        $totalTimeSpent = 0;

        foreach (($project->tasks ?? []) as $task) {
            if ($task->status_id === \App\Enums\TaskStatus::COMPLETED->value) {
                $completedTasks++;
            }
            $totalTimeSpent += $task->time_spent ?? 0;
        }

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'total_milestones' => count($project->milestones ?? []),
            'total_sprints' => count($project->sprints ?? []),
            'team_size' => count($project->team_members ?? []),
            'total_time_spent' => $totalTimeSpent,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
        ];
    }
}
