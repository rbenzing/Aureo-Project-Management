<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\SettingsService;
use RuntimeException;

/**
 * Base controller providing common functionality for all controllers
 * Follows DRY principle and centralizes repeated patterns
 */
abstract class BaseController
{
    protected AuthMiddleware $authMiddleware;
    protected SettingsService $settingsService;

    /**
     * Initialize auth middleware and settings service
     */
    public function __construct(?AuthMiddleware $authMiddleware = null)
    {
        $this->authMiddleware = $authMiddleware ?? new AuthMiddleware();
        $this->settingsService = SettingsService::getInstance();
    }

    /**
     * Require specific permission, throw exception if not authorized
     *
     * @param string $permission Permission slug to check
     * @throws RuntimeException If user lacks permission
     */
    protected function requirePermission(string $permission): void
    {
        $this->authMiddleware->hasPermission($permission);
    }

    /**
     * Render a view with provided data
     *
     * @param string $view View path relative to Views directory (e.g., 'Tasks/index')
     * @param array $data Associative array of variables to extract into view scope
     */
    protected function render(string $view, array $data = []): void
    {
        // Auto-include flash messages from session
        $flashMessages = $this->getFlashMessages();
        $data = array_merge($flashMessages, $data);

        // Auto-include common session data
        $data['currentUser'] = $_SESSION['user'] ?? null;
        $data['csrfToken'] = $_SESSION['csrf_token'] ?? '';

        // Extract data into local scope for view
        extract($data);

        // Include the view file
        include BASE_PATH . "/../Views/{$view}.php";
    }

    /**
     * Get flash messages from session and clear them
     *
     * @return array Flash messages (error, success, info)
     */
    protected function getFlashMessages(): array
    {
        $messages = [
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['success'] ?? null,
            'info' => $_SESSION['info'] ?? null,
        ];

        // Clear flash messages after reading
        unset($_SESSION['error'], $_SESSION['success'], $_SESSION['info']);

        return $messages;
    }

    /**
     * Redirect to URL without a flash message
     *
     * @param string $url Target URL
     */
    protected function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect with success message
     *
     * @param string $url Target URL
     * @param string $message Success message to display
     */
    protected function redirectWithSuccess(string $url, string $message): never
    {
        $_SESSION['success'] = $message;
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect with error message
     *
     * @param string $url Target URL
     * @param string $message Error message to display
     */
    protected function redirectWithError(string $url, string $message): never
    {
        $_SESSION['error'] = $message;
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect with info message
     *
     * @param string $url Target URL
     * @param string $message Info message to display
     */
    protected function redirectWithInfo(string $url, string $message): never
    {
        $_SESSION['info'] = $message;
        header("Location: {$url}");
        exit;
    }

    /**
     * Get pagination parameters from request data
     *
     * @param array $data Request data
     * @return array{page: int, limit: int} Pagination parameters
     */
    protected function getPaginationParams(array $data): array
    {
        $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $limit = $this->settingsService->getResultsPerPage();

        return [
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * Get sorting parameters from GET request
     *
     * @param string $defaultField Default sort field
     * @param string $defaultDirection Default sort direction (asc|desc)
     * @param array $allowedFields Whitelist of permitted sort field names; empty means allow any
     * @param string $fieldParam GET parameter name for field (default: 'sort')
     * @param string $directionParam GET parameter name for direction (default: 'dir')
     * @return array{field: string, direction: string} Sorting parameters
     */
    protected function getSortParams(
        string $defaultField = 'created_at',
        string $defaultDirection = 'desc',
        array $allowedFields = [],
        string $fieldParam = 'sort',
        string $directionParam = 'dir'
    ): array {
        $requested = $_GET[$fieldParam] ?? $defaultField;
        $field = (!empty($allowedFields) && !in_array($requested, $allowedFields, true))
            ? $defaultField
            : $requested;

        $direction = isset($_GET[$directionParam]) && strtolower($_GET[$directionParam]) === 'asc'
            ? 'asc'
            : $defaultDirection;

        return [
            'field' => $field,
            'direction' => $direction
        ];
    }

    /**
     * Validate request method
     *
     * @param string $requestMethod Actual request method
     * @param string $expectedMethod Expected request method (GET, POST, etc.)
     * @throws RuntimeException If methods don't match
     */
    protected function validateRequestMethod(string $requestMethod, string $expectedMethod): void
    {
        if (strtoupper($requestMethod) !== strtoupper($expectedMethod)) {
            throw new RuntimeException("Invalid request method. Expected {$expectedMethod}");
        }
    }

    /**
     * Get search query from GET parameters
     *
     * @param string $param Parameter name (default: 'search')
     * @return string Trimmed search query
     */
    protected function getSearchQuery(string $param = 'search'): string
    {
        return isset($_GET[$param]) ? trim($_GET[$param]) : '';
    }

    /**
     * Get filter value from GET parameters as integer
     *
     * @param string $param Parameter name
     * @return int|null Filter value or null
     */
    protected function getFilterInt(string $param): ?int
    {
        return isset($_GET[$param]) && $_GET[$param] !== '' ? (int)$_GET[$param] : null;
    }

    /**
     * Get filter value from GET parameters as string
     *
     * @param string $param Parameter name
     * @return string|null Filter value or null
     */
    protected function getFilterString(string $param): ?string
    {
        return isset($_GET[$param]) && $_GET[$param] !== '' ? trim($_GET[$param]) : null;
    }

    /**
     * Check if request is POST method
     *
     * @param string $requestMethod Request method to check
     * @return bool True if POST
     */
    protected function isPost(string $requestMethod): bool
    {
        return strtoupper($requestMethod) === 'POST';
    }

    /**
     * Check if request is GET method
     *
     * @param string $requestMethod Request method to check
     * @return bool True if GET
     */
    protected function isGet(string $requestMethod): bool
    {
        return strtoupper($requestMethod) === 'GET';
    }

    /**
     * Get current user ID from session
     *
     * @return int|null User ID or null if not logged in
     */
    protected function getCurrentUserId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    /**
     * Get current user data from session
     *
     * @return array|null User data array or null
     */
    protected function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Handle exception with safe error message and redirect
     *
     * @param \Exception $e Exception to handle
     * @param string $redirectUrl URL to redirect to
     * @param string|null $fallbackMessage Optional fallback error message
     */
    protected function handleException(\Exception $e, string $redirectUrl, ?string $fallbackMessage = null): never
    {
        // Log the actual error
        error_log(sprintf(
            "Controller Error: %s in %s:%d",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        // Use fallback or safe error message
        $message = $fallbackMessage ?? 'An error occurred. Please try again.';

        $this->redirectWithError($redirectUrl, $message);
    }

    /**
     * Build filters array from GET parameters
     *
     * @param array $filterMap Map of parameter names to database columns
     * @return array Filters for model queries
     */
    protected function buildFilters(array $filterMap): array
    {
        $filters = [];

        foreach ($filterMap as $param => $column) {
            $value = $this->getFilterInt($param) ?? $this->getFilterString($param);
            if ($value !== null) {
                $filters[$column] = $value;
            }
        }

        return $filters;
    }
}
