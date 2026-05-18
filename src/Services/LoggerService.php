<?php

//file: Services/LoggerService.php
declare(strict_types=1);

namespace App\Services;

use DateTime;
use Exception;

/**
 * Comprehensive logging service for Aureo application
 */
class LoggerService
{
    private static ?LoggerService $instance = null;
    private string $logDirectory;
    private string $logFile;
    private bool $enabled;

    /**
     * Constructor - Now public to support dependency injection
     *
     * @param string|null $logDirectory Optional custom log directory
     */
    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? dirname(BASE_PATH, 2) . '/log';
        $this->logFile = $this->logDirectory . '/aureo.log';
        $this->enabled = true;

        // Ensure log directory exists
        $this->ensureLogDirectoryExists();

        // Configure PHP error logging
        $this->configurePHPErrorLogging();
    }

    /**
     * Get singleton instance (for backward compatibility)
     * @return self
     * @deprecated Use dependency injection instead
     */
    public static function getInstance(): LoggerService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Ensure log directory exists and is writable
     */
    private function ensureLogDirectoryExists(): void
    {
        if (!is_dir($this->logDirectory)) {
            if (!mkdir($this->logDirectory, 0755, true)) {
                $this->enabled = false;
                error_log("Failed to create log directory: {$this->logDirectory}");

                return;
            }
        }

        if (!is_writable($this->logDirectory)) {
            $this->enabled = false;
            error_log("Log directory is not writable: {$this->logDirectory}");

            return;
        }
    }

    /**
     * Configure PHP to log errors to our custom log file
     */
    private function configurePHPErrorLogging(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Set custom error log location
        ini_set('log_errors', '1');
        ini_set('error_log', $this->logFile);

        // Set error reporting level
        error_reporting(E_ALL);

        // Don't display errors to users (log them instead)
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log an exception with full stack trace
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $message = sprintf(
            "Exception: %s in %s:%d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        $this->log('ERROR', $message, $context);
    }

    /**
     * Log a database query for debugging
     */
    public function query(string $sql, array $params = [], float $executionTime = null): void
    {
        $message = "SQL Query: {$sql}";

        if (!empty($params)) {
            $message .= "\nParameters: " . json_encode($params);
        }

        if ($executionTime !== null) {
            $message .= "\nExecution time: {$executionTime}s";
        }

        $this->log('DEBUG', $message);
    }

    /**
     * Log user activity
     */
    public function activity(string $action, int $userId = null, array $details = []): void
    {
        $context = [
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details,
        ];

        $this->log('INFO', "User Activity: {$action}", $context);
    }

    /**
     * Log security events
     */
    public function security(string $event, array $context = []): void
    {
        $context['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $this->log('WARNING', "Security Event: {$event}", $context);
    }

    /**
     * Core logging method
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $timestamp = (new DateTime())->format('Y-m-d H:i:s');
            $sessionId = session_id() ?: 'no-session';
            $userId = $_SESSION['user']['profile']['id'] ?? 'anonymous';
            $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

            $logEntry = sprintf(
                "[%s] [%s] [Session:%s] [User:%s] [%s %s] %s",
                $timestamp,
                $level,
                $sessionId,
                $userId,
                $requestMethod,
                $requestUri,
                $message
            );

            if (!empty($context)) {
                $logEntry .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT);
            }

            $logEntry .= "\n" . str_repeat('-', 80) . "\n";

            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            // Fallback to PHP's error_log if our logging fails
            error_log("LoggerService failed: " . $e->getMessage());
            error_log("Original message: {$level} - {$message}");
        }
    }

    /**
     * Get the current log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Get recent log entries
     */
    public function getRecentLogs(int $lines = 100): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $content = file_get_contents($this->logFile);
        if ($content === false) {
            return [];
        }

        $logLines = explode("\n", $content);

        return array_slice($logLines, -$lines);
    }

    /**
     * Clear the log file
     */
    public function clearLogs(): bool
    {
        if (file_exists($this->logFile)) {
            return unlink($this->logFile);
        }

        return true;
    }

    /**
     * Check if logging is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
