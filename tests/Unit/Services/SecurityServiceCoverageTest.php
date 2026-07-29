<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Supplemental coverage for SecurityService.
 *
 * tests/Unit/SecurityServiceTest.php already covers the "happy path" branches
 * (relative URLs, disabled-feature short circuits, basic sanitize/session/header
 * checks). This class tops up the branches that test leaves untouched: redirect
 * URLs with a host (allow-list matching, same-host fallback, invalid scheme),
 * rich-content attribute stripping/element replacement, the disabled session
 * domain short circuit, CSP/HSTS omission branches, logSecurityEvent,
 * handleError, and the checkRateLimit database-backed flow (including its
 * fail-open exception path). Database is mocked throughout, so no live MySQL
 * connection is ever opened.
 */
#[CoversClass(SecurityService::class)]
final class SecurityServiceCoverageTest extends TestCase
{
    private SecurityService $securityService;
    private SettingsService&MockObject $settingsServiceMock;
    private Database&MockObject $databaseMock;
    private string $errorLogFile;
    private string|false $originalErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsServiceMock = $this->createMock(SettingsService::class);
        $this->databaseMock = $this->createMock(Database::class);

        $this->securityService = new SecurityService(
            $this->settingsServiceMock,
            $this->databaseMock
        );

        $this->errorLogFile = tempnam(sys_get_temp_dir(), 'aureo_seclog_');
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->errorLogFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog === false ? '' : $this->originalErrorLog);

        if (file_exists($this->errorLogFile)) {
            unlink($this->errorLogFile);
        }

        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // validateRedirectUrl — host-bearing branches
    // ------------------------------------------------------------------

    public function testValidateRedirectUrlRejectsUnparsableUrl(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);

        // A negative port makes parse_url() itself return false, exercising the
        // "completely unparsable" branch (distinct from the "parses but has an
        // invalid scheme" branch covered below).
        $result = $this->securityService->validateRedirectUrl('http://example.com:-80/');

        $this->assertFalse($result);
    }

    public function testValidateRedirectUrlRejectsInvalidScheme(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);

        // parse_url() accepts a scheme starting with a digit; SecurityService's
        // own regex then rejects it before the host is ever inspected.
        $result = $this->securityService->validateRedirectUrl('1http://example.com');

        $this->assertFalse($result);
    }

    public function testValidateRedirectUrlAllowsHostInAllowList(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);
        $this->settingsServiceMock
            ->method('getAllowedRedirectDomains')
            ->willReturn(['trusted.example.com', 'partner.example.com']);

        $result = $this->securityService->validateRedirectUrl('https://trusted.example.com/callback');

        $this->assertTrue($result);
    }

    public function testValidateRedirectUrlRejectsHostNotInAllowList(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);
        $this->settingsServiceMock
            ->method('getAllowedRedirectDomains')
            ->willReturn(['trusted.example.com']);

        $result = $this->securityService->validateRedirectUrl('https://malicious.example.com');

        $this->assertFalse($result);
    }

    public function testValidateRedirectUrlWithNoAllowListFallsBackToCurrentHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'app.example.com';

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_redirects')
            ->willReturn(true);
        $this->settingsServiceMock
            ->method('getAllowedRedirectDomains')
            ->willReturn([]);

        $this->assertTrue($this->securityService->validateRedirectUrl('https://app.example.com/page'));
        $this->assertFalse($this->securityService->validateRedirectUrl('https://other.example.com/page'));
    }

    // ------------------------------------------------------------------
    // sanitizeRichContent
    // ------------------------------------------------------------------

    public function testSanitizeRichContentReturnsUnchangedWhenDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(false);

        $input = '<script>alert(1)</script>';

        $this->assertSame($input, $this->securityService->sanitizeRichContent($input));
    }

    public function testSanitizeRichContentReturnsEmptyStringForBlankInput(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(true);

        $this->assertSame('', $this->securityService->sanitizeRichContent("   \n\t"));
    }

    public function testSanitizeRichContentStripsDisallowedAttributes(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(true);

        $result = $this->securityService->sanitizeRichContent('<p onclick="alert(1)" title="x">Hello</p>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('title=', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('<p>', $result);
    }

    public function testSanitizeRichContentReplacesDisallowedElementButKeepsChildren(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('html_sanitization')
            ->willReturn(true);

        $result = $this->securityService->sanitizeRichContent('<div>Keep <em>me</em> please</div>');

        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringContainsString('Keep', $result);
        $this->assertStringContainsString('<em>me</em>', $result);
        $this->assertStringContainsString('please', $result);
    }

    // ------------------------------------------------------------------
    // validateSessionDomain — disabled short circuit
    // ------------------------------------------------------------------

    public function testValidateSessionDomainReturnsTrueWhenValidationDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('validate_session_domain')
            ->willReturn(false);

        $result = $this->securityService->validateSessionDomain('anything-at-all.example');

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // getSecurityHeaders / applySecurityHeaders — remaining branches
    // ------------------------------------------------------------------

    public function testGetSecurityHeadersOmitsCspWhenPolicyIsEmpty(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->willReturnMap([
                ['enable_csp', true],
                ['additional_headers', false],
            ]);
        $this->settingsServiceMock->method('getContentSecurityPolicy')->willReturn('');

        $headers = $this->securityService->getSecurityHeaders();

        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);
        $this->assertSame([], $headers);
    }

    public function testGetSecurityHeadersOmitsHstsWithoutHttps(): void
    {
        unset($_SERVER['HTTPS']);

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->willReturnMap([
                ['enable_csp', false],
                ['additional_headers', true],
            ]);

        $headers = $this->securityService->getSecurityHeaders();

        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);
    }

    public function testApplySecurityHeadersDelegatesToGetSecurityHeaders(): void
    {
        $this->settingsServiceMock
            ->expects($this->exactly(2))
            ->method('isSecurityFeatureEnabled')
            ->willReturnMap([
                ['enable_csp', false],
                ['additional_headers', true],
            ]);

        // applySecurityHeaders() has no return value to assert on; the mock
        // expectation above verifies it actually delegates to getSecurityHeaders()
        // (one isSecurityFeatureEnabled() call per header group) and iterates the
        // result to call header() for each entry without throwing.
        $this->securityService->applySecurityHeaders();
    }

    // ------------------------------------------------------------------
    // logSecurityEvent
    // ------------------------------------------------------------------

    public function testLogSecurityEventWritesEntryWhenEnabled(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit-Agent';

        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('log_security_events')
            ->willReturn(true);

        $this->securityService->logSecurityEvent('test_event', ['foo' => 'bar']);

        $contents = file_get_contents($this->errorLogFile);

        $this->assertStringContainsString('SECURITY_EVENT', $contents);
        $this->assertStringContainsString('test_event', $contents);
        $this->assertStringContainsString('203.0.113.5', $contents);
        $this->assertStringContainsString('"foo":"bar"', $contents);
    }

    public function testLogSecurityEventIsNoopWhenDisabled(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('log_security_events')
            ->willReturn(false);

        $this->securityService->logSecurityEvent('test_event');

        $contents = file_get_contents($this->errorLogFile);

        $this->assertStringNotContainsString('SECURITY_EVENT', $contents);
    }

    // ------------------------------------------------------------------
    // handleError
    // ------------------------------------------------------------------

    public function testHandleErrorReturnsOriginalMessageAndLogsWhenNotHiding(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->willReturnCallback(fn (string $feature): bool => match ($feature) {
                'hide_error_details' => false,
                'log_security_events' => true,
                default => true,
            });

        $result = $this->securityService->handleError(new \Exception('Something broke'), 'TestContext');

        $this->assertSame('Something broke', $result);

        $contents = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Error in TestContext: Something broke', $contents);
        $this->assertStringContainsString('application_error', $contents);
    }

    public function testHandleErrorReturnsFallbackWhenHidingDetails(): void
    {
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->willReturnCallback(fn (string $feature): bool => match ($feature) {
                'hide_error_details' => true,
                'log_security_events' => false,
                default => true,
            });

        $result = $this->securityService->handleError(
            new \Exception('Sensitive internal detail'),
            'Ctx',
            'Custom fallback message'
        );

        $this->assertSame('Custom fallback message', $result);
    }

    // ------------------------------------------------------------------
    // checkRateLimit
    // ------------------------------------------------------------------

    public function testCheckRateLimitReturnsTrueWhenLimitDisabled(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(0);

        $this->databaseMock->expects($this->never())->method('executeQuery');

        $result = $this->securityService->checkRateLimit('198.51.100.1', 'login');

        $this->assertTrue($result);
    }

    public function testCheckRateLimitAllowsRequestWithinLimit(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(5);

        $this->databaseMock->method('executeQuery')
            ->willReturnCallback(fn (string $sql, array $params = []): PDOStatement => $this->statementFetching(['attempts' => 3], $sql));

        $result = $this->securityService->checkRateLimit('198.51.100.1', 'login');

        $this->assertTrue($result);
    }

    public function testCheckRateLimitBlocksAndLogsWhenLimitExceeded(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(5);
        $this->settingsServiceMock
            ->method('isSecurityFeatureEnabled')
            ->with('log_security_events')
            ->willReturn(true);

        $this->databaseMock->method('executeQuery')
            ->willReturnCallback(fn (string $sql, array $params = []): PDOStatement => $this->statementFetching(['attempts' => 6], $sql));

        $result = $this->securityService->checkRateLimit('198.51.100.1', 'login');

        $this->assertFalse($result);

        $contents = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('rate_limit_exceeded', $contents);
    }

    public function testCheckRateLimitDefaultsAttemptsToOneWhenRowMissing(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(5);

        $this->databaseMock->method('executeQuery')
            ->willReturnCallback(fn (string $sql, array $params = []): PDOStatement => $this->statementFetching(false, $sql));

        $result = $this->securityService->checkRateLimit('198.51.100.1', 'login');

        $this->assertTrue($result);
    }

    public function testCheckRateLimitFailsOpenOnDatabaseException(): void
    {
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(5);

        $this->databaseMock->method('executeQuery')
            ->willThrowException(new \RuntimeException('DB unavailable'));

        $result = $this->securityService->checkRateLimit('198.51.100.1', 'login');

        $this->assertTrue($result);

        $contents = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Rate limiting database error', $contents);
    }

    public function testCheckRateLimitDefaultsIdentifierToRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(5);

        $capturedIdentifier = null;
        $this->databaseMock->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$capturedIdentifier): PDOStatement {
                if (isset($params[':identifier'])) {
                    $capturedIdentifier = $params[':identifier'];
                }

                return $this->statementFetching(['attempts' => 1], $sql);
            });

        $result = $this->securityService->checkRateLimit(null, 'login');

        $this->assertTrue($result);
        $this->assertSame('203.0.113.9', $capturedIdentifier);
    }

    public function testCheckRateLimitRepeatedCallsRemainStable(): void
    {
        // cleanupExpiredRateLimits() runs a DELETE roughly 1 time in 10 (rand-gated);
        // looping exercises that opportunistic branch without asserting on it
        // directly, since its trigger is non-deterministic.
        $this->settingsServiceMock
            ->method('getSecuritySetting')
            ->with('rate_limit_attempts', 60)
            ->willReturn(1000);

        $this->databaseMock->method('executeQuery')
            ->willReturnCallback(fn (string $sql, array $params = []): PDOStatement => $this->statementFetching(['attempts' => 1], $sql));

        for ($i = 0; $i < 25; $i++) {
            $this->assertTrue($this->securityService->checkRateLimit('198.51.100.1', 'login'));
        }
    }

    /**
     * Builds a PDOStatement double whose fetch() yields $fetchReturn, used for the
     * SELECT-attempts read-back; other statements (INSERT/DELETE) never call fetch().
     */
    private function statementFetching(array|false $fetchReturn, string $sql): PDOStatement&MockObject
    {
        $stmt = $this->createMock(PDOStatement::class);

        if (str_contains($sql, 'SELECT attempts')) {
            $stmt->method('fetch')->willReturn($fetchReturn);
        }

        return $stmt;
    }
}
