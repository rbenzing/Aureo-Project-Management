<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\ActivityMiddleware;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for ActivityMiddleware.
 *
 * Database is always a mock seeded into the singleton via reflection before
 * construction, so no live MySQL connection is opened. ActivityMiddleware
 * itself never calls exit()/die(), so every branch is safely reachable
 * in-process.
 *
 * The outer try/catch in handle() (lines ~51-59) is defensive: the only
 * database call it guards (inside logActivity()) already has its own inner
 * try/catch that swallows \Exception, so the outer catch cannot be reached
 * through the injectable Database mock without violating the executeQuery()
 * return-type contract. It is left uncovered; see the class-level report.
 */
#[CoversClass(ActivityMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
final class ActivityMiddlewareTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_SESSION = [];
        $_POST = [];

        $this->seedDatabaseSingleton(null);

        parent::tearDown();
    }

    private function seedDatabaseSingleton(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    /**
     * Builds a Database mock whose executeQuery() records every call
     * (sql + params) into $calls and returns a harmless PDOStatement mock.
     *
     * @param array $calls
     */
    private function makeRecordingDatabase(array &$calls): Database
    {
        $stmt = $this->createMock(PDOStatement::class);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$calls, $stmt): PDOStatement {
                $calls[] = ['sql' => $sql, 'params' => $params];

                return $stmt;
            });

        return $db;
    }

    private function makeMiddleware(Database $db): ActivityMiddleware
    {
        $this->seedDatabaseSingleton($db);

        return new ActivityMiddleware();
    }

    public function testHandleSkipsIgnoredAssetPath(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $db->expects($this->never())->method('executeQuery');

        $_SERVER['REQUEST_URI'] = '/assets/app.css';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->makeMiddleware($db)->handle();

        $this->assertSame([], $calls);
    }

    public function testHandleSkipsIgnoredApiPath(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $db->expects($this->never())->method('executeQuery');

        $_SERVER['REQUEST_URI'] = '/api/tasks';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->makeMiddleware($db)->handle();
    }

    public function testHandleLogsGetRequestAndTracksRecentView(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/projects/view?id=5';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = 'id=5';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/1.0';
        $_SESSION['user']['id'] = 42;

        $this->makeMiddleware($db)->handle();

        $this->assertCount(1, $calls);
        $this->assertStringContainsString('INSERT INTO activity_logs', $calls[0]['sql']);

        $params = $calls[0]['params'];
        $this->assertSame(42, $params[':user_id']);
        $this->assertSame('GET', $params[':method']);
        $this->assertSame('/projects/view?id=5', $params[':path']);
        $this->assertSame('id=5', $params[':query_string']);
        $this->assertSame('8.8.8.8', $params[':ip_address']);
        // Regression: determineEventType() used to classify off the raw REQUEST_URI,
        // so the action segment became "view?id=5", matched none of the
        // 'show'/'view'/'details' cases, and degraded to the generic 'page_view'.
        // Detail views are exactly the URLs carrying "?id=", so nearly all of them
        // were misclassified in activity_logs. The query string is now stripped
        // before classification, as trackRecentView() already did.
        $this->assertSame('detail_view', $params[':event_type']);

        $this->assertSame(['/projects/view'], $_SESSION['recent_views']);
    }

    public function testHandlePostRequestDoesNotTrackRecentView(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/tasks/create';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->makeMiddleware($db)->handle();

        $this->assertCount(1, $calls);
        $this->assertSame('create', $calls[0]['params'][':event_type']);
        $this->assertArrayNotHasKey('recent_views', $_SESSION);
    }

    public function testHandleSkipsRecentViewTrackingForRootPath(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->makeMiddleware($db)->handle();

        $this->assertArrayNotHasKey('recent_views', $_SESSION);
    }

    public function testTrackRecentViewDedupesAndCapsAtTwenty(): void
    {
        $existing = [];
        for ($i = 1; $i <= 20; $i++) {
            $existing[] = "/page-$i";
        }
        $_SESSION['recent_views'] = $existing;

        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/page-new';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->makeMiddleware($db)->handle();

        $this->assertCount(20, $_SESSION['recent_views']);
        $this->assertSame('/page-new', $_SESSION['recent_views'][0]);
        $this->assertNotContains('/page-20', $_SESSION['recent_views']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function eventTypeProvider(): array
    {
        return [
            'get index' => ['GET', '/projects', 'list_view', 'GET /projects'],
            'get show' => ['GET', '/projects/show/1', 'detail_view', 'GET /projects/show/1'],
            'get create form' => ['GET', '/projects/create', 'form_view', 'GET /projects/create'],
            'get login form' => ['GET', '/auth/login', 'login_form_view', 'GET /auth/login'],
            'get logout' => ['GET', '/auth/logout', 'logout', 'GET /auth/logout'],
            'get unknown action' => ['GET', '/reports/summary', 'page_view', 'GET /reports/summary'],
            'post create' => ['POST', '/projects/create', 'create', 'POST /projects/create'],
            'post update' => ['POST', '/projects/edit', 'update', 'POST /projects/edit'],
            'post delete' => ['POST', '/projects/delete', 'delete', 'POST /projects/delete'],
            'post login' => ['POST', '/auth/login', 'login_attempt', 'POST /auth/login'],
            'post logout' => ['POST', '/auth/logout', 'logout', 'POST /auth/logout'],
            'post unknown action' => ['POST', '/projects/whatever', 'form_submission', 'POST /projects/whatever'],
            'put request' => ['PUT', '/projects/1', 'update', 'PUT /projects/1'],
            'patch request' => ['PATCH', '/projects/1', 'update', 'PATCH /projects/1'],
            'delete request' => ['DELETE', '/projects/1', 'delete', 'DELETE /projects/1'],
            'other method' => ['OPTIONS', '/projects/1', 'options_request', 'OPTIONS /projects/1'],
        ];
    }

    public function testDetermineEventTypeForEachMethodAndAction(): void
    {
        foreach (self::eventTypeProvider() as [$method, $path, $expected]) {
            $calls = [];
            $db = $this->makeRecordingDatabase($calls);

            $_SERVER['REQUEST_URI'] = $path;
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
            unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT']);

            $this->makeMiddleware($db)->handle();

            $this->assertSame($expected, $calls[0]['params'][':event_type'], "method=$method path=$path");

            $this->seedDatabaseSingleton(null);
        }
    }

    public function testCollectRequestDataUsesUnknownDefaultsWhenServerKeysMissing(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['QUERY_STRING'],
            $_SERVER['HTTP_REFERER'],
            $_SERVER['HTTP_USER_AGENT'],
        );
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->makeMiddleware($db)->handle();

        $params = $calls[0]['params'];
        $this->assertSame('UNKNOWN', $params[':method']);
        $this->assertNull($params[':query_string']);
        $this->assertNull($params[':referer']);
        $this->assertSame('Unknown', $params[':user_agent']);
    }

    public function testGetClientIpPrefersFirstValidForwardedForEntry(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        $this->makeMiddleware($db)->handle();

        $this->assertSame('203.0.113.9', $calls[0]['params'][':ip_address']);
    }

    public function testGetClientIpFallsBackToLoopbackWhenNoSourceIsValid(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_CLIENT_IP'] = 'not-an-ip';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->makeMiddleware($db)->handle();

        $this->assertSame('127.0.0.1', $calls[0]['params'][':ip_address']);
    }

    public function testSanitizeUrlAcceptsValidRefererAndRejectsInvalidOne(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_REFERER'] = 'https://example.com/from';

        $this->makeMiddleware($db)->handle();

        $this->assertSame('https://example.com/from', $calls[0]['params'][':referer']);

        $this->seedDatabaseSingleton(null);
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $_SERVER['HTTP_REFERER'] = 'not a valid url';

        $this->makeMiddleware($db)->handle();

        $this->assertNull($calls[0]['params'][':referer']);
    }

    public function testSanitizeUserAgentStripsControlCharacters(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_USER_AGENT'] = "Mozilla\x07Test\x1F";

        $this->makeMiddleware($db)->handle();

        $this->assertSame('MozillaTest', $calls[0]['params'][':user_agent']);
    }

    public function testSanitizePostDataRedactsSensitiveFieldsRecursivelyAndEscapesHtml(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $_SERVER['REQUEST_URI'] = '/login';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $_POST = [
            'Password' => 'super-secret',
            0 => 'zero-indexed-value',
            'billing' => [
                'card_number' => '4111111111111111',
                'note' => '<b>hi</b>',
            ],
            'comment' => ' <script>alert(1)</script> ',
            'attempts' => 3,
        ];

        $this->makeMiddleware($db)->handle();

        $requestData = json_decode($calls[0]['params'][':request_data'], true);

        $this->assertSame('[REDACTED]', $requestData['Password']);
        $this->assertSame('zero-indexed-value', $requestData[0]);
        $this->assertSame('[REDACTED]', $requestData['billing']['card_number']);
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $requestData['billing']['note']);
        $this->assertStringContainsString('&lt;script&gt;', $requestData['comment']);
        // Non-string scalars pass through untouched (int is neither redacted
        // nor html-escaped).
        $this->assertSame(3, $requestData['attempts']);
    }

    /**
     * logActivity() wraps its INSERT in its own try/catch that swallows
     * \Exception and logs via error_log(), so a failing database call never
     * propagates and never reaches handle()'s outer catch. Execution
     * continues to trackRecentView() afterwards.
     */
    public function testHandleSwallowsDatabaseExceptionInsideLogActivity(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new \Exception('insert failed'));

        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->makeMiddleware($db)->handle();

        // trackRecentView() still ran after the swallowed exception.
        $this->assertSame(['/dashboard'], $_SESSION['recent_views']);
    }

    public function testAddIgnoredPathsReturnsSelfAndSkipsCustomPath(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);
        $db->expects($this->never())->method('executeQuery');

        $middleware = $this->makeMiddleware($db);
        $result = $middleware->addIgnoredPaths(['/custom-ignore/']);

        $this->assertSame($middleware, $result);

        $_SERVER['REQUEST_URI'] = '/custom-ignore/thing';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $middleware->handle();
    }

    public function testAddSensitiveFieldsReturnsSelfAndRedactsCustomField(): void
    {
        $calls = [];
        $db = $this->makeRecordingDatabase($calls);

        $middleware = $this->makeMiddleware($db);
        $result = $middleware->addSensitiveFields(['api_key']);

        $this->assertSame($middleware, $result);

        $_SERVER['REQUEST_URI'] = '/settings';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_POST = ['api_key' => 'top-secret'];

        $middleware->handle();

        $requestData = json_decode($calls[0]['params'][':request_data'], true);
        $this->assertSame('[REDACTED]', $requestData['api_key']);
    }
}
