<?php

//file: Services/SecurityService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

/**
 * Security Service
 *
 * Centralized service for managing security features and validations
 */
class SecurityService
{
    private SettingsService $settingsService;
    private Database $db;
    private static ?SecurityService $instance = null;

    /**
     * Constructor - Now supports dependency injection
     *
     * @param SettingsService|null $settingsService Optional SettingsService instance
     * @param Database|null $db Optional Database instance
     */
    public function __construct(?SettingsService $settingsService = null, ?Database $db = null)
    {
        $this->settingsService = $settingsService ?? SettingsService::getInstance();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get singleton instance (for backward compatibility)
     * @return self
     * @deprecated Use dependency injection instead
     */
    public static function getInstance(): SecurityService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Validate redirect URL against security settings
     */
    public function validateRedirectUrl(string $url): bool
    {
        if (!$this->settingsService->isSecurityFeatureEnabled('validate_redirects')) {
            return true; // Validation disabled
        }

        // Parse the URL
        $parsedUrl = parse_url($url);
        if (!$parsedUrl) {
            return false;
        }

        // Allow relative URLs (same domain)
        if (!isset($parsedUrl['host'])) {
            return true;
        }

        // Get allowed domains
        $allowedDomains = $this->settingsService->getAllowedRedirectDomains();

        // If no allowed domains specified, only allow same domain
        if (empty($allowedDomains)) {
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';

            return $parsedUrl['host'] === $currentHost;
        }

        // Check against allowed domains
        return in_array($parsedUrl['host'], $allowedDomains, true);
    }

    /**
     * Get safe redirect URL
     */
    public function getSafeRedirectUrl(string $url, string $fallback = '/dashboard'): string
    {
        if ($this->validateRedirectUrl($url)) {
            return $url;
        }

        return $fallback;
    }

    /**
     * Validate input size against security settings
     */
    public function validateInputSize(string $input): bool
    {
        $maxSize = $this->settingsService->getSecuritySetting('max_input_size', 1048576);

        return strlen($input) <= $maxSize;
    }

    /**
     * Sanitize HTML content based on security settings
     */
    public function sanitizeHtml(string $content): string
    {
        if (!$this->settingsService->isSecurityFeatureEnabled('html_sanitization')) {
            return $content;
        }

        // Basic HTML sanitization
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Enhanced HTML sanitization for rich content.
     *
     * Uses DOMDocument to strip all tags except an explicit allowlist, then removes
     * every attribute that is not on a per-element safe list. This prevents event-handler
     * injection (onclick, onerror, etc.) that strip_tags() alone cannot block.
     */
    public function sanitizeRichContent(string $content): string
    {
        if (!$this->settingsService->isSecurityFeatureEnabled('html_sanitization')) {
            return $content;
        }

        if (trim($content) === '') {
            return '';
        }

        $allowedElements = [
            'p', 'br', 'strong', 'em', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code',
        ];

        // Only these attributes are permitted on the elements that support them.
        $allowedAttributes = [
            // no element in the allowlist needs href/src, so the map is intentionally empty.
        ];

        $doc = new \DOMDocument();
        // Suppress parse warnings for malformed HTML; UTF-8 wrapper keeps encoding intact.
        @$doc->loadHTML(
            '<?xml encoding="utf-8"?><body>' . $content . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $this->sanitizeDomNode($doc->getElementsByTagName('body')->item(0), $allowedElements, $allowedAttributes);

        // Extract only the inner content of <body>.
        $output = '';
        foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return $output;
    }

    /**
     * Recursively strips disallowed elements and attributes from a DOM node.
     *
     * @param \DOMNode          $node
     * @param string[]          $allowedElements   Lower-case tag names to keep.
     * @param array<string,string[]> $allowedAttributes Map of tag => allowed attr names.
     */
    private function sanitizeDomNode(\DOMNode $node, array $allowedElements, array $allowedAttributes): void
    {
        $remove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);

                if (!in_array($tag, $allowedElements, true)) {
                    // Replace disallowed element with its text content so copy is preserved.
                    $remove[] = ['node' => $child, 'replace' => true];

                    continue;
                }

                // Strip every attribute not on the per-element safe list.
                $permittedAttrs = $allowedAttributes[$tag] ?? [];
                $attrsToRemove = [];
                foreach ($child->attributes as $attr) {
                    if (!in_array(strtolower($attr->name), $permittedAttrs, true)) {
                        $attrsToRemove[] = $attr->name;
                    }
                }
                foreach ($attrsToRemove as $attrName) {
                    $child->removeAttribute($attrName);
                }

                $this->sanitizeDomNode($child, $allowedElements, $allowedAttributes);
            }
        }

        foreach ($remove as $entry) {
            $child = $entry['node'];
            if ($entry['replace']) {
                // Move child's own children up before removing it.
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
            }
            $node->removeChild($child);
        }
    }

    /**
     * Validate session domain
     */
    public function validateSessionDomain(string $domain): bool
    {
        if (!$this->settingsService->isSecurityFeatureEnabled('validate_session_domain')) {
            return true;
        }

        $currentHost = $_SERVER['HTTP_HOST'] ?? '';

        // Basic domain validation
        if ($domain === $currentHost) {
            return true;
        }

        // Allow subdomains of current host
        if (str_ends_with($domain, '.' . $currentHost)) {
            return true;
        }

        return false;
    }

