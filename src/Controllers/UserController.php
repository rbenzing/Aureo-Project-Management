<?php

// file: Controllers/UserController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Middleware\AuthMiddleware;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Utils\Email;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class UserController extends BaseController
{
    private User $userModel;
    private Company $companyModel;
    private Role $roleModel;

    public function __construct(
        ?User $userModel = null,
        ?Company $companyModel = null,
        ?Role $roleModel = null
    ) {
        parent::__construct();
        $this->userModel = $userModel ?? new User();
        $this->companyModel = $companyModel ?? new Company();
        $this->roleModel = $roleModel ?? new Role();
    }

    /**
     * Display paginated list of users
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_users');

            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $settingsService = \App\Services\SettingsService::getInstance();
            $limit = $settingsService->getResultsPerPage();

            $results = $this->userModel->getAll(['is_deleted' => 0], $page, $limit);
            $users = $results['records'];
            $totalUsers = $results['total'];
            $totalPages = ceil($totalUsers / $limit);

            $this->render('Users/index', compact('totalPages', 'totalUsers', 'users', 'results', 'limit', 'settingsService', 'page'));
        } catch (\Exception $e) {
            error_log("Exception in UserController::index: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching users.');
        }
    }

    /**
     * View user details
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_users');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid user ID');
            }

            $user = $this->userModel->findWithDetails($id);
            if (!$user || $user->is_deleted) {
                throw new InvalidArgumentException('User not found');
            }

            // Get user's roles and permissions
            $userRoleData = $this->userModel->getRolesAndPermissions($id);
            $user->roles = $userRoleData['roles'];
            $user->permissions = $userRoleData['permissions'];

            $this->render('Users/view', compact('userRoleData'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/users', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in UserController::view: " . $e->getMessage());
            $this->redirectWithError('/users', 'An error occurred while fetching user details.');
        }
    }

    /**
     * View current user's profile
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function profile(string $requestMethod, array $data): void
    {
        try {
            // Check if user is logged in
            if (!isset($_SESSION['user']['profile']['id'])) {
                $this->redirectWithError('/login', 'You must be logged in to view your profile.');
            }

            $userId = $_SESSION['user']['profile']['id'];

            $user = $this->userModel->findWithDetails($userId);
            if (!$user || $user->is_deleted) {
                $this->redirectWithError('/dashboard', 'Profile not found.');
            }

            // Get user's roles and permissions
            $userRoleData = $this->userModel->getRolesAndPermissions($userId);
            $user->roles = $userRoleData['roles'];
            $user->permissions = $userRoleData['permissions'];

            // Pass data for breadcrumb
            $data = [];

            $this->render('Users/profile', compact('data', 'userRoleData'));
        } catch (\Exception $e) {
            error_log("Exception in UserController::profile: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching your profile.');
        }
    }

    /**
     * Display user creation form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_users');

            $companiesResult = $this->companyModel->getAll(['is_deleted' => 0], 1, 1000);
            $companies = $companiesResult['records'];
            $rolesResult = $this->roleModel->getAll(['is_deleted' => 0], 1, 1000);
            $roles = $rolesResult['records'];

            $this->render('Users/create', compact('data', 'userRoleData'));
        } catch (\Exception $e) {
            error_log("Exception in UserController::createForm: " . $e->getMessage());
            $this->redirectWithError('/users', 'An error occurred while loading the creation form.');
        }
    }

    /**
     * Create new user
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
            $this->requirePermission('create_users');

            $validator = new Validator($data, [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|unique:users,email',
                'role_id' => 'required|integer|exists:roles,id',
                'company_id' => 'nullable|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $userData = [
                'first_name' => htmlspecialchars($data['first_name']),
                'last_name' => htmlspecialchars($data['last_name']),
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'role_id' => filter_var($data['role_id'], FILTER_VALIDATE_INT),
                'company_id' => !empty($data['company_id']) ?
                    filter_var($data['company_id'], FILTER_VALIDATE_INT) : null,
                'password_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_ARGON2ID),
                'is_active' => false,
            ];

            $userId = $this->userModel->create($userData);
            $activationToken = $this->userModel->generateActivationToken($userId);

            // Send activation email
            Email::sendActivationEmail($userData['email'], $activationToken);

            $this->redirectWithSuccess('/users', 'User created successfully. An activation email has been sent.');

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = Config::getErrorMessage(
                $e,
                'UserController::create (validation)',
                $e->getMessage()
            );
            $_SESSION['form_data'] = $data;
            $this->redirect('/users/create');
        } catch (\Exception $e) {
            $this->redirectWithError('/users/create', Config::getErrorMessage(
                $e,
                'UserController::create',
                'An error occurred while creating the user.'
            ));
        }
    }

    /**
     * Display user edit form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_users');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid user ID');
            }

            $user = $this->userModel->findWithDetails($id);
            if (!$user || $user->is_deleted) {
                throw new InvalidArgumentException('User not found');
            }

            $companiesResult = $this->companyModel->getAll(['is_deleted' => 0], 1, 1000);
            $companies = $companiesResult['records'];
            $rolesResult = $this->roleModel->getAll(['is_deleted' => 0], 1, 1000);
            $roles = $rolesResult['records'];

            $this->render('Users/edit', compact('roles', 'rolesResult', 'companies', 'companiesResult'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/users', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in UserController::editForm: " . $e->getMessage());
            $this->redirectWithError('/users', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Update existing user
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
            $this->requirePermission('edit_users');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid user ID');
            }

            $validator = new Validator($data, [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => "required|email|unique:users,email,{$id}",
                'role_id' => 'required|integer|exists:roles,id',
                'company_id' => 'nullable|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $userData = [
                'first_name' => htmlspecialchars($data['first_name']),
                'last_name' => htmlspecialchars($data['last_name']),
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'role_id' => filter_var($data['role_id'], FILTER_VALIDATE_INT),
                'company_id' => !empty($data['company_id']) ?
                    filter_var($data['company_id'], FILTER_VALIDATE_INT) : null,
            ];

            $this->userModel->update($id, $userData);

            $this->redirectWithSuccess('/users', 'User updated successfully.');

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header("Location: /users/edit/{$id}");
            exit;
        } catch (\Exception $e) {
            error_log("Exception in UserController::update: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating the user.';
            header("Location: /users/edit/{$id}");
            exit;
        }
    }

    /**
     * Delete user (soft delete)
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/users', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_users');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid user ID');
            }

            // Check if user exists and is not already deleted
            $user = $this->userModel->find($id);
            if (!$user || $user->is_deleted) {
                throw new InvalidArgumentException('User not found');
            }

            // Prevent deleting own account
            if ($id === ($_SESSION['user']['id'] ?? null)) {
                throw new InvalidArgumentException('Cannot delete your own account');
            }

            $this->userModel->update($id, ['is_deleted' => true]);

            $this->redirectWithSuccess('/users', 'User deleted successfully.');

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/users', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in UserController::delete: " . $e->getMessage());
            $this->redirectWithError('/users', 'An error occurred while deleting the user.');
        }
    }
}
