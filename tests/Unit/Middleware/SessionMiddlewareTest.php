<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\SessionMiddleware;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for SessionMiddleware.
 *
 * Database, SettingsService and SecurityService singletons are always
 * replaced with mocks before each call, and SessionMiddleware's own private
 * static $db cache is reset in tearDown, so no live MySQL connection ever
 * happens.
 *
 * SessionMiddleware calls the real native session_start()/
 * session_regenerate_id()/session_destroy() functions — they cannot be
 * mocked. Under the CLI SAPI used to run PHPUnit there is no real HTTP
 * response, so only the FIRST native call in a given PHP process that would
 * send a Set-Cookie header (session_start() or session_regenerate_id())
 * actually activates/changes a session; every later such call in the SAME
 * process just emits a "headers already sent"/"no active session" warning
 * and is a no-op. That is purely a CLI/test-process artifact (a real HTTP
 * worker starts one process per request), so every test that needs a real
 * active session runs in its own process via #[RunInSeparateProcess], and
 * the one native call that is genuinely a *second* header-send within that
 * process (e.g. saveSession()'s internal session_regenerate_id() after this
 * test primes an active session) is wrapped in
 * withSuppressedNativeSessionWarnings(), which swallows only that specific,
 * known warning text — anything else still propagates and fails the test.
 *
 * The private constructor is never called anywhere in the class (every
 * public method lazily assigns self::$db itself instead of doing
 * `new self()`), so it is genuinely dead code; it is exercised once via
 * Reflection for completeness and reported as dead code in the final
 * summary.
 */
