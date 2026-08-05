<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\BaseController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

#[CoversClass(BaseController::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Setting::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SettingsService::class)]
class BaseControllerTest extends TestCase
{
    private TestableBaseController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableBaseController();
    }

    public function testGetPaginationParams(): void
    {
        // getPaginationParams() reads the page size from SettingsService, which
        // queries the settings table — so this needs a reachable database.
        $this->requireDatabase();

        $params = $this->controller->testGetPaginationParams(['page' => '3']);

        $this->assertArrayHasKey('page', $params);
        $this->assertArrayHasKey('limit', $params);
        $this->assertEquals(3, $params['page']);
    }

    public function testGetPaginationParamsWithInvalidPage(): void
    {
        $this->requireDatabase();

        $params = $this->controller->testGetPaginationParams(['page' => '-1']);

        $this->assertEquals(1, $params['page'], 'Page should default to 1 for invalid values');
    }

    public function testGetSortParams(): void
    {
        $_GET['sort'] = 'name';
        $_GET['dir'] = 'asc';

        $params = $this->controller->testGetSortParams();

        $this->assertEquals('name', $params['field']);
        $this->assertEquals('asc', $params['direction']);

        unset($_GET['sort'], $_GET['dir']);
    }

    public function testGetSortParamsDefaults(): void
    {
        $params = $this->controller->testGetSortParams('id', 'desc');

        $this->assertEquals('id', $params['field']);
        $this->assertEquals('desc', $params['direction']);
    }

    public function testGetSearchQuery(): void
    {
        $_GET['search'] = '  test query  ';

        $query = $this->controller->testGetSearchQuery();

        $this->assertEquals('test query', $query);

        unset($_GET['search']);
    }

    public function testGetFilterInt(): void
    {
        $_GET['status'] = '5';

        $value = $this->controller->testGetFilterInt('status');

        $this->assertSame(5, $value);

        unset($_GET['status']);
    }

    public function testGetFilterIntReturnsNull(): void
    {
        $value = $this->controller->testGetFilterInt('nonexistent');

        $this->assertNull($value);
    }

    public function testIsPost(): void
    {
        $this->assertTrue($this->controller->testIsPost('POST'));
        $this->assertTrue($this->controller->testIsPost('post'));
        $this->assertFalse($this->controller->testIsPost('GET'));
    }

    public function testIsGet(): void
    {
        $this->assertTrue($this->controller->testIsGet('GET'));
        $this->assertTrue($this->controller->testIsGet('get'));
        $this->assertFalse($this->controller->testIsGet('POST'));
    }

    public function testBuildFilters(): void
    {
        $_GET['status_id'] = '1';
        $_GET['project_id'] = '5';

        $filters = $this->controller->testBuildFilters([
            'status_id' => 'status_id',
            'project_id' => 'project_id',
        ]);

        $this->assertEquals(1, $filters['status_id']);
        $this->assertEquals(5, $filters['project_id']);

        unset($_GET['status_id'], $_GET['project_id']);
    }
}

/**
 * Testable version of BaseController that exposes protected methods
 */
class TestableBaseController extends BaseController
{
    public function testGetPaginationParams(array $data): array
    {
        return $this->getPaginationParams($data);
    }

    public function testGetSortParams(
        string $defaultField = 'created_at',
        string $defaultDirection = 'desc'
    ): array {
        return $this->getSortParams($defaultField, $defaultDirection);
    }

    public function testGetSearchQuery(string $param = 'search'): string
    {
        return $this->getSearchQuery($param);
    }

    public function testGetFilterInt(string $param): ?int
    {
        return $this->getFilterInt($param);
    }

    public function testIsPost(string $method): bool
    {
        return $this->isPost($method);
    }

    public function testIsGet(string $method): bool
    {
        return $this->isGet($method);
    }

    public function testBuildFilters(array $filterMap): array
    {
        return $this->buildFilters($filterMap);
    }
}
