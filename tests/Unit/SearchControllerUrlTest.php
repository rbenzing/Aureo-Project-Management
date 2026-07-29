<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\BaseController;
use App\Controllers\SearchController;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Services\LoggerService;
use App\Services\SearchService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(Database::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SettingsService::class)]
class SearchControllerUrlTest extends TestCase
{
    private function resolve(string $type, int $id): string
    {
        $controller = new SearchController($this->createMock(SearchService::class));
        // setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5.
        $ref = new \ReflectionMethod(SearchController::class, 'resolveUrl');

        return $ref->invoke($controller, $type, $id);
    }

    public function testCompanyUrlUsesCompaniesPlural(): void
    {
        $this->assertSame('/companies/view/3', $this->resolve('company', 3));
    }

    public function testTaskUrlUnchanged(): void
    {
        $this->assertSame('/tasks/view/5', $this->resolve('task', 5));
    }
}
