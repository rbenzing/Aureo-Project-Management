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
        return $this->asPureProbe(fn (): ?HttpResponse => $this->checkAuthentication());
    }

    /**
     * Check specific permission
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return $this->asPureProbe(fn (): ?HttpResponse => $this->authorize($permission));
    }

    /**
     * Check for any of the given permissions
     * @param list<string> $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->asPureProbe(fn (): ?HttpResponse => $this->authorizeAny($permissions));
    }

    /**
     * Check for all required permissions
     * @param list<string> $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return $this->asPureProbe(fn (): ?HttpResponse => $this->authorizeAll($permissions));
    }

    /**
     * Runs a denial-capable check as a side-effect-free probe.
     *
     * checkAuthentication()/authorize()/authorizeAny()/authorizeAll() are built
     * for the redirect-response flow, where the flash message an *Response()
     * builder writes to $_SESSION['error'] is always consumed immediately by
     * the redirect that follows it. The four boolean predicates above have no
     * redirect to consume that flash — they exist precisely to be used as
     * probes (e.g. conditional UI) — so without this, a denial would leave the
     * flash orphaned in the session for whatever page renders next. This
     * restores $_SESSION['error'] to exactly what it was before the check,
     * including restoring "absent" as absent rather than null.
     */
    private function asPureProbe(callable $check): bool
    {
        $hadPriorError = array_key_exists('error', $_SESSION);
        $priorError = $hadPriorError ? $_SESSION['error'] : null;

        $result = $check() === null;

        if ($hadPriorError) {
            $_SESSION['error'] = $priorError;
        } else {
            unset($_SESSION['error']);
        }

        return $result;
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
