<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Controllers\RouterFixtureController;
use App\Core\Router;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/Support/RouterFixtureController.php';

#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RouterFixtureController::reset();
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        RouterFixtureController::reset();
        $_GET = [];
        $_POST = [];

        parent::tearDown();
    }

    public function testDispatchGetRouteInvokesControllerAction(): void
    {
        $router = new Router();
        $router->get('router-fixture', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('GET', ['router-fixture']);

        $this->assertTrue(RouterFixtureController::$called);
        $this->assertSame('GET', RouterFixtureController::$calledMethod);
    }

    public function testDispatchNormalizesLeadingAndTrailingSlashesInRoute(): void
    {
        $router = new Router();
        $router->get('/router-fixture/', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('GET', ['router-fixture']);

        $this->assertTrue(RouterFixtureController::$called);
    }

    public function testEmptyRouteAndEmptyUriBothDefaultToDashboard(): void
    {
        $router = new Router();
        $router->get('', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('GET', []);

        $this->assertTrue(RouterFixtureController::$called);
    }

    public function testDispatchMapsRouteParametersByPosition(): void
    {
        $router = new Router();
        $router->get('projects/:project_id/tasks/:task_id', [
            'controller' => 'RouterFixture',
            'action' => 'show',
            'params' => ['project_id', 'task_id'],
        ]);

        $router->dispatch('GET', ['projects', '7', 'tasks', '42']);

        $this->assertSame('7', RouterFixtureController::$calledData['project_id']);
        $this->assertSame('42', RouterFixtureController::$calledData['task_id']);
    }

    public function testDispatchConvertsAllNamedPlaceholderPatterns(): void
    {
        $router = new Router();
        $router->get('items/:id/:slug/:any', ['controller' => 'RouterFixture', 'action' => 'index']);

        // Only reachable if convertRouteToRegex() correctly translated every
        // placeholder type (:id, :slug, :any) into its regex equivalent.
        $router->dispatch('GET', ['items', '9', 'my-slug', 'trailing/stuff']);

        $this->assertTrue(RouterFixtureController::$called);
    }

    public function testDispatchMergesPostDataForPostRequests(): void
    {
        $_POST = ['title' => 'New task'];

        $router = new Router();
        $router->post('router-fixture', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('POST', ['router-fixture']);

        $this->assertSame('New task', RouterFixtureController::$calledData['title']);
    }

    public function testDispatchMergesGetDataForGetRequests(): void
    {
        $_GET = ['q' => 'search term'];

        $router = new Router();
        $router->get('router-fixture', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('GET', ['router-fixture']);

        $this->assertSame('search term', RouterFixtureController::$calledData['q']);
    }

    public function testPutAndDeleteHelpersRegisterUnderTheirOwnHttpMethod(): void
    {
        $router = new Router();
        $router->put('router-fixture', ['controller' => 'RouterFixture', 'action' => 'index']);
        $router->delete('router-fixture', ['controller' => 'RouterFixture', 'action' => 'show']);

        $router->dispatch('PUT', ['router-fixture']);
        $this->assertSame('PUT', RouterFixtureController::$calledMethod);

        RouterFixtureController::reset();

        $router->dispatch('DELETE', ['router-fixture']);
        $this->assertSame('DELETE', RouterFixtureController::$calledMethod);
    }

    public function testDispatchThrows404WhenNoRouteMatches(): void
    {
        $router = new Router();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Page not found');
        $this->expectExceptionCode(404);

        $router->dispatch('GET', ['nowhere']);
    }

    public function testDispatchThrows404WhenControllerClassDoesNotExist(): void
    {
        $router = new Router();
        $router->get('missing-controller', ['controller' => 'NoSuchThing12345', 'action' => 'index']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Controller not found');
        $this->expectExceptionCode(404);

        $router->dispatch('GET', ['missing-controller']);
    }

    public function testDispatchThrows404WhenActionMethodDoesNotExist(): void
    {
        $router = new Router();
        $router->get('router-fixture', ['controller' => 'RouterFixture', 'action' => 'noSuchAction']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Action not found');
        $this->expectExceptionCode(404);

        $router->dispatch('GET', ['router-fixture']);
    }

    public function testDispatchDefaultsToIndexActionWhenNoneSpecified(): void
    {
        $router = new Router();
        $router->get('router-fixture', ['controller' => 'RouterFixture']);

        $router->dispatch('GET', ['router-fixture']);

        $this->assertTrue(RouterFixtureController::$called);
    }

    public function testDispatchUsesContainerWhenItHasTheController(): void
    {
        $instance = new RouterFixtureController();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('has')
            ->with('\\App\\Controllers\\RouterFixtureController')
            ->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with('\\App\\Controllers\\RouterFixtureController')
            ->willReturn($instance);

        $router = new Router($container);
        $router->get('router-fixture', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('GET', ['router-fixture']);

        $this->assertTrue(RouterFixtureController::$called);
    }

    public function testDispatchFallsBackToNewInstanceWhenContainerLacksController(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('has')->willReturn(false);
        $container->expects($this->never())->method('get');

        $router = new Router($container);
        $router->get('router-fixture', ['controller' => 'RouterFixture', 'action' => 'index']);

        $router->dispatch('GET', ['router-fixture']);

        $this->assertTrue(RouterFixtureController::$called);
    }
}
