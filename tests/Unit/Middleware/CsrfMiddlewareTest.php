<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\CsrfMiddleware;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use Exception;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural tests for CsrfMiddleware.
 *
 * Database, SettingsService and SecurityService singletons are always
 * replaced with mocks (via reflection on their static `instance` property)
 * before the middleware is constructed, so no live MySQL connection or real
 * settings lookup ever happens. They are reset to null in tearDown so they
 * cannot leak into other test files sharing the same PHPUnit process.
 *
 * validatePostRequest() is NOT exercised on its failure path: on a thrown
 * validation Exception it calls header()+exit (line ~227), which would kill
 * the test runner. Its success path (valid token, no exception) is safe and
 * is covered via handleToken(). Private helpers that never exit
 * (isValidTokenFormat, hasValidSessionToken, getSafeRedirectUrl) are invoked
 * directly via ReflectionMethod to reach branches the exiting wrapper would
 * otherwise hide.
 */
#[CoversClass(CsrfMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(LoggerService::class)]
final class CsrfMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $this->seedDatabaseSingleton(null);
        $this->seedSettingsServiceSingleton(null);
        $this->seedSecurityServiceSingleton(null);

        parent::tearDown();
    }

    private function seedDatabaseSingleton(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function seedSettingsServiceSingleton(?SettingsService $service): void
    {
        (new ReflectionClass(SettingsService::class))->getProperty('instance')->setValue(null, $service);
    }

    private function seedSecurityServiceSingleton(?SecurityService $service): void
    {
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, $service);
    }

    private function makeMiddleware(Database $db): CsrfMiddleware
    {
        $this->seedDatabaseSingleton($db);

        return new CsrfMiddleware();
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    private function statementReturning(mixed $fetchValue, mixed $fetchColumnValue = null): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($fetchValue);
        $stmt->method('fetchColumn')->willReturn($fetchColumnValue);

        return $stmt;
    }

    // ---- generateToken() ---------------------------------------------

    public function testGenerateTokenReturnsHexTokenAndPersistsIt(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('getSecuritySetting')->with('csrf_token_lifetime', 3600)->willReturn(1800);
        $this->seedSettingsServiceSingleton($settings);

        $captured = null;
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('executeInsertUpdate')
            ->with($this->stringContains('INSERT INTO csrf_tokens'), $this->callback(function ($params) use (&$captured) {
                $captured = $params;

                return true;
            }))
            ->willReturn(true);

        $token = $this->makeMiddleware($db)->generateToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame($token, $_SESSION['csrf_token']);
        $this->assertSame($token, $captured[':token']);
        $this->assertNull($captured[':user_id']);
        $this->assertNotEmpty($captured[':expires_at']);
    }

    public function testGenerateTokenIncludesSessionUserId(): void
    {
        $_SESSION['user']['id'] = 77;

        $settings = $this->createMock(SettingsService::class);
        $settings->method('getSecuritySetting')->willReturn(3600);
        $this->seedSettingsServiceSingleton($settings);

        $captured = null;
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')
            ->willReturnCallback(function ($sql, $params) use (&$captured) {
                $captured = $params;

                return true;
            });

        $this->makeMiddleware($db)->generateToken();

        $this->assertSame(77, $captured[':user_id']);
    }

    public function testGenerateTokenWrapsPersistenceFailure(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('getSecuritySetting')->willReturn(3600);
        $this->seedSettingsServiceSingleton($settings);

        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new Exception('db down'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to generate security token');

        $this->makeMiddleware($db)->generateToken();
    }

    // ---- validateToken() ----------------------------------------------

    public function testValidateTokenThrowsWhenTokenEmpty(): void
    {
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('CSRF token is missing');

        $middleware->validateToken('');
    }

    public function testValidateTokenThrowsWhenSessionHasNoToken(): void
    {
        unset($_SESSION['csrf_token']);
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('CSRF token is missing');

        $middleware->validateToken(str_repeat('a', 64));
    }

    public function testValidateTokenThrowsOnInvalidFormat(): void
    {
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid token format');

        $middleware->validateToken('not-hex');
    }

    public function testValidateTokenThrowsAndCleansUpWhenNoStoredRowFound(): void
    {
        $requestToken = str_repeat('b', 64);
        $_SESSION['csrf_token'] = $requestToken;

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'SELECT token, session_id')) {
                return $this->statementReturning(false);
            }

            return $this->statementReturning(false);
        });
        $db->expects($this->exactly(2))->method('executeQuery');

        $middleware = $this->makeMiddleware($db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing or expired CSRF token');

        $middleware->validateToken($requestToken);
    }

    public function testValidateTokenThrowsWhenSessionTokenDoesNotMatchRequestToken(): void
    {
        $requestToken = str_repeat('c', 64);
        $_SESSION['csrf_token'] = str_repeat('d', 64);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn(
            $this->statementReturning(['token' => $requestToken, 'session_id' => 'sess-1'])
        );

        $middleware = $this->makeMiddleware($db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        $middleware->validateToken($requestToken);
    }

    public function testValidateTokenReturnsTrueForMatchingStoredToken(): void
    {
        $requestToken = str_repeat('e', 64);
        $_SESSION['csrf_token'] = $requestToken;

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn(
            $this->statementReturning(['token' => $requestToken, 'session_id' => 'sess-1'])
        );

        $middleware = $this->makeMiddleware($db);

        $this->assertTrue($middleware->validateToken($requestToken));
    }

    // ---- cleanupExpiredTokens() -----------------------------------------

    public function testCleanupExpiredTokensDeletesExpiredRows(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('executeQuery')
            ->with($this->stringContains('DELETE FROM csrf_tokens WHERE expires_at < NOW()'))
            ->willReturn($this->statementReturning(false));

        $this->makeMiddleware($db)->cleanupExpiredTokens();
    }

    public function testCleanupExpiredTokensSwallowsDatabaseException(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new Exception('cleanup failed'));

        // No exception should propagate.
        $this->makeMiddleware($db)->cleanupExpiredTokens();
        $this->addToAssertionCount(1);
    }

    // ---- hasValidSessionToken() (private, invoked via reflection) -----

    public function testHasValidSessionTokenFalseWhenSessionTokenMissing(): void
    {
        unset($_SESSION['csrf_token']);
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->assertFalse($this->invokePrivate($middleware, 'hasValidSessionToken'));
    }

    public function testHasValidSessionTokenFalseWhenFormatInvalid(): void
    {
        $_SESSION['csrf_token'] = 'not-hex';
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->assertFalse($this->invokePrivate($middleware, 'hasValidSessionToken'));
    }

    public function testHasValidSessionTokenTrueWhenDbConfirmsLiveRow(): void
    {
        $_SESSION['csrf_token'] = str_repeat('f', 64);
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($this->statementReturning(null, '1'));

        $middleware = $this->makeMiddleware($db);

        $this->assertTrue($this->invokePrivate($middleware, 'hasValidSessionToken'));
    }

    public function testHasValidSessionTokenFalseWhenDbHasNoLiveRow(): void
    {
        $_SESSION['csrf_token'] = str_repeat('f', 64);
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($this->statementReturning(null, false));

        $middleware = $this->makeMiddleware($db);

        $this->assertFalse($this->invokePrivate($middleware, 'hasValidSessionToken'));
    }

    public function testHasValidSessionTokenFalseAndLogsWhenLookupThrows(): void
    {
        $_SESSION['csrf_token'] = str_repeat('f', 64);
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new Exception('lookup failed'));

        $middleware = $this->makeMiddleware($db);

        $this->assertFalse($this->invokePrivate($middleware, 'hasValidSessionToken'));
    }

    // ---- isValidTokenFormat() (private, invoked via reflection) -------

    public function testIsValidTokenFormatAcceptsSixtyFourHexChars(): void
    {
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->assertTrue($this->invokePrivate($middleware, 'isValidTokenFormat', [str_repeat('a', 64)]));
        $this->assertFalse($this->invokePrivate($middleware, 'isValidTokenFormat', ['tooshort']));
        $this->assertFalse($this->invokePrivate($middleware, 'isValidTokenFormat', [str_repeat('Z', 64)]));
    }

    // ---- getSafeRedirectUrl() (private, invoked via reflection) -------

    public function testGetSafeRedirectUrlDefaultsToLoginWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->assertSame('/login', $this->invokePrivate($middleware, 'getSafeRedirectUrl'));
    }

    public function testGetSafeRedirectUrlDelegatesToSecurityServiceWhenRefererPresent(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://example.com/prior-page';

        $security = $this->createMock(SecurityService::class);
        $security->expects($this->once())
            ->method('getSafeRedirectUrl')
            ->with('https://example.com/prior-page', '/login')
            ->willReturn('/prior-page');
        $this->seedSecurityServiceSingleton($security);

        $db = $this->createMock(Database::class);
        $middleware = $this->makeMiddleware($db);

        $this->assertSame('/prior-page', $this->invokePrivate($middleware, 'getSafeRedirectUrl'));

        unset($_SERVER['HTTP_REFERER']);
    }

    // ---- handleToken() --------------------------------------------------

    public function testHandleTokenReturnsEarlyWhenCsrfProtectionDisabled(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->with('csrf_protection_enabled')->willReturn(false);
        $this->seedSettingsServiceSingleton($settings);

        $db = $this->createMock(Database::class);
        $db->expects($this->never())->method('executeQuery');
        $db->expects($this->never())->method('executeInsertUpdate');

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->makeMiddleware($db)->handleToken();
    }

    public function testHandleTokenGeneratesTokenWhenSessionHasNone(): void
    {
        unset($_SESSION['csrf_token']);

        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->with('csrf_protection_enabled')->willReturn(true);
        $settings->method('getSecuritySetting')->willReturn(3600);
        $this->seedSettingsServiceSingleton($settings);

        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Force the random cleanup branch to not fire so only token
        // generation is asserted here.
        $this->forceCleanupRatio(false);

        $this->makeMiddleware($db)->handleToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $_SESSION['csrf_token']);
    }

    public function testHandleTokenSkipsGenerationWhenSessionTokenIsValid(): void
    {
        $existingToken = str_repeat('9', 64);
        $_SESSION['csrf_token'] = $existingToken;

        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->with('csrf_protection_enabled')->willReturn(true);
        $this->seedSettingsServiceSingleton($settings);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($this->statementReturning(null, '1'));
        $db->expects($this->never())->method('executeInsertUpdate');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->forceCleanupRatio(false);

        $this->makeMiddleware($db)->handleToken();

        $this->assertSame($existingToken, $_SESSION['csrf_token']);
    }

    public function testHandleTokenRunsCleanupWhenRandomChanceHits(): void
    {
        $token = str_repeat('9', 64);
        $_SESSION['csrf_token'] = $token;

        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->with('csrf_protection_enabled')->willReturn(true);
        $this->seedSettingsServiceSingleton($settings);

        $calls = [];
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function (string $sql) use (&$calls, $token) {
            $calls[] = $sql;

            if (str_contains($sql, 'DELETE FROM csrf_tokens')) {
                return $this->statementReturning(false);
            }

            return $this->statementReturning(null, '1');
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->forceCleanupRatio(true);

        $this->makeMiddleware($db)->handleToken();

        $this->assertTrue(
            array_reduce($calls, fn ($carry, $sql) => $carry || str_contains($sql, 'DELETE FROM csrf_tokens'), false)
        );
    }

    public function testHandleTokenValidatesMatchingPostToken(): void
    {
        $token = str_repeat('a', 64);
        $_SESSION['csrf_token'] = $token;
        $_POST['csrf_token'] = $token;

        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->willReturn(true);
        $this->seedSettingsServiceSingleton($settings);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn(
            $this->statementReturning(['token' => $token, 'session_id' => 'sess'], '1')
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->forceCleanupRatio(false);

        // No exception / no exit means validation passed.
        $this->makeMiddleware($db)->handleToken();
        $this->addToAssertionCount(1);
    }

    public function testHandleTokenSkipsAjaxValidationWhenAjaxProtectionDisabled(): void
    {
        $token = str_repeat('a', 64);
        $_SESSION['csrf_token'] = $token;
        unset($_POST['csrf_token']);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->willReturnMap([
            ['csrf_protection_enabled', true],
            ['csrf_ajax_protection', false],
        ]);
        $this->seedSettingsServiceSingleton($settings);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($this->statementReturning(null, '1'));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->forceCleanupRatio(false);

        // Would throw/exit if AJAX short-circuit did not take effect.
        $this->makeMiddleware($db)->handleToken();
        $this->addToAssertionCount(1);

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    /**
     * Deterministically seeds the Mersenne Twister PRNG so the very next
     * mt_rand() call inside handleToken()'s cleanup-probability check lands
     * on the requested side of CLEANUP_PROBABILITY (0.01).
     */
    private function forceCleanupRatio(bool $below): void
    {
        for ($seed = 1; $seed < 200000; $seed++) {
            mt_srand($seed);
            $ratio = mt_rand() / mt_getrandmax();
            if (($below && $ratio < 0.01) || (!$below && $ratio >= 0.01)) {
                mt_srand($seed);

                return;
            }
        }

        $this->fail('Could not find a deterministic mt_rand seed for the cleanup ratio test');
    }
}
