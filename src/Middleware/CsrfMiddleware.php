<?php

// file: Middleware/CsrfMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Services\SecurityService;
use App\Services\SettingsService;
use Exception;

class CsrfMiddleware
{
    private Database $db;
    private const TOKEN_LENGTH = 32;
    private const TOKEN_EXPIRY = '1 hour';
    private const CLEANUP_PROBABILITY = 0.01; // 1% chance to run cleanup

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate and store a new CSRF token
     *
     * @return string Generated token
     * @throws Exception If token generation fails
     */
    public function generateToken(): string
    {
        try {
            $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

            // Get token lifetime from security settings
            $settingsService = SettingsService::getInstance();
            $tokenLifetime = $settingsService->getSecuritySetting('csrf_token_lifetime', 3600);
            $expiresAt = date('Y-m-d H:i:s', time() + $tokenLifetime);

            // Get user ID from session if available
            $userId = $_SESSION['user']['id'] ?? null;

            $this->db->executeInsertUpdate(
                "INSERT INTO csrf_tokens (token, session_id, user_id, expires_at)
                    VALUES (:token, :session_id, :user_id, :expires_at)",
                [
                    ':token' => $token,
                    ':session_id' => session_id(),
                    ':user_id' => $userId,
                    ':expires_at' => $expiresAt,
                ]
            );
            // set token session value
            $_SESSION['csrf_token'] = $token;

            return $token;
        } catch (Exception $e) {
            error_log("CSRF token generation failed: " . $e->getMessage());

            throw new Exception('Failed to generate security token');
        }
    }

    /**
     * Validate a CSRF token
     *
     * @param string $requestToken Token to validate
     * @return bool True if token is valid
     * @throws Exception If validation fails
     */
    public function validateToken(string $requestToken): bool
    {
        if (empty($requestToken) || !isset($_SESSION['csrf_token'])) {
            throw new Exception('CSRF token is missing');
        }

        if (!$this->isValidTokenFormat($requestToken)) {
            throw new Exception('Invalid token format');
        }

        $stmt = $this->db->executeQuery(
            "SELECT token, session_id FROM csrf_tokens 
             WHERE token = :token AND expires_at > NOW() 
             AND session_id = :session_id",
            [
                ':token' => $requestToken,
                ':session_id' => session_id(),
            ]
        );
        $storedToken = $stmt->fetch();

        if (empty($storedToken)) {
            $this->cleanupExpiredTokens();

            throw new Exception('Missing or expired CSRF token');
        }

        if (!hash_equals($_SESSION['csrf_token'], $requestToken)) {
            throw new Exception('Invalid CSRF token');
        }

        return true;
    }

    /**
     * Handle CSRF protection for requests
     *
     * @throws Exception If CSRF validation fails
     */
    public function handleToken(): void
    {
        // Check if CSRF protection is enabled
        $settingsService = SettingsService::getInstance();
        if (!$settingsService->isSecurityFeatureEnabled('csrf_protection_enabled')) {
            return; // CSRF protection disabled
        }

        // Random cleanup of expired tokens
        if (mt_rand() / mt_getrandmax() < self::CLEANUP_PROBABILITY) {
            $this->cleanupExpiredTokens();
        }

        // Generate a token when the session has none OR when the session token has
        // no live DB row for this session. Checking only `isset()` let a stale
        // session token (whose DB row had expired or been cleaned) be rendered into
        // the form — the next POST then failed validation with "Missing or expired
        // CSRF token", which surfaced as a bogus "session expired" on first login and
        // only worked on the retry. Regenerating on DB-divergence closes that window.
        if (!$this->hasValidSessionToken()) {
            $this->generateToken();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validatePostRequest();
        }
    }

    /**
     * Whether $_SESSION['csrf_token'] is present AND backed by a live (unexpired)
     * row for the current session. Used to decide if a fresh token is needed.
     */
    private function hasValidSessionToken(): bool
    {
        if (empty($_SESSION['csrf_token']) || !$this->isValidTokenFormat($_SESSION['csrf_token'])) {
            return false;
        }

        try {
            $stmt = $this->db->executeQuery(
                "SELECT 1 FROM csrf_tokens
                 WHERE token = :token AND session_id = :session_id AND expires_at > NOW()",
                [
                    ':token' => $_SESSION['csrf_token'],
                    ':session_id' => session_id(),
                ]
            );

            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            // On a lookup error, force regeneration rather than render a token we
            // can't confirm — a fresh token is always safe to issue.
            error_log("CSRF session-token check failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Remove expired tokens from the database
     */
    public function cleanupExpiredTokens(): void
    {
        try {
            // Only delete rows that are actually expired. Previously this also did
            // unset($_SESSION['csrf_token']) unconditionally, blanking a still-valid
            // token mid-request (e.g. when the 1% cleanup fired on a form GET) and
            // breaking the very next POST. The session token is now managed by
            // hasValidSessionToken()/generateToken(), so leave it untouched here.
            $this->db->executeQuery(
                "DELETE FROM csrf_tokens WHERE expires_at < NOW()"
            );
        } catch (Exception $e) {
            error_log("Failed to cleanup expired CSRF tokens: " . $e->getMessage());
        }
    }

    /**
     * Validate POST request CSRF token
     *
     * @throws Exception If validation fails
     */
    private function validatePostRequest(): void
    {
        try {
            // Check if AJAX CSRF protection is enabled
            $settingsService = SettingsService::getInstance();
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax && !$settingsService->isSecurityFeatureEnabled('csrf_ajax_protection')) {
                return; // AJAX CSRF protection disabled
            }

            // Get token from POST data or AJAX headers
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

            if (!$token) {
                throw new Exception('CSRF token is missing from the request');
            }

            $this->validateToken($token);
        } catch (Exception $e) {
            // Log security event
            $securityService = SecurityService::getInstance();
            $securityService->logSecurityEvent('csrf_validation_failed', [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'],
                'is_ajax' => $isAjax ?? false,
                'error' => $e->getMessage(),
            ]);

            error_log("CSRF validation failed: " . $e->getMessage());
            $_SESSION['error'] = 'Security validation failed. Please try again.';

            header('Location: ' . $this->getSafeRedirectUrl());
            exit;
        }
    }

    /**
     * Validate token format
     */
    private function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{' . (self::TOKEN_LENGTH * 2) . '}$/', $token);
    }

    /**
     * Get safe redirect URL after validation failure
     */
    private function getSafeRedirectUrl(): string
    {
        $securityService = SecurityService::getInstance();
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        // Use security service to validate redirect URL
        if (!empty($referrer)) {
            return $securityService->getSafeRedirectUrl($referrer, '/login');
        }

        return '/login';
    }
}
