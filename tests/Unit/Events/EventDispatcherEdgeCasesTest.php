<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Event;
use App\Events\EventDispatcher;
use App\Events\TaskAssigned;
use App\Services\LoggerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Covers EventDispatcher::dispatch() branches not exercised by
 * tests/Unit/EventSystemTest.php: a listener that throws (caught and
 * logged, other listeners still run), a class-string listener without a
 * handle() method, and a class-string listener that does not resolve to a
 * real, callable class.
 */
#[CoversClass(EventDispatcher::class)]
#[UsesClass(Event::class)]
#[UsesClass(TaskAssigned::class)]
#[UsesClass(LoggerService::class)]
class EventDispatcherEdgeCasesTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton for testing. setAccessible() is a no-op since PHP 8.1
        // and deprecated in 8.5.
        $reflection = new ReflectionClass(EventDispatcher::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        $this->dispatcher = EventDispatcher::getInstance();
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(EventDispatcher::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        parent::tearDown();
    }

    public function testDispatchCatchesExceptionFromListenerAndContinuesToNextListener(): void
    {
        $secondRan = false;

        $this->dispatcher->listen(TaskAssigned::class, function () {
            throw new \RuntimeException('listener blew up');
        }, 10);

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$secondRan) {
            $secondRan = true;
        }, 5);

        // Must not propagate the listener's exception.
        $this->dispatcher->dispatch(new TaskAssigned(1, 2, 3));

        $this->assertTrue($secondRan, 'Second listener should still run after the first one throws');
    }

    public function testDispatchWithClassListenerMissingHandleMethodDoesNothing(): void
    {
        $this->dispatcher->listen(TaskAssigned::class, ListenerWithoutHandleMethod::class);

        // Must not throw despite the listener class having no handle() method.
        $this->dispatcher->dispatch(new TaskAssigned(1, 2, 3));

        $this->assertTrue(ListenerWithoutHandleMethod::$constructed);
    }

    public function testDispatchWithNonExistentClassListenerDoesNothing(): void
    {
        $this->dispatcher->listen(TaskAssigned::class, 'App\\Listeners\\ThisClassDoesNotExist');

        // Neither is_callable() nor class_exists() is true, so dispatch is a no-op.
        $this->dispatcher->dispatch(new TaskAssigned(1, 2, 3));

        $this->assertTrue(true); // reached without exception
    }

    public function testDispatchWithNoRegisteredListenersReturnsImmediately(): void
    {
        // No listen() call for TaskAssigned at all — dispatch must hit the
        // early "!isset($this->listeners[$eventClass])" return without error.
        $this->dispatcher->dispatch(new TaskAssigned(1, 2, 3));

        $this->assertFalse($this->dispatcher->hasListeners(TaskAssigned::class));
    }

    public function testForgetRemovesAllListenersForAnEvent(): void
    {
        $this->dispatcher->listen(TaskAssigned::class, function () {
        });

        $this->assertTrue($this->dispatcher->hasListeners(TaskAssigned::class));

        $this->dispatcher->forget(TaskAssigned::class);

        $this->assertFalse($this->dispatcher->hasListeners(TaskAssigned::class));
        $this->assertSame([], $this->dispatcher->getListeners(TaskAssigned::class));
    }

    public function testGetListenersReturnsEmptyArrayWhenNoneRegistered(): void
    {
        $this->assertSame([], $this->dispatcher->getListeners(TaskAssigned::class));
    }

    public function testGetListenersReturnsRegisteredListenersWithPriority(): void
    {
        $this->dispatcher->listen(TaskAssigned::class, function () {
        }, 7);

        $listeners = $this->dispatcher->getListeners(TaskAssigned::class);

        $this->assertCount(1, $listeners);
        $this->assertSame(7, $listeners[0]['priority']);
    }

    public function testHasListenersReflectsRegistrationState(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(TaskAssigned::class));

        $this->dispatcher->listen(TaskAssigned::class, function () {
        });

        $this->assertTrue($this->dispatcher->hasListeners(TaskAssigned::class));
    }
}

/**
 * Test double: a valid, instantiable class with no handle() method, used to
 * exercise EventDispatcher's `method_exists($listenerInstance, 'handle')`
 * false branch.
 */
class ListenerWithoutHandleMethod
{
    public static bool $constructed = false;

    public function __construct()
    {
        self::$constructed = true;
    }
}
