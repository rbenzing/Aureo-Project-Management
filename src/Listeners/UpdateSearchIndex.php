<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Event;
use App\Events\ProjectCreated;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Task;

/**
 * Update Search Index Listener
 *
 * Keeps the searchable_index table in sync whenever tasks or projects change.
 */
class UpdateSearchIndex
{
    private SearchIndex $index;
    private Task $taskModel;
    private Project $projectModel;

    public function __construct(
        ?SearchIndex $index = null,
        ?Task $taskModel = null,
        ?Project $projectModel = null
    ) {
        $this->index = $index ?? new SearchIndex();
        $this->taskModel = $taskModel ?? new Task();
        $this->projectModel = $projectModel ?? new Project();
    }

    /**
     * Route the incoming event to the correct handler.
     */
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof TaskAssigned => $this->handleTask($event->getTaskId()),
            $event instanceof TaskCompleted => $this->handleTask($event->getTaskId()),
            $event instanceof ProjectCreated => $this->handleProject($event->getProjectId()),
            default => null,
        };
    }

    private function handleTask(int $taskId): void
    {
        $task = $this->taskModel->find($taskId);
        if ($task === null || $task === false) {
            return;
        }

        $this->index->upsert(
            entityType: 'task',
            entityId:   $taskId,
            title:      $task->title ?? '',
            snippet:    substr($task->description ?? '', 0, 200),
            projectId:  isset($task->project_id) ? (int) $task->project_id : null,
            searchBlob: implode(' ', array_filter([
                $task->title ?? '',
                $task->description ?? '',
            ])),
            isDeleted:  (bool) ($task->is_deleted ?? false),
        );
    }

    private function handleProject(int $projectId): void
    {
        $project = $this->projectModel->find($projectId);
        if ($project === null || $project === false) {
            return;
        }

        $this->index->upsert(
            entityType: 'project',
            entityId:   $projectId,
            title:      $project->name ?? '',
            snippet:    substr($project->description ?? '', 0, 200),
            projectId:  $projectId,
            searchBlob: implode(' ', array_filter([
                $project->name ?? '',
                $project->description ?? '',
            ])),
            isDeleted:  (bool) ($project->is_deleted ?? false),
        );
    }
}
