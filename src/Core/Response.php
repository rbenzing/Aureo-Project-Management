<?php

//file: Core/Response.php
declare(strict_types=1);

namespace App\Core;

/**
 * Response Class
 *
 * Builds HTTP responses, particularly JSON responses for API endpoints
 */
class Response
{
    /**
     * Build a JSON response
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     */
    public static function json(array $data, int $statusCode = 200): HttpResponse
    {
        return HttpResponse::json($data, $statusCode);
    }

    /**
     * Build a success JSON response
     *
     * @param array $data Response data
     * @param string $message Success message
     */
    public static function success(array $data = [], string $message = 'Success'): HttpResponse
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Build an error JSON response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): HttpResponse
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return self::json($response, $statusCode);
    }

    /**
     * Build a redirect response
     *
     * @param string $url Redirect URL
     * @param int $statusCode HTTP status code (301, 302, etc.)
     */
    public static function redirect(string $url, int $statusCode = 302): HttpResponse
    {
        return HttpResponse::redirect($url, $statusCode);
    }

    /**
     * Build a plain text response
     *
     * @param string $text Response text
     * @param int $statusCode HTTP status code
     */
    public static function text(string $text, int $statusCode = 200): HttpResponse
    {
        return HttpResponse::text($text, $statusCode);
    }

    /**
     * Build an HTML response
     *
     * @param string $html Response HTML
     * @param int $statusCode HTTP status code
     */
    public static function html(string $html, int $statusCode = 200): HttpResponse
    {
        return HttpResponse::html($html, $statusCode);
    }
}
