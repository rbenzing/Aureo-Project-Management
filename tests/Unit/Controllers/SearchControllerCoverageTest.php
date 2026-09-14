<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\SearchController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\SearchService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Additional coverage for SearchController, complementing (not duplicating)
 * tests/Unit/SearchControllerUrlTest.php -- that file already covers
 * resolveUrl()'s 'company' and 'task' branches; this file covers the
 * remaining branches ('project', 'user', 'sprint', 'milestone', and the
 * default fallthrough for an unrecognised type) plus constructor DI.
 *
 * search(), recordClick() and recentQueries() are NOT exercised here.
 * ApiResponse's methods used to end in a bare `exit;`, which made those three
 * actions uncoverable; the App\Core\Response/ApiResponse refactor (see
 * src/Core/ApiResponse.php, src/Core/HttpResponse.php) changed that -
 * ApiResponse now builds and returns an HttpResponse instead of exiting, so
 * these actions are callable and assertable like any other method. Adding
 * that coverage is out of scope for this task and is left to a later one;
 * this file's scope remains resolveUrl() (private, reached via reflection)
 * and constructor DI.
 */
#[CoversClass(SearchController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Setting::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SettingsService::class)]
final class SearchControllerCoverageTest extends TestCase
{
    private function resolve(string $type, int $id): string
    {
        $controller = new SearchController($this->createMock(SearchService::class));
        $ref = new ReflectionMethod(SearchController::class, 'resolveUrl');

        return $ref->invoke($controller, $type, $id);
    }

    public function testConstructorAcceptsAnInjectedSearchService(): void
    {
        $service = $this->createMock(SearchService::class);
        $controller = new SearchController($service);

        $this->assertInstanceOf(SearchController::class, $controller);
    }

    public function testProjectUrlUsesProjectsPlural(): void
    {
        $this->assertSame('/projects/view/9', $this->resolve('project', 9));
    }

    public function testUserUrlUsesUsersPlural(): void
    {
        $this->assertSame('/users/view/4', $this->resolve('user', 4));
    }

    public function testSprintUrlUsesSprintsPlural(): void
    {
        $this->assertSame('/sprints/view/2', $this->resolve('sprint', 2));
    }

    public function testMilestoneUrlUsesMilestonesPlural(): void
    {
        $this->assertSame('/milestones/view/6', $this->resolve('milestone', 6));
    }

    public function testUnrecognisedEntityTypeFallsBackToNaivePluralization(): void
    {
        $this->assertSame('/widgets/view/1', $this->resolve('widget', 1));
    }
}
