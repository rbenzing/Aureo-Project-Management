<?php

//file: Models/TimeEntry.php
declare(strict_types=1);

namespace App\Models;

/**
 * Time Entry Model
 *
 * Rows in time_entries, created when a task timer stops and editable
 * afterwards to correct times, notes and billable status.
 */
class TimeEntry extends BaseModel
{
    protected string $table = 'time_entries';

    /**
     * time_entries carries no is_deleted column, so BaseModel's soft-delete
     * filter would reference a column that does not exist. Deletion here is
     * a real DELETE.
     */
    protected bool $usesSoftDeletes = false;

    protected array $fillable = [
        'task_id', 'user_id', 'start_time', 'end_time', 'duration', 'notes', 'is_billable',
    ];

    public ?int $id = null;
    public int $task_id;
    public int $user_id;
    public string $start_time;
    public ?string $end_time = null;
    public ?int $duration = null;
    public ?string $notes = null;
    public int $is_billable = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * One entry with the task, project and owner names the edit form shows.
     *
     * @return object|null null when the id does not exist
     */
    public function findWithDetails(int $id): ?object
    {
        $results = $this->queryBuilder([
            'alias' => 'te',
            'select' => 'te.*, t.title AS task_title, p.name AS project_name, '
                . 'u.first_name, u.last_name',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'tasks t', 'on' => 'te.task_id = t.id'],
                ['type' => 'LEFT', 'table' => 'projects p', 'on' => 't.project_id = p.id'],
                ['type' => 'LEFT', 'table' => 'users u', 'on' => 'te.user_id = u.id'],
            ],
            'where' => [['column' => 'te.id', 'value' => $id]],
            'limit' => 1,
        ]);

        return $results[0] ?? null;
    }
}
