<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityService
 *
 * Tests security service functionality including:
 * - URL redirect validation
 * - Input size validation
 * - HTML sanitization
 * - Session domain validation
 * - Security headers generation
 * - Error message safety
 */
#[CoversClass(SecurityService::class)]
final class SecurityServiceTest extends TestCase
{
    private SecurityService $securityService;
    private SettingsService $settingsServiceMock;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->settingsServiceMock = $this->createMock(SettingsService::class);
        $this->databaseMock = $this->createMock(Database::class);

        // Create SecurityService with mocked dependencies
        $this->securityService = new SecurityService(
            $this->settingsServiceMock,
            $this->databaseMock
        );
    }

    /**
     * Test relative URL validation (always allowed)
     */
    public function testRelativeUrlIsValid(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);

        $result = $this->securityService->validateRedirectUrl('/dashboard');
        $this->assertTrue($result);

        $result2 = $this->securityService->validateRedirectUrl('/projects/view/123');
        $this->assertTrue($result2);
    }

    /**
     * Test absolute URL validation when redirects are not validated
     */
    public function testAbsoluteUrlWhenValidationDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(false);

        $result = $this->securityService->validateRedirectUrl('https://external.com');
        $this->assertTrue($result);
    }

    /**
     * Test invalid URL format
     */
    public function testInvalidUrlFormat(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);

        // Invalid URL should return false
        $result = $this->securityService->validateRedirectUrl('not a valid url://');
        $this->assertFalse($result);
    }

    /**
     * Test safe redirect URL with valid URL
     */
    public function testGetSafeRedirectUrlWithValidUrl(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(false);

        $url = '/projects';
        $result = $this->securityService->getSafeRedirectUrl($url);
        $this->assertEquals($url, $result);
    }

    /**
     * Test safe redirect URL falls back to default
     */
    public function testGetSafeRedirectUrlFallback(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);

        // Invalid URL should return fallback
        $result = $this->securityService->getSafeRedirectUrl('invalid://url', '/home');
        $this->assertEquals('/home', $result);
    }

    /**
     * Test input size validation with small input
     */
    public function testValidateInputSizeWithSmallInput(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('max_input_size', 1048576)
            ->willReturn(1048576); // 1MB default

        $smallInput = 'This is a small input';
        $result = $this->securityService->validateInputSize($smallInput);
        $this->assertTrue($result);
    }

    /**
     * Test input size validation with large input
     */
    public function testValidateInputSizeWithLargeInput(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('max_input_size', 1048576)
            ->willReturn(100); // Set max to 100 bytes

        $largeInput = str_repeat('a', 200); // 200 bytes
        $result = $this->securityService->validateInputSize($largeInput);
        $this->assertFalse($result);
    }

    /**
     * Test HTML sanitization when enabled
     */
    public function testSanitizeHtmlWhenEnabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(true);

        $input = '<script>alert("XSS")</script><p>Safe content</p>';
        $result = $this->securityService->sanitizeHtml($input);

        // All HTML should be escaped
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * Test HTML sanitization when disabled
     */
    public function testSanitizeHtmlWhenDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(false);

        $input = '<script>alert("XSS")</script>';
        $result = $this->securityService->sanitizeHtml($input);

        // Content should be unchanged when disabled
        $this->assertEquals($input, $result);
    }

    /**
     * Test rich content sanitization allows safe tags
     */
    public function testSanitizeRichContentAllowsSafeTags(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(true);

        $input = '<p>Paragraph</p><strong>Bold</strong><script>alert("XSS")</script>';
        $result = $this->securityService->sanitizeRichContent($input);

        // Safe tags should remain
        $this->assertStringContainsString('<p>Paragraph</p>', $result);
        $this->assertStringContainsString('<strong>Bold</strong>', $result);

        // Dangerous tags should be stripped
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * Test session domain validation with matching domain
     */
    public function testValidateSessionDomainWithMatchingDomain(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_session_domain')
            ->willReturn(true);

        $result = $this->securityService->validateSessionDomain('example.com');
        $this->assertTrue($result);
    }

    /**
     * Test session domain validation with subdomain
     */
    public function testValidateSessionDomainWithSubdomain(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_session_domain')
            ->willReturn(true);

        $result = $this->securityService->validateSessionDomain('app.example.com');
        $this->assertTrue($result);
    }

    /**
     * Test session domain validation with different domain
     */
    public function testValidateSessionDomainWithDifferentDomain(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_session_domain')
            ->willReturn(true);

        $result = $this->securityService->validateSessionDomain('malicious.com');
        $this->assertFalse($result);
    }

    /**
     * Test security headers generation with CSP enabled
     */
    public function testGetSecurityHeadersWithCspEnabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->willReturnMap([
                ['enable_csp', true],
                ['additional_headers', false],
            ]);

        $this->settingsServiceMock
            ->method('getContentSecurityPolicy')
            ->willReturn("default-src 'self'");

        $headers = $this->securityService->getSecurityHeaders();

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertEquals("default-src 'self'", $headers['Content-Security-Policy']);
    }

    /**
     * Test security headers generation with additional headers
     */
    public function testGetSecurityHeadersWithAdditionalHeaders(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->willReturnMap([
                ['enable_csp', false],
                ['additional_headers', true],
            ]);

        $headers = $this->securityService->getSecurityHeaders();

        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);

        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertEquals('DENY', $headers['X-Frame-Options']);

        $this->assertArrayHasKey('X-XSS-Protection', $headers);
        $this->assertEquals('1; mode=block', $headers['X-XSS-Protection']);

        // HSTS should be included when HTTPS is on
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertStringContainsString('max-age=31536000', $headers['Strict-Transport-Security']);
    }

    /**
     * Test session configuration returns correct settings
     */
    public function testGetSessionConfig(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('session_samesite', 'Lax')
            ->willReturn('Strict');

        $config = $this->securityService->getSessionConfig();

        $this->assertArrayHasKey('cookie_httponly', $config);
        $this->assertTrue($config['cookie_httponly']);

        $this->assertArrayHasKey('use_only_cookies', $config);
        $this->assertTrue($config['use_only_cookies']);

        $this->assertArrayHasKey('cookie_samesite', $config);
        $this->assertEquals('Strict', $config['cookie_samesite']);
    }

    /**
     * Test error details should be hidden when enabled
     */
    public function testShouldHideErrorDetailsWhenEnabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('hide_error_details')
            ->willReturn(true);

        $result = $this->securityService->shouldHideErrorDetails();
        $this->assertTrue($result);
    }

    /**
     * Test error details should not be hidden when disabled
     */
    public function testShouldHideErrorDetailsWhenDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('hide_error_details')
            ->willReturn(false);

        $result = $this->securityService->shouldHideErrorDetails();
        $this->assertFalse($result);
    }

    /**
     * Test safe error message when hiding is enabled
     */
    public function testGetSafeErrorMessageWhenHidingEnabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('hide_error_details')
            ->willReturn(true);

        $originalMessage = 'Database connection failed: Access denied for user';
        $result = $this->securityService->getSafeErrorMessage($originalMessage);

        // Should return fallback message, not original
        $this->assertNotEquals($originalMessage, $result);
        $this->assertEquals('An error occurred. Please try again later.', $result);
    }

    /**
     * Test safe error message when hiding is disabled
     */
    public function testGetSafeErrorMessageWhenHidingDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('hide_error_details')
            ->willReturn(false);

        $originalMessage = 'Database connection failed';
        $result = $this->securityService->getSafeErrorMessage($originalMessage);

        // Should return original message when hiding is disabled
        $this->assertEquals($originalMessage, $result);
    }

    /**
     * Test safe error message with custom fallback
     */
    public function testGetSafeErrorMessageWithCustomFallback(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('hide_error_details')
            ->willReturn(true);

        $customFallback = 'Custom error message';
        $result = $this->securityService->getSafeErrorMessage('Original error', $customFallback);

        $this->assertEquals($customFallback, $result);
    }

    protected function tearDown(): void
    {
        // Clean up server variables
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);

        parent::tearDown();
    }
}
