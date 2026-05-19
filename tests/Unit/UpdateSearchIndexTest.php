<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\Event;
use App\Events\ProjectCreated;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Listeners\UpdateSearchIndex;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Task;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateSearchIndexTest extends TestCase
{
    /** @var SearchIndex&MockObject */
    private SearchIndex $indexMock;

    /** @var Task&MockObject */
    private Task $taskMock;

    /** @var Project&MockObject */
    private Project $projectMock;

    private UpdateSearchIndex $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexMock = $this->createMock(SearchIndex::class);
        $this->taskMock = $this->createMock(Task::class);
        $this->projectMock = $this->createMock(Project::class);

        $this->listener = new UpdateSearchIndex(
            $this->indexMock,
            $this->taskMock,
            $this->projectMock
        );
    }

    // -------------------------------------------------------------------------
    // TaskAssigned — task found
    // -------------------------------------------------------------------------

    public function testHandleTaskAssignedCallsFindWithTaskId(): void
    {
        $task = new \stdClass();
        $task->title = 'Fix login bug';
        $task->description = 'Users cannot log in on Safari.';
        $task->project_id = 3;
        $task->is_deleted = 0;

        $this->taskMock
            ->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($task);

        $this->indexMock->method('upsert')->willReturn(true);

        $this->listener->handle(new TaskAssigned(42, 1, 2));
    }

    public function testHandleTaskAssignedCallsUpsertWithEntityTypeTask(): void
    {
        $task = new \stdClass();
        $task->title = 'Fix login bug';
        $task->description = 'Users cannot log in on Safari.';
        $task->project_id = 3;
        $task->is_deleted = 0;

        $this->taskMock->method('find')->willReturn($task);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'task',
                42,
                'Fix login bug',
                'Users cannot log in on Safari.',
                3,
                'Fix login bug Users cannot log in on Safari.',
                false
            )
            ->willReturn(true);

        $this->listener->handle(new TaskAssigned(42, 1, 2));
    }

    public function testHandleTaskAssignedSnipsDescriptionTo200Chars(): void
    {
        $longDesc = str_repeat('x', 300);

        $task = new \stdClass();
        $task->title = 'A';
        $task->description = $longDesc;
        $task->project_id = null;
        $task->is_deleted = 0;

        $this->taskMock->method('find')->willReturn($task);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'task',
                $this->anything(),
                $this->anything(),
                substr($longDesc, 0, 200),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(true);

        $this->listener->handle(new TaskAssigned(1, 1, 1));
    }

    // -------------------------------------------------------------------------
    // TaskAssigned — task NOT found (returns false)
    // -------------------------------------------------------------------------

    public function testHandleTaskAssignedWhenTaskNotFoundDoesNotCallUpsert(): void
    {
        $this->taskMock->method('find')->willReturn(false);

        $this->indexMock
            ->expects($this->never())
            ->method('upsert');

        $this->listener->handle(new TaskAssigned(99, 1, 2));
    }

    // -------------------------------------------------------------------------
    // TaskCompleted — task found
    // -------------------------------------------------------------------------

    public function testHandleTaskCompletedCallsUpsertWithEntityTypeTask(): void
    {
        $task = new \stdClass();
        $task->title = 'Deploy hotfix';
        $task->description = 'Push to production.';
        $task->project_id = 7;
        $task->is_deleted = 0;

        $this->taskMock
            ->expects($this->once())
            ->method('find')
            ->with(55)
            ->willReturn($task);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'task',
                55,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(true);

        $this->listener->handle(new TaskCompleted(55, 10, 120));
    }

    // -------------------------------------------------------------------------
    // TaskCompleted — task NOT found
    // -------------------------------------------------------------------------

    public function testHandleTaskCompletedWhenTaskNotFoundDoesNotCallUpsert(): void
    {
        $this->taskMock->method('find')->willReturn(false);

        $this->indexMock
            ->expects($this->never())
            ->method('upsert');

        $this->listener->handle(new TaskCompleted(99, 10, 0));
    }

    // -------------------------------------------------------------------------
    // ProjectCreated — project found
    // -------------------------------------------------------------------------

    public function testHandleProjectCreatedCallsFindWithProjectId(): void
    {
        $project = new \stdClass();
        $project->name = 'Aureo';
        $project->description = 'Project management app.';
        $project->is_deleted = 0;

        $this->projectMock
            ->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($project);

        $this->indexMock->method('upsert')->willReturn(true);

        $this->listener->handle(new ProjectCreated(10, 'Aureo', 1));
    }

    public function testHandleProjectCreatedCallsUpsertWithEntityTypeProject(): void
    {
        $project = new \stdClass();
        $project->name = 'Aureo';
        $project->description = 'Project management app.';
        $project->is_deleted = 0;

        $this->projectMock->method('find')->willReturn($project);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'project',
                10,
                'Aureo',
                'Project management app.',
                10,
                'Aureo Project management app.',
                false
            )
            ->willReturn(true);

        $this->listener->handle(new ProjectCreated(10, 'Aureo', 1));
    }

    public function testHandleProjectCreatedSnipsDescriptionTo200Chars(): void
    {
        $longDesc = str_repeat('y', 300);

        $project = new \stdClass();
        $project->name = 'Big Project';
        $project->description = $longDesc;
        $project->is_deleted = 0;

        $this->projectMock->method('find')->willReturn($project);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'project',
                $this->anything(),
                $this->anything(),
                substr($longDesc, 0, 200),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(true);

        $this->listener->handle(new ProjectCreated(5, 'Big Project', 1));
    }

    // -------------------------------------------------------------------------
    // ProjectCreated — project NOT found
    // -------------------------------------------------------------------------

    public function testHandleProjectCreatedWhenProjectNotFoundDoesNotCallUpsert(): void
    {
        $this->projectMock->method('find')->willReturn(false);

        $this->indexMock
            ->expects($this->never())
            ->method('upsert');

        $this->listener->handle(new ProjectCreated(99, 'Ghost', 1));
    }

    // -------------------------------------------------------------------------
    // is_deleted flag propagation
    // -------------------------------------------------------------------------

    public function testHandleTaskAssignedPassesIsDeletedTrueWhenSet(): void
    {
        $task = new \stdClass();
        $task->title = 'Deleted task';
        $task->description = '';
        $task->project_id = 1;
        $task->is_deleted = 1;

        $this->taskMock->method('find')->willReturn($task);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                true
            )
            ->willReturn(true);

        $this->listener->handle(new TaskAssigned(1, 1, 1));
    }

    // -------------------------------------------------------------------------
    // Unrecognised event — no crash
    // -------------------------------------------------------------------------

    public function testHandleWithUnknownEventDoesNotThrow(): void
    {
        // Create a concrete Event subclass not handled by the listener
        $unknownEvent = new class () extends Event {
            public function __construct()
            {
                parent::__construct([]);
            }
        };

        $this->taskMock->expects($this->never())->method('find');
        $this->projectMock->expects($this->never())->method('find');
        $this->indexMock->expects($this->never())->method('upsert');

        // Must not throw
        $this->listener->handle($unknownEvent);

        $this->assertTrue(true); // reached without exception
    }
}
