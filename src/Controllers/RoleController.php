<?php

// file: Controllers/RoleController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\CSRFMiddleware;
use App\Models\Permission;
use App\Models\Role;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class RoleController extends BaseController
{
    private CSRFMiddleware $csrfMiddleware;
    private Role $roleModel;
    private Permission $permissionModel;

    public function __construct(
        ?Role $roleModel = null,
        ?Permission $permissionModel = null
    ) {
        parent::__construct();
        $this->csrfMiddleware = new CSRFMiddleware();
        $this->roleModel = $roleModel ?? new Role();
        $this->permissionModel = $permissionModel ?? new Permission();
    }

    /**
     * Display paginated list of roles
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_roles');

            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $settingsService = \App\Services\SettingsService::getInstance();
            $limit = $settingsService->getResultsPerPage();

            $results = $this->roleModel->getAllWithDetails($page, $limit);
            $roles = $results['records'];
            $totalRoles = $results['total'];
            $totalPages = ceil($totalRoles / $limit);

            $this->render('Roles/index', compact('totalPages', 'totalRoles', 'roles', 'results', 'limit', 'settingsService', 'page'));
        } catch (\Exception $e) {
            error_log("Exception in RoleController::index: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching roles.');
        }
    }

    /**
     * View role details
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_roles');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid role ID');
            }

            $role = $this->roleModel->findWithPermissions($id);
            if (!$role || $role->is_deleted) {
                throw new InvalidArgumentException('Role not found');
            }

            $this->render('Roles/view', compact('totalPages', 'totalRoles', 'roles', 'results', 'limit', 'settingsService', 'page'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/roles', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in RoleController::view: " . $e->getMessage());
            $this->redirectWithError('/roles', 'An error occurred while fetching role details.');
        }
    }

    /**
     * Display role creation form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_roles');

            $permissions = $this->permissionModel->getOrganizedPermissions();

            $this->render('Roles/create', compact('totalPages', 'totalRoles', 'roles', 'results', 'limit', 'settingsService', 'page'));
        } catch (\Exception $e) {
            error_log("Exception in RoleController::createForm: " . $e->getMessage());
            $this->redirectWithError('/roles', 'An error occurred while loading the creation form.');
        }
    }

    /**
     * Create new role
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function create(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->createForm($requestMethod, $data);

            return;
        }

        try {
            $this->requirePermission('create_roles');

            // Validate CSRF token
            if (!$this->csrfMiddleware->validateToken($data['csrf_token'] ?? '')) {
                throw new InvalidArgumentException('Invalid CSRF token');
            }

            $validator = new Validator($data, [
                'name' => 'required|string|max:100|unique:roles,name',
                'description' => 'nullable|string|max:500',
                'permissions' => 'array',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            // Begin transaction
            $this->roleModel->beginTransaction();

            try {
                $roleData = [
                    'name' => htmlspecialchars($data['name']),
                    'description' => isset($data['description']) ?
                        htmlspecialchars($data['description']) : null,
                ];

                $roleId = $this->roleModel->create($roleData);

                // Assign permissions if any
                if (!empty($data['permissions'])) {
                    $permissions = array_map('intval', $data['permissions']);
                    $this->permissionModel->assignToRole($roleId, $permissions);
                }

                $this->roleModel->commit();

                $this->redirectWithSuccess('/roles', 'Role created successfully.');

            } catch (\Exception $e) {
                $this->roleModel->rollBack();

                throw $e;
            }

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            $this->redirect('/roles/create');
        } catch (\Exception $e) {
            error_log("Exception in RoleController::create: " . $e->getMessage());
            $this->redirectWithError('/roles/create', 'An error occurred while creating the role.');
        }
    }

    /**
     * Display role edit form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_roles');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid role ID');
            }

            $role = $this->roleModel->findWithPermissions($id);
            if (!$role || $role->is_deleted) {
                throw new InvalidArgumentException('Role not found');
            }

            $permissions = $this->permissionModel->getOrganizedPermissions();

            $this->render('Roles/edit', compact('permissions'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/roles', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in RoleController::editForm: " . $e->getMessage());
            $this->redirectWithError('/roles', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Update existing role
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function update(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->editForm($requestMethod, $data);

            return;
        }

        try {
            $this->requirePermission('edit_roles');

            // Validate CSRF token
            if (!$this->csrfMiddleware->validateToken($data['csrf_token'] ?? '')) {
                throw new InvalidArgumentException('Invalid CSRF token');
            }

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid role ID');
            }

            $validator = new Validator($data, [
                'name' => 'required|string|max:100|unique:roles,name',
                'description' => 'nullable|string|max:500',
                'permissions' => 'array',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            // Begin transaction
            $this->roleModel->beginTransaction();

            try {
                $roleData = [
                    'name' => htmlspecialchars($data['name']),
                    'description' => isset($data['description']) ?
                        htmlspecialchars($data['description']) : null,
                ];

                $this->roleModel->update($id, $roleData);

                // Sync permissions
                $permissions = !empty($data['permissions']) ?
                    array_map('intval', $data['permissions']) : [];
                $this->roleModel->syncPermissions($id, $permissions);

                $this->roleModel->commit();

                $this->redirectWithSuccess('/roles', 'Role updated successfully.');

            } catch (\Exception $e) {
                $this->roleModel->rollBack();

                throw $e;
            }

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header("Location: /roles/edit/{$id}");
            exit;
        } catch (\Exception $e) {
            error_log("Exception in RoleController::update: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating the role.';
            header("Location: /roles/edit/{$id}");
            exit;
        }
    }

    /**
     * Delete role (soft delete)
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/roles', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_roles');

            // Validate CSRF token
            if (!$this->csrfMiddleware->validateToken($data['csrf_token'] ?? '')) {
                throw new InvalidArgumentException('Invalid CSRF token');
            }

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid role ID');
            }

            // Check if role exists and is not already deleted
            $role = $this->roleModel->find($id);
            if (!$role || $role->is_deleted) {
                throw new InvalidArgumentException('Role not found');
            }

            // Check if role is in use
            $users = $this->roleModel->getUsers($id);
            if (!empty($users)) {
                throw new InvalidArgumentException('Cannot delete role as it is currently assigned to one or more users.');
            }

            $this->roleModel->update($id, ['is_deleted' => true]);

            $this->redirectWithSuccess('/roles', 'Role deleted successfully.');

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/roles', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in RoleController::delete: " . $e->getMessage());
            $this->redirectWithError('/roles', 'An error occurred while deleting the role.');
        }
    }
}
