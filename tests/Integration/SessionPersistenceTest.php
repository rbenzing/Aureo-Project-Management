<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\SessionMiddleware;
use App\Models\Setting;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

/**
 * Integration tests for session rows written on login.
 *
 * SessionMiddleware::saveSession() runs on every successful login
 * (AuthController::login) and wrote expires_at with PHP's date(), while
 * handle() selects with `expires_at > NOW()` — the database's clock. This is
 * the same defect class as the CSRF token expiry (see CsrfTokenLifecycleTest):
 * the application's display timezone silently decided whether persisted state
 * was already expired.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(SessionMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class SessionPersistenceTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        if (session_status() !== PHP_SESSION_ACTIVE && !@session_start()) {
            $this->markTestSkipped('No session could be started in this environment.');
        }

        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);

        if (session_id() !== '') {
            $this->db?->executeQuery('DELETE FROM sessions WHERE id = :id', [':id' => session_id()]);
        }

        parent::tearDown();
    }

    /**
     * A session persisted on login must still be live by the database's clock —
     * that is the clock handle() checks it against.
     */
    public function testSessionSavedUnderABehindServerTimezoneIsNotAlreadyExpired(): void
    {
        // The shipped fallback when the settings table is empty, and behind UTC.
        date_default_timezone_set('America/New_York');

        SessionMiddleware::saveSession(null, ['id' => 1, 'email' => 'session@example.test']);

        $row = $this->db->executeQuery(
            'SELECT (expires_at > NOW()) AS still_valid FROM sessions WHERE id = :id',
            [':id' => session_id()]
        )->fetch(\PDO::FETCH_OBJ);

        $this->assertNotFalse($row, 'saveSession() should have persisted a row.');
        $this->assertSame(
            1,
            (int) $row->still_valid,
            'A session saved on login must not be written already expired.'
        );
    }
}
