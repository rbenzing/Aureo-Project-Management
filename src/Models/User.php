<?php

// file: Models/User.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Enums\TaskStatus;
use App\Services\SecurityService;
use DateTime;
use PDO;
use RuntimeException;

/**
 * User Model
 *
 * Handles all user-related database operations
 */
class User extends BaseModel
{
    protected string $table = 'users';

    /**
     * User properties
     */
    public ?int $id = null;
    public ?int $company_id = null;
    public int $role_id;
    public string $first_name;
    public string $last_name;
    public string $email;
    public ?string $phone = null;
    public string $password_hash;
    public bool $is_active = false;
    public ?string $activation_token = null;
    public ?string $activation_token_expires_at = null;
    public ?string $reset_password_token = null;
    public ?string $reset_password_token_expires_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public bool $is_deleted = false;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'company_id', 'role_id', 'first_name', 'last_name',
        'email', 'phone', 'password_hash', 'is_active',
    ];

    /**
     * Define hidden fields (sensitive data not to be returned)
     */
    protected array $hidden = [
        'password_hash', 'activation_token', 'reset_password_token',
    ];

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'first_name', 'last_name', 'email',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'first_name' => ['required', 'string'],
        'last_name' => ['required', 'string'],
        'email' => ['required', 'email', 'unique'],
        'role_id' => ['required'],
    ];

    /**
     * Flexible user query method with various filters
     * Consolidates multiple similar query methods into one
     *
     * @param array $filters Filter conditions:
     *   - email: string
     *   - role_id: int
     *   - company_id: int
     *   - is_active: bool
     *   - id: int
     * @param int $limit Results per page (0 for no limit)
     * @param int $page Page number
     * @param string $sortField Field to sort by
     * @param string $sortDirection Sort direction (asc/desc)
     * @return array Users array
     */
    private function getUsersWithDetails(
        array $filters = [],
        int $limit = 0,
        int $page = 1,
        string $sortField = 'first_name',
        string $sortDirection = 'asc'
    ): array {
        $where = [];
        $whereRaw = [
            ['sql' => 'u.is_deleted = 0']
        ];

        // Apply filters
        if (isset($filters['email'])) {
            $where[] = ['column' => 'u.email', 'operator' => '=', 'value' => $filters['email']];
        }

        if (isset($filters['id'])) {
            $where[] = ['column' => 'u.id', 'operator' => '=', 'value' => $filters['id']];
        }

        if (isset($filters['role_id'])) {
            $where[] = ['column' => 'u.role_id', 'operator' => '=', 'value' => $filters['role_id']];
        }

        if (isset($filters['company_id'])) {
            $where[] = ['column' => 'u.company_id', 'operator' => '=', 'value' => $filters['company_id']];
        }

        if (isset($filters['is_active'])) {
            $where[] = ['column' => 'u.is_active', 'operator' => '=', 'value' => $filters['is_active'] ? 1 : 0];
        }

        $options = [
            'select' => 'u.*, c.name as company_name, r.name as role_name',
            'alias' => 'u',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'companies c', 'on' => 'u.company_id = c.id'],
                ['type' => 'LEFT', 'table' => 'roles r', 'on' => 'u.role_id = r.id'],
            ],
            'where' => $where,
            'whereRaw' => $whereRaw,
            'orderBy' => "u.{$sortField} {$sortDirection}",
        ];

        if ($limit > 0) {
            $options['limit'] = $limit;
            $options['offset'] = ($page - 1) * $limit;
        }

        return $this->queryBuilder($options);
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return object|null
     */
    public function findByEmail(string $email): ?object
    {
        try {
            $users = $this->getUsersWithDetails(['email' => $email], 1);
            return $users[0] ?? null;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to find user by email: " . $e->getMessage());
        }
    }

    /**
     * Get all users without pagination
     *
     * @return array
     */
    public function getAllUsers(): array
    {
        try {
            return $this->getUsersWithDetails([], 0, 1, 'first_name, u.last_name', 'asc');
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get all users: " . $e->getMessage());
        }
    }

    /**
     * Get user roles and permissions
     *
     * @param int $userId
     * @return array
     */
    public function getRolesAndPermissions(int $userId): array
    {
        try {
            // Get user's role
            $sql = "SELECT r.name AS role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = :user_id
                AND u.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);
            $roleResult = $stmt->fetch(PDO::FETCH_ASSOC);

            $roles = [];
            if ($roleResult) {
                $roles[] = $roleResult['role_name'];
            }

            // Get permissions for that role
            $sql = "SELECT p.name AS permission_name
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN users u ON rp.role_id = u.role_id
                WHERE u.id = :user_id
                AND p.is_deleted = 0
                AND u.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);
            $permissionResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $permissions = [];
            foreach ($permissionResults as $permission) {
                $permissions[] = $permission['permission_name'];
            }

            return [
                'roles' => $roles,
                'permissions' => $permissions,
            ];
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get roles and permissions: " . $e->getMessage());
        }
    }

    /**
     * Find user with detailed information
     *
     * @param int $id
     * @param array $options Selective loading options:
     *   - projects: bool (default true)
     *   - permissions: bool (default true)
     *   - companies: bool (default true)
     *   - active_tasks: bool (default true)
     * @return object|null
     */
    public function findWithDetails(int $id, array $options = []): ?object
    {
        try {
            // Default options - load everything unless specified otherwise
            $defaultOptions = [
                'projects' => true,
                'permissions' => true,
                'companies' => true,
                'active_tasks' => true,
            ];

            $options = array_merge($defaultOptions, $options);

            $users = $this->getUsersWithDetails(['id' => $id], 1);
            $user = $users[0] ?? null;

            if ($user) {
                // Selectively load related data based on options
                if ($options['projects']) {
                    $user->projects = $this->getUserProjects($id);
                }

                if ($options['permissions']) {
                    $rolesAndPermissions = $this->getRolesAndPermissions($id);
                    $user->permissions = $rolesAndPermissions['permissions'];
                }

                if ($options['companies']) {
                    $user->companies = $this->getUserCompanies($id);
                }

                if ($options['active_tasks']) {
                    $user->active_tasks = $this->getUserActiveTasks($id);
                }
            }

            return $user;
        } catch (\Exception $e) {
            try {
                $securityService = SecurityService::getInstance();
                $safeMessage = $securityService->getSafeErrorMessage($e->getMessage(), "Failed to find user with details");

                throw new RuntimeException($safeMessage);
            } catch (\Exception $securityException) {
                throw new RuntimeException("Failed to find user with details");
            }
        }
    }

    /**
     * Find user with basic information only (no related data)
     *
     * @param int $id
     * @return object|null
     */
    public function findBasic(int $id): ?object
    {
        return $this->findWithDetails($id, [
            'projects' => false,
            'permissions' => false,
            'companies' => false,
            'active_tasks' => false,
        ]);
    }

    /**
     * Get user's projects
     *
     * @param int $userId
     * @return array
     */
    public function getUserProjects(int $userId): array
    {
        try {
            $sql = "SELECT DISTINCT p.*, ps.name as status_name
                    FROM projects p
                    LEFT JOIN statuses_project ps ON p.status_id = ps.id
                    WHERE (
                        p.owner_id = :owner_id
                        OR p.id IN (
                            SELECT project_id FROM user_projects WHERE user_id = :user_projects_id
                        )
                        OR p.id IN (
                            SELECT DISTINCT project_id FROM tasks 
                            WHERE assigned_to = :tasks_user_id AND is_deleted = 0
                        )
                    )
                    AND p.is_deleted = 0
                    ORDER BY p.updated_at DESC";

            // Explicitly prepare the statement
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare($sql);

            // Bind parameters explicitly
            $stmt->bindValue(':owner_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_projects_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':tasks_user_id', $userId, PDO::PARAM_INT);

            // Execute the statement
            $stmt->execute();

            // Fetch results
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            // Log the full error details
            error_log("User Projects Query Error: " . $e->getMessage());
            error_log("User ID: " . $userId);

            throw new RuntimeException("Failed to get user projects: " . $e->getMessage());
        }
    }

    /**
     * Get user's companies (from junction table)
     *
     * @param int $userId
     * @return array
     */
    public function getUserCompanies(int $userId): array
    {
        try {
            // Get both primary company and those from junction table
            $sql = "SELECT DISTINCT c.*
                    FROM companies c
                    WHERE (
                        c.id = (SELECT company_id FROM users WHERE id = :user_id AND company_id IS NOT NULL)
                        OR c.id IN (
                            SELECT company_id FROM user_companies WHERE user_id = :company_user_id
                        )
                    )
                    AND c.is_deleted = 0";

            // Explicitly prepare the statement
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare($sql);

            // Bind parameters explicitly
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':company_user_id', $userId, PDO::PARAM_INT);

            // Execute the statement
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get user companies: " . $e->getMessage());
        }
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
        try {
            $closedStatus = TaskStatus::CLOSED->value;
            $completedStatus = TaskStatus::COMPLETED->value;

            $sql = "SELECT t.*,
                        p.name as project_name,
                        ts.name as status_name
                    FROM tasks t
                    LEFT JOIN projects p ON t.project_id = p.id
                    LEFT JOIN statuses_task ts ON t.status_id = ts.id
                    WHERE t.assigned_to = :user_id
                    AND t.status_id NOT IN ({$closedStatus}, {$completedStatus})
                    AND t.is_deleted = 0
                    ORDER BY
                        CASE
                            WHEN t.due_date < CURDATE() THEN 0  -- Overdue
                            WHEN t.due_date = CURDATE() THEN 1  -- Due today
                            ELSE 2                             -- Due later
                        END,
                        t.due_date ASC,
                        t.priority DESC
                    LIMIT :limit";

            $stmt = $this->db->executeQuery($sql, [
                ':user_id' => $userId,
                ':limit' => $limit,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get user active tasks: " . $e->getMessage());
        }
    }

    /**
     * Find user by activation token
     *
     * @param string $token
     * @return object|null
     */
    public function findByActivationToken(string $token): ?object
    {
        try {
            $sql = "SELECT * FROM users 
                    WHERE activation_token = :token 
                    AND activation_token_expires_at > NOW() 
                    AND is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':token' => $token]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            return $user ?: null;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to find user by activation token: " . $e->getMessage());
        }
    }

    /**
     * Find user by reset password token
     *
     * @param string $token
     * @return object|null
     */
    public function findByResetToken(string $token): ?object
    {
        try {
            $sql = "SELECT * FROM users 
                    WHERE reset_password_token = :token 
                    AND reset_password_token_expires_at > NOW() 
                    AND is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':token' => $token]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            return $user ?: null;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to find user by reset token: " . $e->getMessage());
        }
    }

    /**
     * Generate password reset token
     *
     * @param int $userId
     * @return string
     * @throws RuntimeException
     */
    public function generatePasswordResetToken(int $userId): string
    {
        try {
            $token = bin2hex(random_bytes(16));

            // Use timezone-aware date formatting
            $settingsService = \App\Services\SettingsService::getInstance();
            $timezone = $settingsService->getDefaultTimezone();
            $expiresAt = (new DateTime('now', new \DateTimeZone($timezone)))->modify('+1 hour')->format('Y-m-d H:i:s');

            $sql = "UPDATE users 
                    SET reset_password_token = :token,
                        reset_password_token_expires_at = :expires_at,
                        updated_at = NOW()
                    WHERE id = :id";

            $this->db->executeInsertUpdate($sql, [
                ':id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt,
            ]);

            return $token;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate reset token: ' . $e->getMessage());
        }
    }

    /**
     * Generate activation token
     *
     * @param int $userId
     * @return string
     * @throws RuntimeException
     */
    public function generateActivationToken(int $userId): string
    {
        try {
            $token = bin2hex(random_bytes(16));

            // Use timezone-aware date formatting
            $settingsService = \App\Services\SettingsService::getInstance();
            $timezone = $settingsService->getDefaultTimezone();
            $expiresAt = (new DateTime('now', new \DateTimeZone($timezone)))->modify('+24 hours')->format('Y-m-d H:i:s');

            $sql = "UPDATE users 
                    SET activation_token = :token,
                        activation_token_expires_at = :expires_at,
                        updated_at = NOW()
                    WHERE id = :id";

            $this->db->executeInsertUpdate($sql, [
                ':id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt,
            ]);

            return $token;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate activation token: ' . $e->getMessage());
        }
    }

    /**
     * Clear password reset token
     *
     * @param int $userId
     * @return bool
     */
    public function clearPasswordResetToken(int $userId): bool
    {
        try {
            $sql = "UPDATE users 
                    SET reset_password_token = NULL,
                        reset_password_token_expires_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id";

            return $this->db->executeInsertUpdate($sql, [':id' => $userId]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to clear reset token: " . $e->getMessage());
        }
    }

    /**
     * Clear activation token
     *
     * @param int $userId
     * @return bool
     */
    public function clearActivationToken(int $userId): bool
    {
        try {
            $sql = "UPDATE users 
                    SET activation_token = NULL,
                        activation_token_expires_at = NULL,
                        updated_at = NOW()
                    WHERE id = :id";

            return $this->db->executeInsertUpdate($sql, [':id' => $userId]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to clear activation token: " . $e->getMessage());
        }
    }

    /**
     * Add company association
     *
     * @param int $userId
     * @param int $companyId
     * @return bool
     */
    public function addCompany(int $userId, int $companyId): bool
    {
        try {
            $sql = "INSERT INTO user_companies (user_id, company_id)
                    VALUES (:user_id, :company_id)
                    ON DUPLICATE KEY UPDATE user_id = :user_id";

            return $this->db->executeInsertUpdate($sql, [
                ':user_id' => $userId,
                ':company_id' => $companyId,
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to add company association: " . $e->getMessage());
        }
    }

    /**
     * Remove company association
     *
     * @param int $userId
     * @param int $companyId
     * @return bool
     */
    public function removeCompany(int $userId, int $companyId): bool
    {
        try {
            $sql = "DELETE FROM user_companies 
                    WHERE user_id = :user_id AND company_id = :company_id";

            return $this->db->executeInsertUpdate($sql, [
                ':user_id' => $userId,
                ':company_id' => $companyId,
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to remove company association: " . $e->getMessage());
        }
    }

    /**
     * Add project assignment
     *
     * @param int $userId
     * @param int $projectId
     * @return bool
     */
    public function addProject(int $userId, int $projectId): bool
    {
        try {
            $sql = "INSERT INTO user_projects (user_id, project_id)
                    VALUES (:user_id, :project_id)
                    ON DUPLICATE KEY UPDATE user_id = :user_id";

            return $this->db->executeInsertUpdate($sql, [
                ':user_id' => $userId,
                ':project_id' => $projectId,
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to add project assignment: " . $e->getMessage());
        }
    }

    /**
     * Remove project assignment
     *
     * @param int $userId
     * @param int $projectId
     * @return bool
     */
    public function removeProject(int $userId, int $projectId): bool
    {
        try {
            $sql = "DELETE FROM user_projects 
                    WHERE user_id = :user_id AND project_id = :project_id";

            return $this->db->executeInsertUpdate($sql, [
                ':user_id' => $userId,
                ':project_id' => $projectId,
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to remove project assignment: " . $e->getMessage());
        }
    }
}
