<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Base test case for controller tests
 * Provides utilities for testing HTTP requests and responses
 */
abstract class ControllerTestCase extends DatabaseTestCase
{
    /**
     * Simulate a GET request
     */
    protected function get(string $uri, array $params = []): ControllerResponse
    {
        return $this->makeRequest('GET', $uri, $params);
    }

    /**
     * Simulate a POST request
     */
    protected function post(string $uri, array $data = []): ControllerResponse
    {
        return $this->makeRequest('POST', $uri, $data);
    }

    /**
     * Make HTTP request to controller
     */
    protected function makeRequest(string $method, string $uri, array $data = []): ControllerResponse
    {
        // Capture output
        ob_start();

        // Store original REQUEST_URI
        $originalUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;

        // Parse route and get controller/action
        $segments = explode('/', ltrim($uri, '/'));

        // This is a simplified version - actual implementation would use Router
        // For now, we'll test controllers directly

        $output = ob_get_clean();

        // Restore REQUEST_URI
        if ($originalUri !== null) {
            $_SERVER['REQUEST_URI'] = $originalUri;
        }

        return new ControllerResponse($output, http_response_code());
    }

    /**
     * Assert response is a redirect
     */
    protected function assertRedirect(ControllerResponse $response, ?string $expectedUrl = null): void
    {
        $this->assertTrue(
            $response->isRedirect(),
            'Response is not a redirect'
        );

        if ($expectedUrl !== null) {
            $this->assertEquals(
                $expectedUrl,
                $response->getRedirectUrl(),
                "Redirect URL does not match expected"
            );
        }
    }

    /**
     * Assert session has success message
     */
    protected function assertSessionHasSuccess(?string $message = null): void
    {
        $this->assertArrayHasKey('success', $_SESSION, 'Session does not have success message');

        if ($message !== null) {
            $this->assertStringContainsString($message, $_SESSION['success']);
        }
    }

    /**
     * Assert session has error message
     */
    protected function assertSessionHasError(?string $message = null): void
    {
        $this->assertArrayHasKey('error', $_SESSION, 'Session does not have error message');

        if ($message !== null) {
            $this->assertStringContainsString($message, $_SESSION['error']);
        }
    }
}

/**
 * Simple response wrapper for controller tests
 */
class ControllerResponse
{
    private string $content;
    private int $statusCode;
    private array $headers = [];

    public function __construct(string $content, int $statusCode)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;

        // Capture headers if available
        if (function_exists('headers_list')) {
            $this->headers = headers_list();
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isRedirect(): bool
    {
        foreach ($this->headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                return true;
            }
        }

        return false;
    }

    public function getRedirectUrl(): ?string
    {
        foreach ($this->headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                return trim(substr($header, 9));
            }
        }

        return null;
    }

    public function assertSee(string $text): self
    {
        if (strpos($this->content, $text) === false) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Failed asserting that response contains '{$text}'"
            );
        }

        return $this;
    }

    public function assertDontSee(string $text): self
    {
        if (strpos($this->content, $text) !== false) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Failed asserting that response does not contain '{$text}'"
            );
        }

        return $this;
    }
}
