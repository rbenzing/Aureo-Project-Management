<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Event;
use App\Events\ProjectCreated;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of the abstract Event base class (getName/getPayload/
 * getTimestamp/get) plus the three concrete event subclasses' constructors
 * and payload accessors.
 *
 * NOTE: tests/Unit/EventSystemTest.php (outside this module's scope) already
 * exercises these same classes behaviourally, but PHPUnit's metadata-driven
 * coverage only merges a class's executed lines into the report when at
 * least one test in the run declares #[CoversClass] for that exact class.
 * Since Event/TaskAssigned/TaskCompleted/ProjectCreated are only ever
 * #[CoversClass]-annotated in the excluded root-level test, none of their
 * lines would be attributed to src/Events without a declaration here too.
 */
#[CoversClass(Event::class)]
#[CoversClass(TaskAssigned::class)]
#[CoversClass(TaskCompleted::class)]
#[CoversClass(ProjectCreated::class)]
class EventTest extends TestCase
{
    public function testGetNameReturnsTheConcreteSubclassName(): void
    {
        $event = new TaskAssigned(1, 2, 3);

        $this->assertSame(TaskAssigned::class, $event->getName());
    }

    public function testGetPayloadReturnsTheFullPayloadArray(): void
    {
        $event = new TaskAssigned(10, 20, 30);

        $this->assertSame(
            ['task_id' => 10, 'user_id' => 20, 'assigned_by' => 30],
            $event->getPayload()
        );
    }

    public function testGetTimestampReturnsAFloatSetAtConstructionTime(): void
    {
        $before = microtime(true);
        $event = new TaskAssigned(1, 2, 3);
        $after = microtime(true);

        $this->assertIsFloat($event->getTimestamp());
        $this->assertGreaterThanOrEqual($before, $event->getTimestamp());
        $this->assertLessThanOrEqual($after, $event->getTimestamp());
    }

    public function testGetReturnsPayloadValueOrDefaultWhenMissing(): void
    {
        $event = new TaskAssigned(5, 6, 7);

        $this->assertSame(5, $event->get('task_id'));
        $this->assertNull($event->get('does_not_exist'));
        $this->assertSame('fallback', $event->get('does_not_exist', 'fallback'));
    }

    public function testTaskAssignedExposesItsOwnPayload(): void
    {
        $event = new TaskAssigned(taskId: 11, userId: 22, assignedBy: 33);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame(11, $event->getTaskId());
        $this->assertSame(22, $event->getUserId());
        $this->assertSame(33, $event->getAssignedBy());
        $this->assertSame(['task_id' => 11, 'user_id' => 22, 'assigned_by' => 33], $event->getPayload());
    }

    public function testTaskCompletedExposesItsOwnPayload(): void
    {
        $event = new TaskCompleted(taskId: 42, completedBy: 9, timeSpent: 90);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame(42, $event->getTaskId());
        $this->assertSame(9, $event->getCompletedBy());
        $this->assertSame(90, $event->getTimeSpent());
        $this->assertSame(['task_id' => 42, 'completed_by' => 9, 'time_spent' => 90], $event->getPayload());
    }

    public function testProjectCreatedExposesItsOwnPayload(): void
    {
        $event = new ProjectCreated(projectId: 3, projectName: 'Aureo', ownerId: 1);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame(3, $event->getProjectId());
        $this->assertSame('Aureo', $event->getProjectName());
        $this->assertSame(1, $event->getOwnerId());
        $this->assertSame(['project_id' => 3, 'project_name' => 'Aureo', 'owner_id' => 1], $event->getPayload());
    }
}
