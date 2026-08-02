<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\ActivityController;
use App\Controllers\BaseController;
use App\Core\Database;
use App\Models\User;
use App\Services\LoggerService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Testable subclass with a custom constructor that deliberately skips
 * ActivityController::__construct()/BaseController::__construct().
 *
 * ActivityController's real constructor calls
 * `$this->authMiddleware->isAuthenticated()` SYNCHRONOUSLY, on a real,
 * freshly-built AuthMiddleware (ActivityController doesn't accept one via
 * DI) -- before any test code gets a chance to intervene. Unlike every
 * other controller in this codebase, there is no seam between "AuthMiddleware
 * gets constructed" and "AuthMiddleware gets used": both happen in the same
 * statement. Letting that run for real would mean either a real session +
 * User::find() round trip or hitting AuthMiddleware's redirect()-on-failure
 * (header()+exit). So this subclass never calls the parent constructor at
 * all and instead sets ActivityController's private $db/$userModel and
 * BaseController's protected $logger directly via reflection -- the only
 * dependencies index() actually touches (requirePermission() is overridden
 * to a no-op below, and $authMiddleware/$settingsService are simply never
 * read by ActivityController's methods, so leaving them uninitialized is
 * safe).
 */
final class ActivityControllerTestable extends ActivityController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;

    public function __construct(Database $db, User $userModel, LoggerService $logger)
    {
        $activityRef = new ReflectionClass(ActivityController::class);
        $activityRef->getProperty('db')->setValue($this, $db);
        $activityRef->getProperty('userModel')->setValue($this, $userModel);

        (new ReflectionClass(BaseController::class))->getProperty('logger')->setValue($this, $logger);
    }

    protected function requirePermission(string $permission): void
    {
        // no-op in tests
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;

        throw new RuntimeException('halt:error');
    }
}

/**
 * Behavioural tests for ActivityController.
 *
 * All of index()'s SQL access goes through the private getActivities()/
 * getTotalActivities()/getActivityStats()/checkEnhancedColumns() helpers,
 * all of which call $this->db->executeQuery(...)->fetchAll()/fetchColumn()/
 * rowCount(). A single mocked Database + PDOStatement pair drives every
 * branch without a real connection; db/userModel/logger are full mocks, so
 * none of Database/User/LoggerService's own code ever executes -- only
 * BaseController's inherited logException()/getFlashMessages()-free path
 * runs for real (via the un-overridden logException()), hence the single
 * #[UsesClass(BaseController::class)].
 */
