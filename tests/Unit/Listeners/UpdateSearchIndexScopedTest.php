<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Event;
use App\Events\ProjectCreated;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Listeners\UpdateSearchIndex;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * NOTE: tests/Unit/UpdateSearchIndexTest.php (outside this module's scope)
 * already exercises UpdateSearchIndex's behaviour exhaustively, but PHPUnit's
 * metadata-driven coverage only merges a class's executed lines into the
 * report when at least one test in the *executed run* declares
 * #[CoversClass] for that exact class. Since the exhaustive suite lives
 * outside tests/Unit/Listeners/, none of UpdateSearchIndex's lines would be
 * attributed to src/Listeners when this module is verified in isolation
 * without a #[CoversClass] declaration here too. These tests re-exercise
 * every branch (with different fixtures) so the module's own coverage run
 * reflects reality.
 */
#[CoversClass(UpdateSearchIndex::class)]
#[UsesClass(Event::class)]
#[UsesClass(TaskAssigned::class)]
#[UsesClass(TaskCompleted::class)]
#[UsesClass(ProjectCreated::class)]
class UpdateSearchIndexScopedTest extends TestCase
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

    public function testTaskAssignedUpsertsTaskEntityWithSearchBlob(): void
    {
        $task = new \stdClass();
        $task->title = 'Refactor payment gateway';
        $task->description = 'Split legacy provider code into adapters.';
        $task->project_id = 12;
        $task->is_deleted = 0;

        $this->taskMock->expects($this->once())->method('find')->with(7)->willReturn($task);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'task',
                7,
                'Refactor payment gateway',
                'Split legacy provider code into adapters.',
                12,
                'Refactor payment gateway Split legacy provider code into adapters.',
                false
            )
            ->willReturn(true);

        $this->listener->handle(new TaskAssigned(taskId: 7, userId: 1, assignedBy: 2));
    }

    public function testTaskAssignedSkipsUpsertWhenTaskMissing(): void
    {
        $this->taskMock->method('find')->willReturn(false);

        $this->indexMock->expects($this->never())->method('upsert');

        $this->listener->handle(new TaskAssigned(taskId: 404, userId: 1, assignedBy: 2));
    }

    public function testTaskCompletedUpsertsTaskEntityAndTruncatesLongDescription(): void
    {
        $longDescription = str_repeat('z', 500);

        $task = new \stdClass();
        $task->title = 'Migrate database';
        $task->description = $longDescription;
        $task->project_id = 3;
        $task->is_deleted = 1;

        $this->taskMock->method('find')->willReturn($task);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'task',
                8,
                'Migrate database',
                substr($longDescription, 0, 200),
                3,
                $this->anything(),
                true
            )
            ->willReturn(true);

        $this->listener->handle(new TaskCompleted(taskId: 8, completedBy: 5, timeSpent: 300));
    }

    public function testTaskCompletedSkipsUpsertWhenTaskMissing(): void
    {
        $this->taskMock->method('find')->willReturn(false);

        $this->indexMock->expects($this->never())->method('upsert');

        $this->listener->handle(new TaskCompleted(taskId: 404, completedBy: 5, timeSpent: 0));
    }

    public function testProjectCreatedUpsertsProjectEntityWithSearchBlob(): void
    {
        $project = new \stdClass();
        $project->name = 'Customer Portal';
        $project->description = 'Self-service portal for customers.';
        $project->is_deleted = 0;

        $this->projectMock->expects($this->once())->method('find')->with(15)->willReturn($project);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with(
                'project',
                15,
                'Customer Portal',
                'Self-service portal for customers.',
                15,
                'Customer Portal Self-service portal for customers.',
                false
            )
            ->willReturn(true);

        $this->listener->handle(new ProjectCreated(projectId: 15, projectName: 'Customer Portal', ownerId: 4));
    }

    public function testProjectCreatedSkipsUpsertWhenProjectMissing(): void
    {
        $this->projectMock->method('find')->willReturn(false);

        $this->indexMock->expects($this->never())->method('upsert');

        $this->listener->handle(new ProjectCreated(projectId: 404, projectName: 'Ghost', ownerId: 1));
    }

    public function testProjectCreatedFallsBackToEmptyStringsWhenNameAndDescriptionMissing(): void
    {
        // Neither `name` nor `description` is set on the fetched record, so the
        // listener's `?? ''` fallbacks and array_filter() on an all-empty pair
        // must be exercised without emitting undefined-property notices.
        $project = new \stdClass();
        $project->is_deleted = 0;

        $this->projectMock->method('find')->willReturn($project);

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with('project', 20, '', '', 20, '', false)
            ->willReturn(true);

        $this->listener->handle(new ProjectCreated(projectId: 20, projectName: 'Untitled', ownerId: 1));
    }

    public function testHandleWithUnrelatedEventDoesNothing(): void
    {
        $unhandled = new class () extends Event {
            public function __construct()
            {
                parent::__construct([]);
            }
        };

        $this->taskMock->expects($this->never())->method('find');
        $this->projectMock->expects($this->never())->method('find');
        $this->indexMock->expects($this->never())->method('upsert');

        $this->listener->handle($unhandled);
    }
}
