<?php

// file: Middleware/AuthMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpResponse;
use App\Models\User;
use App\Services\SettingsService;

class AuthMiddleware
{
    private const PATHS = [
        'login' => '/login',
        'dashboard' => '/dashboard',
        'unauthorized' => '/dashboard',
    ];

    private User $userModel;
    private SettingsService $settingsService;
    private ?HttpResponse $denial = null;

    public function __construct()
    {
        $this->userModel = new User();
        $this->settingsService = SettingsService::getInstance();
    }

    /**
     * Verify user authentication status
     */
    public function isAuthenticated(): bool
    {
        try {
            // Check for session existence
            if (!isset($_SESSION['user'])) {
                $this->handleUnauthenticated('You must be logged in to access this page.');

                return false;
            }

            // Initialize last activity if not set
            if (!isset($_SESSION['last_activity'])) {
                $_SESSION['last_activity'] = time();
            }

            // Validate session timeout
            if ($this->isSessionExpired()) {
                $this->handleSessionTimeout();

                return false;
            }

            // Validate user in database
            if (!$this->validateUserSession()) {
                return false;
            }

            // Update last activity time
            $this->updateSessionActivity();

            // Ensure permissions are loaded
            $this->loadUserPermissions();

            return true;

        } catch (\Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            $this->handleUnauthenticated('An error occurred during authentication.');

            return false;
        }
    }

    /**
     * Check specific permission
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        if (!$this->checkPermission($permission)) {
            $this->handleUnauthorized();

            return false;
        }

        return true;
    }

    /**
     * Check for any of the given permissions
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $userPermissions = $_SESSION['user']['permissions'] ?? [];
        $hasPermission = false;

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                $hasPermission = true;

                break;
            }
        }

        if (!$hasPermission) {
            $this->handleUnauthorized();

            return false;
        }

        return true;
    }

    /**
     * Check for all required permissions
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $userPermissions = $_SESSION['user']['permissions'] ?? [];

        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions, true)) {
                $this->handleUnauthorized();

                return false;
            }
        }

        return true;
    }

    /**
     * Validate user session against database
     * @return bool
     */
    private function validateUserSession(): bool
    {
        $userId = $_SESSION['user']['profile']['id'] ?? null;
        if (!$userId) {
            $this->handleUnauthenticated('Invalid session data.');

            return false;
        }

        $user = $this->userModel->find($userId);
        if (!$user || !$user->is_active) {
            $this->handleInactiveAccount();

            return false;
        }

        return true;
    }

    /**
     * Load user permissions if not already loaded
     */
    private function loadUserPermissions(): void
    {
        if (!isset($_SESSION['user']['permissions'])) {
            $userId = $_SESSION['user']['profile']['id'];
            $_SESSION['user']['permissions'] = $this->userModel->getRolesAndPermissions($userId)['permissions'];
        }
    }

    /**
     * Check if session has expired
     */
    private function isSessionExpired(): bool
    {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }

        $lastActivity = (int)$_SESSION['last_activity'];
        $timeElapsed = time() - $lastActivity;
        $sessionTimeout = $this->settingsService->getSessionTimeout();

        return $timeElapsed > $sessionTimeout;
    }

    /**
     * Update session activity timestamp
     */
    private function updateSessionActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Handle unauthenticated access
     * @param string $message
     */
    private function handleUnauthenticated(string $message): void
    {
        $_SESSION['error'] = $message;
        $this->redirect(self::PATHS['login']);
    }

    /**
     * Handle unauthorized access
     */
    private function handleUnauthorized(): void
    {
        $_SESSION['error'] = 'You do not have permission to access this resource.';
        $this->redirect(self::PATHS['unauthorized']);
    }

    /**
     * Handle inactive account
     */
    private function handleInactiveAccount(): void
    {
        unset($_SESSION['user']);
        $_SESSION['error'] = 'Your account is no longer active. Please contact support.';
        $this->redirect(self::PATHS['login']);
    }

    /**
     * Handle session timeout
     */
    private function handleSessionTimeout(): void
    {
        unset($_SESSION['user']);
        $_SESSION['error'] = 'Your session has expired. Please log in again.';
        $this->redirect(self::PATHS['login']);
    }

    /**
     * Check individual permission
     * @param string $permission
     * @return bool
     */
    private function checkPermission(string $permission): bool
    {
        $userPermissions = $_SESSION['user']['permissions'] ?? [];

        return in_array($permission, $userPermissions, true);
    }

    /**
     * Perform redirect
     * @param string $path
     */
    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    /**
     * The authentication decision, with no redirect and no exit.
     *
     * Returns null when the request is authenticated, or the response that
     * should be sent when it is not. Session side effects that are part of the
     * decision (clearing a dead session, setting the flash message, refreshing
     * last_activity, loading permissions) are kept exactly as they were.
     */
    private function checkAuthentication(): ?HttpResponse
    {
        try {
            if (!isset($_SESSION['user'])) {
                return $this->unauthenticatedResponse('You must be logged in to access this page.');
            }

            if (!isset($_SESSION['last_activity'])) {
                $_SESSION['last_activity'] = time();
            }

            if ($this->isSessionExpired()) {
                return $this->sessionTimeoutResponse();
            }

            $userId = $_SESSION['user']['profile']['id'] ?? null;
            if (!$userId) {
                return $this->unauthenticatedResponse('Invalid session data.');
            }

            $user = $this->userModel->find($userId);
            if (!$user || !$user->is_active) {
                return $this->inactiveAccountResponse();
            }

            $this->updateSessionActivity();
            $this->loadUserPermissions();

            return null;
        } catch (\Exception $e) {
            error_log("Authentication error: " . $e->getMessage());

            return $this->unauthenticatedResponse('An error occurred during authentication.');
        }
    }

    private function unauthenticatedResponse(string $message): HttpResponse
    {
        $_SESSION['error'] = $message;

        return HttpResponse::redirect(self::PATHS['login']);
    }

    private function unauthorizedResponse(): HttpResponse
    {
        $_SESSION['error'] = 'You do not have permission to access this resource.';

        return HttpResponse::redirect(self::PATHS['unauthorized']);
    }

    private function inactiveAccountResponse(): HttpResponse
    {
        unset($_SESSION['user']);
        $_SESSION['error'] = 'Your account is no longer active. Please contact support.';

        return HttpResponse::redirect(self::PATHS['login']);
    }

    private function sessionTimeoutResponse(): HttpResponse
    {
        unset($_SESSION['user']);
        $_SESSION['error'] = 'Your session has expired. Please log in again.';

        return HttpResponse::redirect(self::PATHS['login']);
    }

    public function authenticate(): ?HttpResponse
    {
        return $this->checkAuthentication();
    }

    public function authorize(string $permission): ?HttpResponse
    {
        return $this->authorizeAll([$permission]);
    }

    /** @param list<string> $permissions */
    public function authorizeAny(array $permissions): ?HttpResponse
    {
        if (($denial = $this->checkAuthentication()) !== null) {
            return $denial;
        }

        foreach ($permissions as $permission) {
            if ($this->checkPermission($permission)) {
                return null;
            }
        }

        return $this->unauthorizedResponse();
    }

    /** @param list<string> $permissions */
    public function authorizeAll(array $permissions): ?HttpResponse
    {
        if (($denial = $this->checkAuthentication()) !== null) {
            return $denial;
        }

        foreach ($permissions as $permission) {
            if (!$this->checkPermission($permission)) {
                return $this->unauthorizedResponse();
            }
        }

        return null;
    }
}
