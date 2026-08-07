<?php

// file: Models/Permission.php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

/**
 * Permission Model
 *
 * Handles all permission-related database operations
 */
class Permission extends BaseModel
{
    protected string $table = 'permissions';

    /**
     * Permission properties
     */
    public ?int $id = null;
    public string $name;
    public ?string $description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'name', 'description',
    ];

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'name', 'description',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'name' => ['required', 'string', 'unique'],
        'description' => ['string'],
    ];

    /**
     * Get permissions by role ID
     *
     * @param int $roleId
     * @return array
     * @throws RuntimeException
     */
    public function getByRoleId(int $roleId): array
    {
        try {
            $sql = "SELECT p.*
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE rp.role_id = :role_id 
                    AND p.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':role_id' => $roleId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get permissions for role: " . $e->getMessage());
        }
    }

    /**
     * Associate permissions with a role
     *
     * @param int $roleId
     * @param array $permissionIds
     * @return bool
     * @throws RuntimeException
     */
    public function assignToRole(int $roleId, array $permissionIds): bool
    {
        $owned = $this->beginTransactionIfNone();

        try {
            // First, remove existing permissions
            $sql = "DELETE FROM role_permissions WHERE role_id = :role_id";
            $this->db->executeInsertUpdate($sql, [':role_id' => $roleId]);

            // Then, add new permissions
            if (!empty($permissionIds)) {
                foreach ($permissionIds as $permissionId) {
                    $sql = "INSERT INTO role_permissions (role_id, permission_id) 
                            VALUES (:role_id, :permission_id)";
                    $this->db->executeInsertUpdate($sql, [
                        ':role_id' => $roleId,
                        ':permission_id' => $permissionId,
                    ]);
                }
            }

            $this->commitIfOwned($owned);

            return true;

        } catch (\Exception $e) {
            $this->rollBackIfOwned($owned);

            throw new RuntimeException("Failed to assign permissions: " . $e->getMessage());
        }
    }

    /**
     * Get permissions grouped by type
     *
     * @return array
     */
    public function getGroupedPermissions(): array
    {
        try {
            $permissions = $this->getAll(['is_deleted' => 0], 1, 1000)['records'];
            $grouped = [];

            foreach ($permissions as $permission) {
                $parts = explode('_', $permission->name);
                $type = $parts[0] ?? 'other';  // Default group is 'other'

                if (!isset($grouped[$type])) {
                    $grouped[$type] = [];
                }
                $grouped[$type][] = $permission;
            }

            // Sort groups alphabetically
            ksort($grouped);

            return $grouped;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to group permissions: " . $e->getMessage());
        }
    }

    /**
     * Get permissions organized by entity and action for enhanced UI
     *
     * @return array
     */
    public function getOrganizedPermissions(): array
    {
        try {
            $permissions = $this->getAll(['is_deleted' => 0], 1, 1000)['records'];
            $organized = [];

            // Define entity order and metadata
            $entityConfig = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => 'chart-bar',
                    'description' => 'Access to dashboard and analytics',
                ],
                'projects' => [
                    'label' => 'Projects',
                    'icon' => 'folder',
                    'description' => 'Project management and oversight',
                ],
                'tasks' => [
                    'label' => 'Tasks',
                    'icon' => 'clipboard-list',
                    'description' => 'Task creation and management',
                ],
                'milestones' => [
                    'label' => 'Milestones',
                    'icon' => 'flag',
                    'description' => 'Milestone and epic management',
                ],
                'sprints' => [
                    'label' => 'Sprints',
                    'icon' => 'lightning-bolt',
                    'description' => 'Sprint planning and execution',
                ],
                'time_tracking' => [
                    'label' => 'Time Tracking',
                    'icon' => 'clock',
                    'description' => 'Time tracking and reporting',
                ],
                'users' => [
                    'label' => 'Users',
                    'icon' => 'users',
                    'description' => 'User account management',
                ],
                'roles' => [
                    'label' => 'Roles',
                    'icon' => 'shield-check',
                    'description' => 'Role and permission management',
                ],
                'companies' => [
                    'label' => 'Companies',
                    'icon' => 'office-building',
                    'description' => 'Company and organization management',
                ],
                'templates' => [
                    'label' => 'Templates',
                    'icon' => 'template',
                    'description' => 'Template creation and management',
                ],
                'settings' => [
                    'label' => 'Settings',
                    'icon' => 'cog',
                    'description' => 'System configuration and settings',
                ],
            ];

            // Define action levels and their hierarchy
            $actionLevels = [
                'view' => ['level' => 1, 'label' => 'View', 'color' => 'blue'],
                'create' => ['level' => 2, 'label' => 'Create', 'color' => 'green'],
                'edit' => ['level' => 3, 'label' => 'Edit', 'color' => 'yellow'],
                'delete' => ['level' => 4, 'label' => 'Delete', 'color' => 'red'],
                'manage' => ['level' => 5, 'label' => 'Manage All', 'color' => 'purple'],
            ];

            foreach ($permissions as $permission) {
                $parts = explode('_', $permission->name);
                $action = $parts[0] ?? 'other';
                $entity = implode('_', array_slice($parts, 1)) ?: 'other';

                if (!isset($organized[$entity])) {
                    $organized[$entity] = [
                        'config' => $entityConfig[$entity] ?? [
                            'label' => ucwords(str_replace('_', ' ', $entity)),
                            'icon' => 'collection',
                            'description' => 'Management of ' . str_replace('_', ' ', $entity),
                        ],
                        'permissions' => [],
                    ];
                }

                $organized[$entity]['permissions'][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'action' => $action,
                    'action_config' => $actionLevels[$action] ?? [
                        'level' => 0, 'label' => ucfirst($action), 'color' => 'gray',
                    ],
                ];
            }

            // Sort entities by predefined order
            $sortedOrganized = [];
            foreach (array_keys($entityConfig) as $entity) {
                if (isset($organized[$entity])) {
                    // Sort permissions by action level
                    usort($organized[$entity]['permissions'], function ($a, $b) {
                        return $a['action_config']['level'] <=> $b['action_config']['level'];
                    });
                    $sortedOrganized[$entity] = $organized[$entity];
                }
            }

            // Add any remaining entities not in config
            foreach ($organized as $entity => $data) {
                if (!isset($sortedOrganized[$entity])) {
                    $sortedOrganized[$entity] = $data;
                }
            }

            return $sortedOrganized;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to organize permissions: " . $e->getMessage());
        }
    }

    /**
     * Check if permission exists by name
     *
     * @param string $name
     * @return bool
     */
    public function existsByName(string $name): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM permissions WHERE name = :name AND is_deleted = 0";
            $stmt = $this->db->executeQuery($sql, [':name' => $name]);

            return (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to check if permission exists: " . $e->getMessage());
        }
    }

    /**
     * Get permission by name
     *
     * @param string $name
     * @return object|null
     */
    public function getByName(string $name): ?object
    {
        try {
            $sql = "SELECT * FROM permissions WHERE name = :name AND is_deleted = 0";
            $stmt = $this->db->executeQuery($sql, [':name' => $name]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            return $result ?: null;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get permission by name: " . $e->getMessage());
        }
    }

    /**
     * Create a new permission if it doesn't exist
     *
     * @param string $name
     * @param string|null $description
     * @return int Permission ID
     */
    public function createIfNotExists(string $name, ?string $description = null): int
    {
        try {
            // Check if permission already exists
            $permission = $this->getByName($name);
            if ($permission) {
                return $permission->id;
            }

            // Create new permission
            $permissionData = [
                'name' => $name,
                'description' => $description,
            ];

            return $this->create($permissionData);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to create permission: " . $e->getMessage());
        }
    }

    /**
     * Bulk create permissions
     *
     * @param array $permissions Array of permission data
     * @return array Created permission IDs
     */
    public function bulkCreate(array $permissions): array
    {
        $owned = $this->beginTransactionIfNone();

        try {
            $createdIds = [];
            foreach ($permissions as $permission) {
                // Check if permission already exists
                $existing = $this->getByName($permission['name']);
                if ($existing) {
                    $createdIds[] = $existing->id;

                    continue;
                }

                // Create new permission
                $createdIds[] = $this->create([
                    'name' => $permission['name'],
                    'description' => $permission['description'] ?? null,
                ]);
            }

            $this->commitIfOwned($owned);

            return $createdIds;
        } catch (\Exception $e) {
            $this->rollBackIfOwned($owned);

            throw new RuntimeException("Failed to bulk create permissions: " . $e->getMessage());
        }
    }

    /**
     * Get predefined role templates with their permissions
     *
     * @return array
     */
    public function getRoleTemplates(): array
    {
        return [
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'permissions' => [
                    'view_dashboard',
                    'view_projects', 'create_projects', 'edit_projects', 'delete_projects', 'manage_projects',
                    'view_tasks', 'create_tasks', 'edit_tasks', 'delete_tasks', 'manage_tasks',
                    'view_milestones', 'create_milestones', 'edit_milestones', 'delete_milestones', 'manage_milestones',
                    'view_sprints', 'create_sprints', 'edit_sprints', 'delete_sprints', 'manage_sprints',
                    'view_time_tracking', 'create_time_tracking', 'edit_time_tracking', 'delete_time_tracking', 'manage_time_tracking',
                    'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
                    'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
                    'view_companies', 'create_companies', 'edit_companies', 'delete_companies', 'manage_companies',
                    'view_templates', 'create_templates', 'edit_templates', 'delete_templates', 'manage_templates',
                    'view_settings', 'manage_settings',
                ],
            ],
            'manager' => [
                'name' => 'Project Manager',
                'description' => 'Project oversight with team management capabilities',
                'permissions' => [
                    'view_dashboard',
                    'view_projects', 'create_projects', 'edit_projects', 'manage_projects',
                    'view_tasks', 'create_tasks', 'edit_tasks', 'manage_tasks',
                    'view_milestones', 'create_milestones', 'edit_milestones', 'manage_milestones',
                    'view_sprints', 'create_sprints', 'edit_sprints', 'manage_sprints',
                    'view_time_tracking', 'create_time_tracking', 'edit_time_tracking', 'manage_time_tracking',
                    'view_users', 'edit_users',
                    'view_companies', 'view_roles',
                    'view_templates', 'create_templates', 'edit_templates',
                ],
            ],
            'developer' => [
                'name' => 'Developer',
                'description' => 'Development team member with task and time tracking access',
                'permissions' => [
                    'view_dashboard',
                    'view_projects',
                    'view_tasks', 'create_tasks', 'edit_tasks',
                    'view_milestones', 'view_sprints',
                    'view_time_tracking', 'create_time_tracking', 'edit_time_tracking',
                    'view_users', 'view_companies',
                    'view_templates',
                ],
            ],
            'client' => [
                'name' => 'Client',
                'description' => 'Limited access for external clients and stakeholders',
                'permissions' => [
                    'view_dashboard', 'view_projects', 'view_tasks', 'view_milestones', 'view_sprints',
                ],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Read-only access to most areas',
                'permissions' => [
                    'view_dashboard',
                    'view_projects', 'view_tasks', 'view_milestones', 'view_sprints',
                    'view_time_tracking', 'view_users', 'view_companies', 'view_roles',
                    'view_templates',
                ],
            ],
        ];
    }

    /**
     * Get permission IDs for a role template
     *
     * @param string $templateKey
     * @return array
     */
    public function getTemplatePermissionIds(string $templateKey): array
    {
        $templates = $this->getRoleTemplates();

        if (!isset($templates[$templateKey])) {
            return [];
        }

        $permissionNames = $templates[$templateKey]['permissions'];
        $permissionIds = [];

        foreach ($permissionNames as $name) {
            $permission = $this->getByName($name);
            if ($permission) {
                $permissionIds[] = $permission->id;
            }
        }

        return $permissionIds;
    }
}