#[CoversClass(SessionMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(LoggerService::class)]
final class SessionMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, null);
        (new ReflectionClass(SettingsService::class))->getProperty('instance')->setValue(null, null);
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, null);
        (new ReflectionClass(SessionMiddleware::class))->getProperty('db')->setValue(null, null);

        parent::tearDown();
    }

    private function withSuppressedNativeSessionWarnings(callable $fn): mixed
    {
        set_error_handler(function (int $errno, string $errstr): bool {
            return (bool) preg_match(
                '/headers already sent|no active session|uninitialized session|'
                . 'session id cannot be (changed|regenerated)|session cannot be started/i',
                $errstr
            );
        }, E_WARNING);

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    private function seedSingletons(
        Database $db,
        ?SettingsService $settings = null,
        ?SecurityService $security = null,
    ): void {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);

        $settings ??= $this->defaultSettingsMock();
        (new ReflectionClass(SettingsService::class))->getProperty('instance')->setValue(null, $settings);

        $security ??= $this->defaultSecurityMock();
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, $security);
    }

    private function defaultSettingsMock(): SettingsService
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('getSessionTimeout')->willReturn(3600);
        $settings->method('isSecurityFeatureEnabled')->with('validate_session_domain')->willReturn(false);

        return $settings;
    }

    private function defaultSecurityMock(): SecurityService
    {
        $security = $this->createMock(SecurityService::class);
        $security->method('getSessionConfig')->willReturn([
            'cookie_httponly' => true,
            'use_only_cookies' => true,
            // true so startSecureSession()'s `if ($sessionConfig['cookie_secure'])`
            // ini_set() branch is exercised.
            'cookie_secure' => true,
            'cookie_samesite' => 'Lax',
        ]);

        return $security;
    }

    /**
     * @param array $calls
     */
    private function makeRecordingDatabase(array &$calls, ?callable $fetchResolver = null): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$calls, $fetchResolver): PDOStatement {
                $calls[] = ['sql' => $sql, 'params' => $params];

                $stmt = $this->createMock(PDOStatement::class);
                if ($fetchResolver !== null) {
                    $stmt->method('fetch')->willReturn($fetchResolver($sql, $params));
                }

                return $stmt;
            }
        );

        return $db;
    }

    private function sqlList(array $calls): array
    {
        return array_map(static fn (array $call): string => $call['sql'], $calls);
    }

    // ---- private constructor (dead code, exercised via Reflection) ----

    public function testPrivateConstructorAssignsDatabaseSingleton(): void
    {
        $db = $this->createMock(Database::class);
        $this->seedSingletons($db);

        $reflection = new ReflectionClass(SessionMiddleware::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $reflection->getConstructor()->invoke($instance);

        $this->assertSame($db, $reflection->getProperty('db')->getValue());
    }

    // ---- handle() -------------------------------------------------------
    // handle()'s own session_start() is the first (and, in the
    // "not found" tests, only) native session call in its isolated
    // process, so it needs no warning suppression.

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleLoadsExistingSessionAndExtendsExpiry(): void
    {
        $storedData = json_encode(['user' => ['id' => 1, 'permissions' => ['task.view']]]);

        $calls = [];
        $db = $this->makeRecordingDatabase($calls, function (string $sql) use ($storedData) {
            if (str_contains($sql, 'SELECT id, user_id, data, expires_at')) {
                return (object) [
                    'id' => 'sess-123',
                    'user_id' => 1,
                    'data' => $storedData,
                    'expires_at' => '2999-01-01 00:00:00',
                ];
            }

            return false;
        });

        $this->seedSingletons($db);

        SessionMiddleware::handle();

        $this->assertSame(['user' => ['id' => 1, 'permissions' => ['task.view']]], $_SESSION);

        $sqls = $this->sqlList($calls);
        $this->assertCount(2, $sqls);
        $this->assertStringContainsString('SELECT id, user_id, data, expires_at', $sqls[0]);
        $this->assertStringContainsString('UPDATE sessions SET expires_at', $sqls[1]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleValidatesDomainAndFallsBackWhenInvalid(): void
    {
        $_SERVER['HTTP_HOST'] = 'malicious.example.com';

        $settings = $this->createMock(SettingsService::class);
        $settings->method('getSessionTimeout')->willReturn(3600);
        $settings->method('isSecurityFeatureEnabled')->with('validate_session_domain')->willReturn(true);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSessionConfig')->willReturn([
            'cookie_httponly' => true,
            'use_only_cookies' => true,
            'cookie_secure' => false,
            'cookie_samesite' => 'Lax',
        ]);
        $security->expects($this->once())
            ->method('validateSessionDomain')
            ->with('malicious.example.com')
            ->willReturn(false);

        $calls = [];
        $db = $this->makeRecordingDatabase($calls, fn () => false);

        $this->seedSingletons($db, $settings, $security);

        SessionMiddleware::handle();

        $this->assertSame([], $_SESSION);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleDestroysSessionWhenNoRowFound(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls, fn () => false);
        $this->seedSingletons($db);

        SessionMiddleware::handle();

        $sqls = $this->sqlList($calls);
        $this->assertCount(3, $sqls);
        $this->assertStringContainsString('SELECT id, user_id, data, expires_at', $sqls[0]);
        $this->assertStringContainsString('DELETE FROM csrf_tokens', $sqls[1]);
        $this->assertStringContainsString('DELETE FROM sessions', $sqls[2]);
        $this->assertSame([], $_SESSION);
    }

    // ---- destroySession() ------------------------------------------------
    // Primed with one clean session_start() so session_destroy() has a real
    // active session to act on.

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDestroySessionDeletesCsrfAndSessionRowsAndClearsSuperglobal(): void
    {
        session_start();
        $_SESSION['leftover'] = 'value';

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::destroySession());

        $sqls = $this->sqlList($calls);
        $this->assertCount(2, $sqls);
        $this->assertStringContainsString('DELETE FROM csrf_tokens', $sqls[0]);
        $this->assertStringContainsString('DELETE FROM sessions', $sqls[1]);
        $this->assertSame([], $_SESSION);
    }

    // ---- regenerateSessionId() -------------------------------------------

    /**
     * Database::executeQuery() always either returns a real \PDOStatement
     * or throws (see App\Core\Database::executeQuery, which wraps failures
     * in a RuntimeException rather than ever returning a falsy value). That
     * means `$success` in regenerateSessionId() is never falsy, so its
     * `if (!$success) { ...insert a fresh session row... }` fallback
     * (src/Middleware/SessionMiddleware.php lines ~229-239) is dead code —
     * it can never run through the real Database class. This test
     * documents that by asserting only the two calls that always happen.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegenerateSessionIdChangesIdAndUpdatesDatabase(): void
    {
        session_start();
        $oldId = session_id();

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::regenerateSessionId());

        $sqls = $this->sqlList($calls);
        $this->assertCount(2, $sqls);
        $this->assertStringContainsString('UPDATE sessions SET id = :new_id', $sqls[0]);
        $this->assertStringContainsString('DELETE FROM sessions WHERE id = :id', $sqls[1]);
        $this->assertSame($oldId, $calls[0]['params'][':old_id']);
        $this->assertNotSame($oldId, $calls[0]['params'][':new_id']);
        $this->assertSame($oldId, $calls[1]['params'][':id']);
    }

    // ---- saveSession() ----------------------------------------------------
    // Each primed with a clean session_start() first; saveSession()'s own
    // internal session_regenerate_id() is then the second header-send call
    // in the process, hence the suppression wrapper.

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveSessionPersistsDataRegeneratesIdAndUpdatesCsrfSessionId(): void
    {
        session_start();

        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit-Agent';
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $userData = ['id' => 9, 'profile' => ['id' => 9]];

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::saveSession(9, $userData));

        $this->assertSame($userData, $_SESSION['user']);
        $this->assertIsInt($_SESSION['last_activity']);

        $sqls = $this->sqlList($calls);
        $this->assertCount(4, $sqls);
        $this->assertStringContainsString('UPDATE sessions SET id = :new_id', $sqls[0]);
        $this->assertStringContainsString('DELETE FROM sessions WHERE id = :id', $sqls[1]);
        $this->assertStringContainsString('INSERT INTO sessions', $sqls[2]);
        $this->assertStringContainsString('UPDATE csrf_tokens SET session_id', $sqls[3]);

        $insertParams = $calls[2]['params'];
        $this->assertSame(9, $insertParams[':user_id']);
        $this->assertSame('198.51.100.7', $insertParams[':ip_address']);
        $this->assertSame('PHPUnit-Agent', $insertParams[':user_agent']);
        $this->assertSame(json_encode($userData), $insertParams[':data']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveSessionPrefersClientIpHeaderOverForwardedForAndRemoteAddr(): void
    {
        session_start();

        $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.11';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99';

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::saveSession(1, []));

        $this->assertSame('203.0.113.11', $calls[2]['params'][':ip_address']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveSessionUsesFirstForwardedForEntryWhenClientIpAbsent(): void
    {
        session_start();

        $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.55, 10.0.0.1';

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::saveSession(1, []));

        $this->assertSame('203.0.113.55', $calls[2]['params'][':ip_address']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveSessionFallsBackToRemoteAddrWhenForwardedIpInvalid(): void
    {
        session_start();

        $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::saveSession(1, []));

        $this->assertSame('10.0.0.9', $calls[2]['params'][':ip_address']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveSessionDefaultsIpAndUserAgentWhenServerKeysMissing(): void
    {
        session_start();

        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_CLIENT_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_USER_AGENT'],
        );

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $this->seedSingletons($db);

        $this->withSuppressedNativeSessionWarnings(fn () => SessionMiddleware::saveSession(null, []));

        $this->assertSame('::1', $calls[2]['params'][':ip_address']);
        $this->assertSame('', $calls[2]['params'][':user_agent']);
        $this->assertNull($calls[2]['params'][':user_id']);
    }
}
