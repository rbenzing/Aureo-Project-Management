<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\SearchController;
use App\Services\SearchService;
use PHPUnit\Framework\TestCase;

class SearchControllerUrlTest extends TestCase
{
    private function resolve(string $type, int $id): string
    {
        $controller = new SearchController($this->createMock(SearchService::class));
        $ref = new \ReflectionMethod(SearchController::class, 'resolveUrl');
        $ref->setAccessible(true);

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
