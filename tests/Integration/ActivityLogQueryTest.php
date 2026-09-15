<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\ActivityController;
use App\Controllers\BaseController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\TestCase;

/**
 * Integration test for the activity log's count query.
 *
 * getTotalActivities() named :search twice in one statement when the enhanced
 * columns are present — and they are present in the canonical schema, so this
 * was the branch that ran. A native prepare rejects that, the method catches
 * \Throwable and returns 0, so searching the activity log reported no results
 * while the listing query beside it (which already used :search_path and
 * :search_name) returned rows. Wrong count, no error.
 *
 * The controller is built without its constructor on purpose: the real one
 * calls AuthMiddleware::authenticate() and exits on denial, which cannot run
 * inside a test process. The method under test needs only the database handle,
 * so injecting that is enough to exercise the genuine statement.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(ActivityController::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class ActivityLogQueryTest extends TestCase
{
    private const MARKER = 'aureo-activity-probe';

    /** @var list<int> */
    private array $logIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $rows = [
            ['/' . self::MARKER . '/alpha', 'alpha description'],
            ['/' . self::MARKER . '/beta', 'beta description'],
            ['/unrelated/path', self::MARKER . ' in the description only'],
        ];

        foreach ($rows as [$path, $description]) {
            $this->db->executeInsertUpdate(
                'INSERT INTO activity_logs (session_id, event_type, method, path, ip_address, description)
                 VALUES (:session, :event, :method, :path, :ip, :description)',
                [
                    ':session' => 'probe-' . uniqid(),
                    ':event' => 'page_view',
                    ':method' => 'GET',
                    ':path' => $path,
                    ':ip' => '127.0.0.1',
                    ':description' => $description,
                ]
            );
            $this->logIds[] = (int) $this->db->getConnection()->lastInsertId();
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->logIds as $id) {
            $this->db?->executeQuery('DELETE FROM activity_logs WHERE id = :id', [':id' => $id]);
        }
        $this->logIds = [];

        parent::tearDown();
    }

    private function controller(): ActivityController
    {
        $controller = new ReflectionClass(ActivityController::class)->newInstanceWithoutConstructor();

        // setAccessible() is unnecessary since PHP 8.1 and deprecated in 8.5.
        new ReflectionProperty(ActivityController::class, 'db')
            ->setValue($controller, Database::getInstance());

        // The logger has to be real too: getTotalActivities() swallows failures
        // through logException(), so without it a broken statement surfaces as
        // "typed property not initialized" instead of the silent 0 that
        // production actually returns — and the test would then be proving the
        // wrong failure.
        new ReflectionProperty(BaseController::class, 'logger')
            ->setValue($controller, new LoggerService());

        return $controller;
    }

    private function totalActivities(array $filters): int
    {
        return (int) new ReflectionMethod(ActivityController::class, 'getTotalActivities')
            ->invoke($this->controller(), $filters);
    }

    /**
     * Zero is exactly what the broken statement returned, so a non-zero count
     * is the assertion that separates a working query from a swallowed error.
     */
    public function testSearchFilterCountsMatchingRows(): void
    {
        $total = $this->totalActivities(['search' => self::MARKER]);

        $this->assertSame(3, $total, 'All three seeded rows match on path or description.');
    }

    /**
     * The two halves of the OR bind the same value to different names. If one
     * name were dropped, a term that only appears in one column would still
     * look like it worked — this asserts each half independently.
     */
    public function testSearchMatchesDescriptionAsWellAsPath(): void
    {
        $this->assertSame(
            1,
            $this->totalActivities(['search' => 'in the description only']),
            'A term present only in description must be found.'
        );

        $this->assertSame(
            1,
            $this->totalActivities(['search' => self::MARKER . '/alpha']),
            'A term present only in path must be found.'
        );
    }

    public function testSearchFilterExcludesNonMatchingRows(): void
    {
        $this->assertSame(0, $this->totalActivities(['search' => 'no-row-contains-this-term']));
    }
}
