<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NotFoundException;
use App\Models\User;

/**
 * User Repository
 *
 * Handles data access for users
 */
class UserRepository implements RepositoryInterface
{
    private User $model;

    public function __construct(?User $model = null)
    {
        $this->model = $model ?? new User();
    }

    /**
     * Find a user by ID
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
     * Find a user by ID or throw exception
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
     * Find user with full details
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
     * Find user by email
     *
     * @param string $email
     * @return object|null
     */
    public function findByEmail(string $email): ?object
    {
        return $this->model->findByEmail($email);
    }

    /**
     * Get all users with filters
     *
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 10): array
    {
        if (empty($filters) && $page === 1 && $limit === 10) {
            return $this->model->getAllUsers();
        }

        return $this->model->getAll($filters, $page, $limit);
    }

    /**
     * Get users by role
     *
     * @param int $roleId
     * @return array
     */
    public function getByRole(int $roleId): array
    {
        return $this->model->getAll(['role_id' => $roleId])['records'] ?? [];
    }

    /**
     * Get users by company
     *
     * @param int $companyId
     * @return array
     */
    public function getByCompany(int $companyId): array
    {
        return $this->model->getAll(['company_id' => $companyId])['records'] ?? [];
    }

    /**
     * Get active users
     *
     * @return array
     */
    public function getActiveUsers(): array
    {
        return $this->model->getAll(['is_active' => 1])['records'] ?? [];
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return int User ID
     */
    public function create(array $data): int
    {
        return $this->model->create($data);
    }

    /**
     * Update a user
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
     * Delete a user (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    /**
     * Count users matching criteria
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->model->count($conditions);
    }

    /**
     * Check if user exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Get user's projects
     *
     * @param int $userId
     * @return array
     */
    public function getUserProjects(int $userId): array
    {
        return $this->model->getUserProjects($userId);
    }

    /**
     * Get user's active tasks
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserActiveTasks(int $userId, int $limit = 5): array
    {
        return $this->model->getUserActiveTasks($userId, $limit);
    }

    /**
     * Get user's roles and permissions
     *
     * @param int $userId
     * @return array
     */
    public function getRolesAndPermissions(int $userId): array
    {
        return $this->model->getRolesAndPermissions($userId);
    }

    /**
     * Add project to user
     *
     * @param int $userId
     * @param int $projectId
     * @return bool
     */
    public function addProject(int $userId, int $projectId): bool
    {
        return $this->model->addProject($userId, $projectId);
    }

    /**
     * Remove project from user
     *
     * @param int $userId
     * @param int $projectId
     * @return bool
     */
    public function removeProject(int $userId, int $projectId): bool
    {
        return $this->model->removeProject($userId, $projectId);
    }
}
