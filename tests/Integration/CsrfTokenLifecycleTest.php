<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\CsrfMiddleware;
use App\Models\Setting;
use App\Services\SecurityService;
use App\Services\SettingsService;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

/**
 * Integration tests for the CSRF token round trip against a real database.
 *
 * CsrfMiddlewareTest mocks Database and PDOStatement, so `expires_at > NOW()`
 * is never evaluated against a real server clock. That hid a defect which made
 * a fresh install impossible to log into: generateToken() wrote expires_at with
 * PHP's date(), while validateToken() compared it against the database's NOW().
 * Config sets PHP's timezone from the `settings` table, which is empty on a
 * fresh install, so the hardcoded 'America/New_York' fallback applied while the
 * database ran UTC — every token was written already expired and every POST,
 * including login, failed with "Missing or expired CSRF token".
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(CsrfMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class CsrfTokenLifecycleTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->originalTimezone = date_default_timezone_get();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
        $this->db?->executeQuery('DELETE FROM csrf_tokens WHERE session_id = :sid', [':sid' => session_id()]);
        $_SESSION = [];

        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function displayTimezoneProvider(): array
    {
        return [
            // The shipped fallback when the settings table is empty — the exact
            // configuration a fresh install runs in.
            'America/New_York (shipped default)' => ['America/New_York'],
            // Far behind any plausible server clock: if expiry is written in
            // local time and compared against the server's, this is decisive.
            'Pacific/Midway (UTC-11)' => ['Pacific/Midway'],
            'Asia/Tokyo (UTC+9)' => ['Asia/Tokyo'],
            'UTC' => ['UTC'],
        ];
    }

    /**
     * A token must be valid the instant it is issued, whatever timezone the
     * application happens to display dates in. The display timezone is a
     * presentation concern and must not decide authentication outcomes.
     */
    #[DataProvider('displayTimezoneProvider')]
    public function testTokenIssuedUnderAnyDisplayTimezoneValidatesImmediately(string $timezone): void
    {
        date_default_timezone_set($timezone);

        $middleware = new CsrfMiddleware();
        $token = $middleware->generateToken();

        $this->assertTrue(
            $middleware->validateToken($token),
            "A token issued under {$timezone} must validate immediately."
        );
    }

    /**
     * The companion to the test above: the fix must not become "never expires".
     */
    public function testGenuinelyExpiredTokenIsStillRejected(): void
    {
        date_default_timezone_set('UTC');

        $middleware = new CsrfMiddleware();
        $token = $middleware->generateToken();

        // Push this token's expiry into the past using the database's own
        // clock, so the assertion does not depend on PHP's.
        $this->db->executeQuery(
            'UPDATE csrf_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE token = :token',
            [':token' => $token]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing or expired CSRF token');

        $middleware->validateToken($token);
    }

    /**
     * Guards the storage layer directly: whatever clock is used, a freshly
     * issued token must not be stored with an expiry that already passed.
     */
    public function testStoredExpiryIsInTheFutureRelativeToTheDatabaseClock(): void
    {
        date_default_timezone_set('America/New_York');

        $token = (new CsrfMiddleware())->generateToken();

        $row = $this->db->executeQuery(
            'SELECT (expires_at > NOW()) AS still_valid FROM csrf_tokens WHERE token = :token',
            [':token' => $token]
        )->fetch(\PDO::FETCH_OBJ);

        $this->assertNotFalse($row, 'The generated token should have been stored.');
        $this->assertSame(1, (int) $row->still_valid, 'A newly issued token must not be stored already expired.');
    }
}
