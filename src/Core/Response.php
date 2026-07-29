<?php

//file: Core/Response.php
declare(strict_types=1);

namespace App\Core;

/**
 * Response Class
 *
 * Handles HTTP responses, particularly JSON responses for API endpoints
 */
class Response
{
    /**
     * Send JSON response
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        // Set HTTP status code
        http_response_code($statusCode);

        // Set JSON content type header
        header('Content-Type: application/json');

        // Prevent caching for API responses
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        // Output JSON response
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Exit to prevent further output
        exit;
    }

    /**
     * Send success JSON response
     *
     * @param array $data Response data
     * @param string $message Success message
     * @return void
     */
    public static function success(array $data = [], string $message = 'Success'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Send error JSON response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     * @return void
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send redirect response
     *
     * @param string $url Redirect URL
     * @param int $statusCode HTTP status code (301, 302, etc.)
     * @return void
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    /**
     * Send plain text response
     *
     * @param string $text Response text
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function text(string $text, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain');
        echo $text;
        exit;
    }

    /**
     * Send HTML response
     *
     * @param string $html Response HTML
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function html(string $html, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html');
        echo $html;
        exit;
    }
}