    /**
     * Get security headers based on settings
     */
    public function getSecurityHeaders(): array
    {
        $headers = [];

        // Content Security Policy
        if ($this->settingsService->isSecurityFeatureEnabled('enable_csp')) {
            $csp = $this->settingsService->getContentSecurityPolicy();
            if (!empty($csp)) {
                $headers['Content-Security-Policy'] = $csp;
            }
        }

        // Additional security headers
        if ($this->settingsService->isSecurityFeatureEnabled('additional_headers')) {
            $headers['X-Content-Type-Options'] = 'nosniff';
            $headers['X-Frame-Options'] = 'DENY';
            $headers['X-XSS-Protection'] = '1; mode=block';
            $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
            $headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=()';

            // HSTS for HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
            }
        }

        return $headers;
    }

    /**
     * Apply security headers
     */
    public function applySecurityHeaders(): void
    {
        $headers = $this->getSecurityHeaders();

        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        if (!$this->settingsService->isSecurityFeatureEnabled('log_security_events')) {
            return;
        }

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user']['id'] ?? null,
            'context' => $context,
        ];

        error_log('SECURITY_EVENT: ' . json_encode($logData));
    }

    /**
     * Check rate limiting using database persistence
     *
     * @param string|null $identifier Unique identifier (defaults to IP address)
     * @param string $action Action being rate limited (default: 'general')
     * @param int $windowSeconds Time window in seconds (default: 60)
     * @return bool True if within rate limit, false if exceeded
     */
    public function checkRateLimit(string $identifier = null, string $action = 'general', int $windowSeconds = 60): bool
    {
        $maxAttempts = $this->settingsService->getSecuritySetting('rate_limit_attempts', 60);

        if ($maxAttempts <= 0) {
            return true; // Rate limiting disabled
        }

        $identifier = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Opportunistically clean up long-stale rows; not relied on for correctness.
        $this->cleanupExpiredRateLimits();

        try {
            // Single atomic upsert: if the row is fresh (expires_at > NOW()), increment
            // attempts; if it's stale or missing, reset to a new window. Avoids the
            // SELECT/INSERT race that produced duplicate-key errors every request.
            $sql = "INSERT INTO `rate_limits`
                        (identifier, action, attempts, window_start, expires_at)
                    VALUES
                        (:identifier, :action, 1, NOW(), DATE_ADD(NOW(), INTERVAL :window SECOND))
                    ON DUPLICATE KEY UPDATE
                        attempts = IF(expires_at > NOW(), attempts + 1, 1),
                        window_start = IF(expires_at > NOW(), window_start, NOW()),
                        expires_at = IF(expires_at > NOW(), expires_at, DATE_ADD(NOW(), INTERVAL :window2 SECOND)),
                        updated_at = NOW()";
            $this->db->executeQuery($sql, [
                ':identifier' => $identifier,
                ':action' => $action,
                ':window' => $windowSeconds,
                ':window2' => $windowSeconds,
            ]);

            // Read back the post-upsert attempt count to enforce the limit.
            $stmt = $this->db->executeQuery(
                "SELECT attempts FROM `rate_limits`
                 WHERE identifier = :identifier AND action = :action",
                [':identifier' => $identifier, ':action' => $action]
            );
            $row = $stmt->fetch();
            $attempts = $row ? (int) $row['attempts'] : 1;

            if ($attempts > $maxAttempts) {
                $this->logSecurityEvent('rate_limit_exceeded', [
                    'identifier' => $identifier,
                    'action' => $action,
                    'attempts' => $attempts,
                    'limit' => $maxAttempts,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If database fails, log error but don't block the request
            error_log("Rate limiting database error: " . $e->getMessage());

            return true;
        }
    }

    /**
     * Clean up expired rate limit records
     * Runs periodically to prevent table bloat
     */
    private function cleanupExpiredRateLimits(): void
    {
        // Only cleanup 10% of the time to reduce overhead
        if (rand(1, 10) !== 1) {
            return;
        }

        try {
            $query = "DELETE FROM `rate_limits` WHERE expires_at < NOW()";
            $this->db->executeQuery($query);
        } catch (\Exception $e) {
            error_log("Rate limit cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Get session configuration based on security settings
     */
    public function getSessionConfig(): array
    {
        return [
            'cookie_httponly' => true,
            'use_only_cookies' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_samesite' => $this->settingsService->getSecuritySetting('session_samesite', 'Lax'),
        ];
    }

    /**
     * Should hide error details based on security settings
     */
    public function shouldHideErrorDetails(): bool
    {
        return $this->settingsService->isSecurityFeatureEnabled('hide_error_details');
    }

    /**
     * Get safe error message for display to users
     */
    public function getSafeErrorMessage(string $originalMessage, string $fallbackMessage = 'An error occurred. Please try again later.'): string
    {
        if ($this->shouldHideErrorDetails()) {
            return $fallbackMessage;
        }

        return $originalMessage;
    }

    /**
     * Log error and return safe message for display
     */
    public function handleError(\Exception $e, string $context = '', string $fallbackMessage = 'An error occurred. Please try again later.'): string
    {
        // Always log the full error details
        error_log("Error in {$context}: " . $e->getMessage());

        // Log as security event if enabled
        $this->logSecurityEvent('application_error', [
            'context' => $context,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        // Return safe message based on settings
        return $this->getSafeErrorMessage($e->getMessage(), $fallbackMessage);
    }
}
