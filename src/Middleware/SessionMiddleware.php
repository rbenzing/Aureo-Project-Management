<?php

// file: Middleware/SessionMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Services\SecurityService;
use App\Services\SettingsService;

class SessionMiddleware
{
    private static $db;

    private function __construct()
    {
        // Use the singleton instance of Database
        self::$db = Database::getInstance();
    }

    private static function startSecureSession()
    {
        // Get security service for configuration
        $securityService = SecurityService::getInstance();
        $settingsService = SettingsService::getInstance();

        // Apply security configuration
        $sessionConfig = $securityService->getSessionConfig();

        ini_set('session.cookie_httponly', $sessionConfig['cookie_httponly'] ? 1 : 0);
        ini_set('session.use_only_cookies', $sessionConfig['use_only_cookies'] ? 1 : 0);

        if ($sessionConfig['cookie_secure']) {
            ini_set('session.cookie_secure', 1);
        }

        // Get session timeout from settings
        $sessionTimeout = $settingsService->getSessionTimeout();

        // Validate session domain if enabled
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if ($settingsService->isSecurityFeatureEnabled('validate_session_domain')) {
            if (!$securityService->validateSessionDomain($domain)) {
                // Use a safe fallback domain or current host
                $domain = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_HOST) ?: $domain;
            }
        }

        // Set session cookie parameters with security settings
        session_set_cookie_params([
            'lifetime' => $sessionTimeout,
            'path' => '/',
            'domain' => $domain,
            'secure' => $sessionConfig['cookie_secure'],
            'httponly' => $sessionConfig['cookie_httponly'],
            'samesite' => $sessionConfig['cookie_samesite'],
        ]);

        session_start();
    }

    /**
     * Handle session management.
     */
    public static function handle()
    {
        // Start the session
        self::startSecureSession();

        // Ensure the database instance is initialized
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }

        // Get the session ID from the cookie
        $sessionId = session_id();

        // Fetch the session from the database
        $stmt = self::$db->executeQuery(
            "SELECT id, user_id, data, expires_at FROM sessions WHERE id = :id AND expires_at > NOW()",
            [':id' => $sessionId]
        );
        $session = $stmt->fetch(\PDO::FETCH_OBJ);

        if ($session) {
            // Deserialize session data
            $_SESSION = json_decode($session->data, true);

            // Extend session expiration time and update last_accessed_at
            $settingsService = SettingsService::getInstance();
            $sessionTimeout = $settingsService->getSessionTimeout();
            $newExpiresAt = date('Y-m-d H:i:s', time() + $sessionTimeout);
            self::$db->executeQuery(
                "UPDATE sessions SET expires_at = :expires_at, last_accessed_at = NOW() WHERE id = :id",
                [
                    ':expires_at' => $newExpiresAt,
                    ':id' => $sessionId,
                ]
            );
        } else {
            // Clear the session if invalid or expired
            self::destroySession();
        }
    }

    /**
     * Save session data to the database.
     */
    public static function saveSession($userId = null, $data = [])
    {
        // Ensure the database instance is initialized
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }

        $prevSessionId = session_id();

        // Regenerate session ID on login to prevent session fixation
        self::regenerateSessionId();

        // Resolve IP from headers, taking only the first value in X-Forwarded-For.
        // Use REMOTE_ADDR as the trusted fallback.
        $rawIp = $_SERVER['REMOTE_ADDR'] ?? '::1';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $rawIp = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $rawIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        $ipAddress = filter_var($rawIp, FILTER_VALIDATE_IP) ? $rawIp : ($_SERVER['REMOTE_ADDR'] ?? '::1');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionId = session_id();

        // Get session timeout from settings
        $settingsService = SettingsService::getInstance();
        $sessionTimeout = $settingsService->getSessionTimeout();
        $expiresAt = date('Y-m-d H:i:s', time() + $sessionTimeout);

        // Serialize session data
        $serializedData = json_encode($data);

        $_SESSION['user'] = $data;
        $_SESSION['last_activity'] = time();

        // Insert or update the session in the database
        self::$db->executeQuery(
            "INSERT INTO sessions (id, user_id, data, ip_address, user_agent, expires_at, last_accessed_at)
             VALUES (:id, :user_id, :data, :ip_address, :user_agent, :expires_at, NOW())
             ON DUPLICATE KEY UPDATE 
                 user_id = VALUES(user_id), 
                 data = VALUES(data), 
                 ip_address = VALUES(ip_address),
                 user_agent = VALUES(user_agent),
                 expires_at = VALUES(expires_at),
                 last_accessed_at = NOW()",
            [
                ':id' => $sessionId,
                ':user_id' => $userId,
                ':data' => $serializedData,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':expires_at' => $expiresAt,
            ]
        );

        // update CSRF session ID
        self::$db->executeQuery(
            "UPDATE csrf_tokens SET session_id = :id WHERE session_id = :prev_id",
            [
                ':id' => $sessionId,
                ':prev_id' => $prevSessionId,
            ]
        );
    }

    /**
     * Destroy the session.
     */
    public static function destroySession()
    {
        // Ensure the database instance is initialized
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }

        $sessionId = session_id();

        // Delete the session from the csrf_tokens database
        self::$db->executeQuery(
            "DELETE FROM csrf_tokens WHERE session_id = :id",
            [':id' => $sessionId]
        );

        // Delete the session from the session database
        self::$db->executeQuery(
            "DELETE FROM sessions WHERE id = :id",
            [':id' => $sessionId]
        );

        // Destroy the PHP session
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Regenerate the session ID.
     */
    public static function regenerateSessionId()
    {
        // Ensure the database instance is initialized
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }

        $oldSessionId = session_id();
        session_regenerate_id(true);
        $newSessionId = session_id();

        // Update the database session ID
        $success = self::$db->executeQuery(
            "UPDATE sessions SET id = :new_id WHERE id = :old_id",
            [
                ':new_id' => $newSessionId,
                ':old_id' => $oldSessionId,
            ]
        );

        // If the update failed (maybe no session in database yet), create a new session
        if (!$success) {
            $settingsService = SettingsService::getInstance();
            $sessionTimeout = $settingsService->getSessionTimeout();
            self::$db->executeQuery(
                "INSERT INTO sessions (id, data, expires_at) VALUES (:id, '{}', :expires_at)",
                [
                    ':id' => $newSessionId,
                    ':expires_at' => date('Y-m-d H:i:s', time() + $sessionTimeout),
                ]
            );
        }

        // Delete the old session data if still exists
        self::$db->executeQuery(
            "DELETE FROM sessions WHERE id = :id",
            [':id' => $oldSessionId]
        );
    }
}