#[CoversClass(ActivityController::class)]
#[UsesClass(BaseController::class)]
final class ActivityControllerTest extends TestCase
{
    /** @var Database&\PHPUnit\Framework\MockObject\MockObject */
    private $db;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var LoggerService&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->createMock(Database::class);
        $this->userModel = $this->createMock(User::class);
        $this->logger = $this->createMock(LoggerService::class);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        parent::tearDown();
    }

    private function controller(): ActivityControllerTestable
    {
        return new ActivityControllerTestable($this->db, $this->userModel, $this->logger);
    }

    /**
     * Configures the Database mock so every executeQuery() call succeeds:
     * SHOW COLUMNS queries report the enhanced-columns row present, and
     * every other query returns $rows via fetchAll() and $count via
     * fetchColumn().
     */
    private function mockWorkingDatabase(array $rows = [], int $count = 5): void
    {
        $columnsStmt = $this->createMock(PDOStatement::class);
        $columnsStmt->method('rowCount')->willReturn(1);

        $dataStmt = $this->createMock(PDOStatement::class);
        $dataStmt->method('fetchAll')->with(PDO::FETCH_OBJ)->willReturn($rows);
        $dataStmt->method('fetchColumn')->willReturn($count);

        $this->db->method('executeQuery')->willReturnCallback(
            function (string $sql) use ($columnsStmt, $dataStmt) {
                return str_contains($sql, 'SHOW COLUMNS') ? $columnsStmt : $dataStmt;
            }
        );
    }

    /**
     * Same as mockWorkingDatabase() but SHOW COLUMNS reports the enhanced
     * columns as absent, driving checkEnhancedColumns()'s false branch (and
     * therefore the legacy, non-enhanced query-building paths in
     * getTotalActivities()/getActivityStats()).
     */
    private function mockWorkingDatabaseWithoutEnhancedColumns(array $rows = [], int $count = 0): void
    {
        $columnsStmt = $this->createMock(PDOStatement::class);
        $columnsStmt->method('rowCount')->willReturn(0);

        $dataStmt = $this->createMock(PDOStatement::class);
        $dataStmt->method('fetchAll')->with(PDO::FETCH_OBJ)->willReturn($rows);
        $dataStmt->method('fetchColumn')->willReturn($count);

        $this->db->method('executeQuery')->willReturnCallback(
            function (string $sql) use ($columnsStmt, $dataStmt) {
                return str_contains($sql, 'SHOW COLUMNS') ? $columnsStmt : $dataStmt;
            }
        );
    }

    private function activityRow(): \stdClass
    {
        $row = new \stdClass();
        $row->id = 1;
        $row->event_type = 'login_attempt';
        $row->first_name = 'Ada';
        $row->last_name = 'Lovelace';

        return $row;
    }

    // -------------------------------------------------------------------- index()

    public function testIndexWithNoAuthenticatedUserRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('User not authenticated', $c->redirectMessage);
    }

    public function testIndexRendersActivityListWithStatsAndPagination(): void
    {
        $_SESSION['user'] = ['permissions' => []];
        $this->mockWorkingDatabase([$this->activityRow()], 5);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Activity/index', $c->renderedView);
        $this->assertCount(1, $c->renderedData['activities']);
        $this->assertSame(5, $c->renderedData['stats']['total_activities']);
        $this->assertSame(5, $c->renderedData['stats']['today_activities']);
        $this->assertSame([], $c->renderedData['users']);
        $this->assertSame('Activity Log', $c->renderedData['viewTitle']);
        $this->assertArrayHasKey('eventTypeOptions', $c->renderedData);
        $this->assertArrayHasKey('entityTypeOptions', $c->renderedData);

        $pagination = $c->renderedData['pagination'];
        $this->assertSame(1, $pagination['current_page']);
        $this->assertSame(5, $pagination['total_items']);
        $this->assertFalse($pagination['has_prev']);
    }

    public function testIndexLoadsAllUsersOnlyWhenViewUsersPermissionPresent(): void
    {
        $_SESSION['user'] = ['permissions' => ['view_users']];
        $this->mockWorkingDatabase([], 0);
        $this->userModel->expects($this->once())
            ->method('getAllUsers')
            ->willReturn([(object)['id' => 1]]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertCount(1, $c->renderedData['users']);
    }

    public function testIndexUsesRequestedPageAndClampsLimitBetween10And100(): void
    {
        $_SESSION['user'] = ['permissions' => []];
        $this->mockWorkingDatabase([], 0);

        $c = $this->controller();
        $c->index('GET', ['page' => '2', 'limit' => '5000']);

        $this->assertSame(2, $c->renderedData['pagination']['current_page']);
        $this->assertSame(100, $c->renderedData['pagination']['items_per_page']);
    }

    public function testIndexResetsInvalidDateFilters(): void
    {
        $_SESSION['user'] = ['permissions' => []];
        $this->mockWorkingDatabase([], 0);

        $c = $this->controller();
        $c->index('GET', ['date_from' => 'not-a-date', 'date_to' => '2024-01-01']);

        $this->assertSame('', $c->renderedData['filters']['date_from']);
        $this->assertSame('2024-01-01', $c->renderedData['filters']['date_to']);
    }

    public function testIndexWithFiltersAndNoEnhancedColumnsAppliesLegacySearchClause(): void
    {
        $_SESSION['user'] = ['permissions' => []];
        $this->mockWorkingDatabaseWithoutEnhancedColumns([$this->activityRow()], 2);

        $c = $this->controller();
        $c->index('GET', [
            'event_type' => 'login_attempt',
            'entity_type' => 'project',
            'user_id' => '7',
            'search' => '  ada  ',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
        ]);

        $this->assertSame('Activity/index', $c->renderedView);
        $filters = $c->renderedData['filters'];
        $this->assertSame('login_attempt', $filters['event_type']);
        $this->assertSame('project', $filters['entity_type']);
        $this->assertSame(7, $filters['user_id']);
        $this->assertSame('ada', $filters['search']);
        $this->assertSame(2, $c->renderedData['stats']['total_activities']);
    }

    public function testIndexOnQueryFailureLogsAndRedirectsWithUnderlyingMessage(): void
    {
        $_SESSION['user'] = ['permissions' => []];
        $this->db->method('executeQuery')->willThrowException(new RuntimeException('connection refused'));

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertStringContainsString('Database query failed', $c->redirectMessage);
    }
}
