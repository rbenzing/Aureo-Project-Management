<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\Event;
use App\Events\EventDispatcher;
use App\Events\ProjectCreated;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Services\LoggerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventDispatcher::class)]
#[CoversClass(TaskAssigned::class)]
#[CoversClass(TaskCompleted::class)]
#[CoversClass(ProjectCreated::class)]
#[UsesClass(Event::class)]
#[UsesClass(LoggerService::class)]
class EventSystemTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton for testing. setAccessible() is a no-op since PHP 8.1
        // and deprecated in 8.5.
        $reflection = new \ReflectionClass(EventDispatcher::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        $this->dispatcher = EventDispatcher::getInstance();
    }

    protected function tearDown(): void
    {
        // Clean up singleton
        $reflection = new \ReflectionClass(EventDispatcher::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        parent::tearDown();
    }

    public function testEventDispatcherIsSingleton(): void
    {
        $instance1 = EventDispatcher::getInstance();
        $instance2 = EventDispatcher::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testTaskAssignedEventCreation(): void
    {
        $event = new TaskAssigned(taskId: 1, userId: 2, assignedBy: 3);

        $this->assertEquals(1, $event->getTaskId());
        $this->assertEquals(2, $event->getUserId());
        $this->assertEquals(3, $event->getAssignedBy());
        $this->assertInstanceOf(Event::class, $event);
    }

    public function testEventHasName(): void
    {
        $event = new TaskAssigned(1, 2, 3);

        $this->assertEquals(TaskAssigned::class, $event->getName());
    }

    public function testEventHasTimestamp(): void
    {
        $before = microtime(true);
        $event = new TaskAssigned(1, 2, 3);
        $after = microtime(true);

        $timestamp = $event->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testEventGetPayload(): void
    {
        $event = new TaskAssigned(1, 2, 3);
        $payload = $event->getPayload();

        $this->assertIsArray($payload);
        $this->assertEquals(1, $payload['task_id']);
        $this->assertEquals(2, $payload['user_id']);
        $this->assertEquals(3, $payload['assigned_by']);
    }

    public function testEventGetMethod(): void
    {
        $event = new TaskAssigned(1, 2, 3);

        $this->assertEquals(1, $event->get('task_id'));
        $this->assertEquals(2, $event->get('user_id'));
        $this->assertNull($event->get('nonexistent'));
        $this->assertEquals('default', $event->get('nonexistent', 'default'));
    }

    public function testRegisterClosureListener(): void
    {
        $executed = false;

        $this->dispatcher->listen(TaskAssigned::class, function (TaskAssigned $event) use (&$executed) {
            $executed = true;
        });

        $event = new TaskAssigned(1, 2, 3);
        $this->dispatcher->dispatch($event);

        $this->assertTrue($executed);
    }

    public function testRegisterClassListener(): void
    {
        global $testListenerExecuted;
        $testListenerExecuted = false;

        $this->dispatcher->listen(TaskAssigned::class, TestEventListener::class);

        $event = new TaskAssigned(1, 2, 3);
        $this->dispatcher->dispatch($event);

        $this->assertTrue($testListenerExecuted);
    }

    public function testListenerReceivesEvent(): void
    {
        $receivedEvent = null;

        $this->dispatcher->listen(TaskAssigned::class, function (TaskAssigned $event) use (&$receivedEvent) {
            $receivedEvent = $event;
        });

        $event = new TaskAssigned(1, 2, 3);
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $receivedEvent);
        $this->assertEquals(1, $receivedEvent->getTaskId());
    }

    public function testMultipleListenersExecute(): void
    {
        $executions = [];

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$executions) {
            $executions[] = 'first';
        });

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$executions) {
            $executions[] = 'second';
        });

        $event = new TaskAssigned(1, 2, 3);
        $this->dispatcher->dispatch($event);

        $this->assertCount(2, $executions);
        $this->assertContains('first', $executions);
        $this->assertContains('second', $executions);
    }

    public function testListenerPriorityOrdering(): void
    {
        $executions = [];

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$executions) {
            $executions[] = 'low';
        }, 1);

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$executions) {
            $executions[] = 'high';
        }, 10);

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$executions) {
            $executions[] = 'medium';
        }, 5);

        $event = new TaskAssigned(1, 2, 3);
        $this->dispatcher->dispatch($event);

        $this->assertEquals(['high', 'medium', 'low'], $executions);
    }

    public function testHasListeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(TaskAssigned::class));

        $this->dispatcher->listen(TaskAssigned::class, function () {
        });

        $this->assertTrue($this->dispatcher->hasListeners(TaskAssigned::class));
    }

    public function testGetListeners(): void
    {
        $this->assertEquals([], $this->dispatcher->getListeners(TaskAssigned::class));

        $this->dispatcher->listen(TaskAssigned::class, function () {
        }, 5);

        $listeners = $this->dispatcher->getListeners(TaskAssigned::class);
        $this->assertCount(1, $listeners);
        $this->assertEquals(5, $listeners[0]['priority']);
    }

    public function testForgetListeners(): void
    {
        $this->dispatcher->listen(TaskAssigned::class, function () {
        });

        $this->assertTrue($this->dispatcher->hasListeners(TaskAssigned::class));

        $this->dispatcher->forget(TaskAssigned::class);

        $this->assertFalse($this->dispatcher->hasListeners(TaskAssigned::class));
    }

    public function testDispatchWithNoListeners(): void
    {
        // Should not throw exception
        $event = new TaskAssigned(1, 2, 3);
        $this->dispatcher->dispatch($event);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testTaskCompletedEvent(): void
    {
        $event = new TaskCompleted(taskId: 42, completedBy: 10, timeSpent: 120);

        $this->assertEquals(42, $event->getTaskId());
        $this->assertEquals(10, $event->getCompletedBy());
        $this->assertEquals(120, $event->getTimeSpent());
        $this->assertEquals(TaskCompleted::class, $event->getName());
    }

    public function testProjectCreatedEvent(): void
    {
        $event = new ProjectCreated(projectId: 100, projectName: 'Test Project', ownerId: 5);

        $this->assertEquals(100, $event->getProjectId());
        $this->assertEquals('Test Project', $event->getProjectName());
        $this->assertEquals(5, $event->getOwnerId());
        $this->assertEquals(ProjectCreated::class, $event->getName());
    }

    public function testDifferentEventsHaveSeparateListeners(): void
    {
        $taskExecuted = false;
        $projectExecuted = false;

        $this->dispatcher->listen(TaskAssigned::class, function () use (&$taskExecuted) {
            $taskExecuted = true;
        });

        $this->dispatcher->listen(ProjectCreated::class, function () use (&$projectExecuted) {
            $projectExecuted = true;
        });

        // Dispatch TaskAssigned
        $this->dispatcher->dispatch(new TaskAssigned(1, 2, 3));
        $this->assertTrue($taskExecuted);
        $this->assertFalse($projectExecuted);

        // Dispatch ProjectCreated
        $this->dispatcher->dispatch(new ProjectCreated(1, 'Test', 2));
        $this->assertTrue($projectExecuted);
    }

    public function testListenersAreSortedByPriorityAfterRegistration(): void
    {
        $this->dispatcher->listen(TaskAssigned::class, fn () => null, 1);
        $this->dispatcher->listen(TaskAssigned::class, fn () => null, 100);
        $this->dispatcher->listen(TaskAssigned::class, fn () => null, 50);

        $listeners = $this->dispatcher->getListeners(TaskAssigned::class);

        $this->assertEquals(100, $listeners[0]['priority']);
        $this->assertEquals(50, $listeners[1]['priority']);
        $this->assertEquals(1, $listeners[2]['priority']);
    }
}

/**
 * Test listener class for event testing
 */
class TestEventListener
{
    public function handle(Event $event): void
    {
        global $testListenerExecuted;
        $testListenerExecuted = true;
    }
}
