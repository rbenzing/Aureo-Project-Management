<?php

// file: Middleware/ActivityMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use Exception;

class ActivityMiddleware
{
    private $db;
    private array $ignoredPaths = [
        '/assets/',
        '/favicon.ico',
        '/ping',
        '/health',
    ];

    private array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'csrf_token',
        'credit_card',
        'card_number',
        'cvv',
        'secret',
    ];

    /**
     * Initialize the middleware with database connection
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Main middleware handler
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            if (!$this->shouldIgnorePath($_SERVER['REQUEST_URI'])) {
                $this->logActivity();
            }
        } catch (Exception $e) {
            // Log error but don't interrupt the application flow
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }

    /**
     * Get the client's IP address with enhanced security
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $ipSources = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        $validIp = null;

        foreach ($ipSources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $this->sanitizeIpAddress($_SERVER[$source]);

                if ($this->validateIpAddress($ip)) {
                    $validIp = $ip;

                    break;
                }
            }
        }

        return $validIp ?? '127.0.0.1';
    }

    /**
     * Sanitize IP address
     *
     * @param string $ip
     * @return string
     */
    private function sanitizeIpAddress(string $ip): string
    {
        // Handle comma-separated IP lists (common in X-Forwarded-For)
        $ip = trim(explode(',', $ip)[0]);

        // Remove any non-IP characters
        $ip = preg_replace('/[^0-9a-fA-F.:\/]/', '', $ip);

        return $ip;
    }

    /**
     * Validate IP address
     *
     * @param string $ip
     * @return bool
     */
    private function validateIpAddress(string $ip): bool
    {
        // Reject malformed IPs, private ranges, reserved ranges, and loopback.
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Log the current activity
     *
     * @return void
     */
    private function logActivity(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $sessionId = session_id();

        $requestData = $this->collectRequestData();
        $eventType = $this->determineEventType($requestData['path'], $requestData['method']);

        try {
            $this->db->executeQuery(
                "INSERT INTO activity_logs (
                    user_id, 
                    session_id,
                    event_type,
                    method,
                    path,
                    query_string,
                    referer,
                    user_agent,
                    ip_address,
                    request_data,
                    created_at
                ) VALUES (
                    :user_id,
                    :session_id,
                    :event_type,
                    :method,
                    :path,
                    :query_string,
                    :referer,
                    :user_agent,
                    :ip_address,
                    :request_data,
                    NOW()
                )",
                [
                    ':user_id' => $userId,
                    ':session_id' => $sessionId,
                    ':event_type' => $eventType,
                    ':method' => $requestData['method'],
                    ':path' => $requestData['path'],
                    ':query_string' => $requestData['query'],
                    ':referer' => $this->sanitizeUrl($requestData['referer']),
                    ':user_agent' => $this->sanitizeUserAgent($requestData['user_agent']),
                    ':ip_address' => $requestData['ip_address'],
                    ':request_data' => json_encode($requestData['post_data']),
                ]
            );
        } catch (\Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }

    /**
     * Sanitize URL
     *
     * @param string|null $url
     * @return string|null
     */
    private function sanitizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $sanitized = filter_var($url, FILTER_SANITIZE_URL);

        return filter_var($sanitized, FILTER_VALIDATE_URL) ? $sanitized : null;
    }

    /**
     * Sanitize User Agent
     *
     * @param string|null $userAgent
     * @return string
     */
    private function sanitizeUserAgent(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown';
        }

        // Remove any non-printable characters
        return preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent);
    }

    /**
     * Collect all relevant request data
     *
     * @return array
     */
    private function collectRequestData(): array
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'path' => $_SERVER['REQUEST_URI'] ?? '/',
            'query' => $_SERVER['QUERY_STRING'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->getClientIp(),
            'post_data' => $this->sanitizePostData($_POST),
        ];
    }

    /**
     * Check if the path should be ignored
     *
     * @param string $path
     * @return bool
     */
    private function shouldIgnorePath(string $path): bool
    {
        foreach ($this->ignoredPaths as $ignoredPath) {
            if (str_starts_with($path, $ignoredPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize POST data for logging
     *
     * @param array $postData
     * @return array
     */
    private function sanitizePostData(array $postData): array
    {
        $sanitized = [];
        foreach ($postData as $key => $value) {
            // Convert key to string to avoid strtolower() type error with integer keys
            $keyString = (string)$key;
            if (in_array(strtolower($keyString), $this->sensitiveFields, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizePostData($value);
            } else {
                $sanitized[$key] = is_string($value) ?
                    htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') :
                    $value;
            }
        }

        return $sanitized;
    }

    /**
     * Determine the event type based on the request
     *
     * @param string $path
     * @param string $method
     * @return string
     */
    private function determineEventType(string $path, string $method): string
    {
        // Extract the controller and action from the path
        $pathParts = explode('/', trim($path, '/'));
        $controller = $pathParts[0] ?? 'home';
        $action = $pathParts[1] ?? 'index';

        // Handle different HTTP methods
        return match ($method) {
            'GET' => $this->determineGetEventType($action),
            'POST' => $this->determinePostEventType($action),
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($method) . '_request'
        };
    }

    /**
     * Determine event type for GET requests
     *
     * @param string $action
     * @return string
     */
    private function determineGetEventType(string $action): string
    {
        return match ($action) {
            'index' => 'list_view',
            'show', 'view', 'details' => 'detail_view',
            'create', 'new', 'edit' => 'form_view',
            'login' => 'login_form_view',
            'logout' => 'logout',
            default => 'page_view'
        };
    }

    /**
     * Determine event type for POST requests
     *
     * @param string $action
     * @return string
     */
    private function determinePostEventType(string $action): string
    {
        return match ($action) {
            'create', 'store' => 'create',
            'update', 'edit' => 'update',
            'delete', 'destroy' => 'delete',
            'login' => 'login_attempt',
            'logout' => 'logout',
            default => 'form_submission'
        };
    }

    /**
     * Add custom paths to ignore
     *
     * @param array $paths
     * @return self
     */
    public function addIgnoredPaths(array $paths): self
    {
        $this->ignoredPaths = array_merge($this->ignoredPaths, $paths);

        return $this;
    }

    /**
     * Add custom sensitive fields
     *
     * @param array $fields
     * @return self
     */
    public function addSensitiveFields(array $fields): self
    {
        $this->sensitiveFields = array_merge($this->sensitiveFields, $fields);

        return $this;
    }
}
