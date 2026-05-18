<?php

// file: Models/Task.php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskType;
use App\Services\SecurityService;
use App\Utils\Time;
use PDO;
use RuntimeException;

/**
 * Task Model
 *
 * Handles all task-related database operations
 */
class Task extends BaseModel
{
    protected string $table = 'tasks';

    /**
     * Task properties
     */
    public ?int $id = null;
    public int $project_id;
    public ?int $assigned_to = null;
    public string $title;
    public ?string $description = null;
    public string $priority = Priority::NONE->value;
    public int $status_id;
    public ?int $estimated_time = null;
    public ?int $billable_time = null;
    public ?int $time_spent = 0;
    public ?string $start_date = null;
    public ?string $due_date = null;
    public ?string $complete_date = null;
    public ?int $hourly_rate = null;
    public bool $is_hourly = false;
    public bool $is_deleted = false;
    public bool $is_subtask = false;
    public ?int $parent_task_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // New Scrum fields
    public ?int $story_points = null;
    public ?string $acceptance_criteria = null;
    public string $task_type = TaskType::TASK->value;
    public ?int $backlog_priority = null;
    public bool $is_ready_for_sprint = false;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'project_id',
        'assigned_to',
        'title',
        'description',
        'priority',
        'status_id',
        'estimated_time',
        'billable_time',
        'time_spent',
        'start_date',
        'due_date',
        'complete_date',
        'hourly_rate',
        'is_hourly',
        'is_subtask',
        'parent_task_id',
        'story_points',
        'acceptance_criteria',
        'task_type',
        'backlog_priority',
        'is_ready_for_sprint',
    ];

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'title',
        'description',
        'acceptance_criteria',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'title' => ['required', 'string'],
        'project_id' => ['required'],
        'status_id' => ['required'],
        'story_points' => ['nullable', 'integer', 'min:1', 'max:13'],
        'acceptance_criteria' => ['nullable', 'string'],
        'task_type' => ['required', 'enum:App\Enums\TaskType'],
        'priority' => ['nullable', 'enum:App\Enums\Priority'],
        'backlog_priority' => ['nullable', 'integer', 'min:1'],
        'is_ready_for_sprint' => ['boolean'],
    ];

    /**
     * Get task with full details
     * @param int $id
     * @return object|null
     */
    public function findWithDetails(int $id): ?object
    {
        try {
            $sql = "SELECT t.*,
                p.name as project_name,
                ts.name as status_name,
                u.first_name,
                u.last_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN statuses_task ts ON t.status_id = ts.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = :id AND t.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':id' => $id]);
            $task = $stmt->fetch(PDO::FETCH_OBJ);

            if ($task) {
                $task->subtasks = $this->getSubtasks($id);
                $task->time_entries = $this->getTaskTimeEntries($id);
                $task->comments = $this->getTaskComments($id);
                $task->milestones = $this->getTaskMilestones($id);
                $task->history = $this->getTaskHistory($id);

                // Calculate task metrics
                $task->metrics = $this->calculateTaskMetrics($task);

                // Format times for display
                $task->formatted_estimated_time = Time::formatSeconds($task->estimated_time);
                $task->formatted_time_spent = Time::formatSeconds($task->time_spent);
                $task->formatted_billable_time = Time::formatSeconds($task->billable_time);
            }

            return $task ?: null;
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching task details: " . $e->getMessage());
        }
    }

    /**
     * Build ORDER BY clause for task queries
     *
     * @param string $sortField
     * @param string $sortDirection
     * @return string
     */
    private function buildOrderByClause(string $sortField, string $sortDirection): string
    {
        // Validate sort direction
        $sortDirection = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        // Map sort fields to actual database columns
        $fieldMap = [
            'title' => 't.title',
            'project_name' => 'p.name',
            'assigned_to' => 'CONCAT(u.first_name, " ", u.last_name)',
            'priority' => 't.priority',
            'status_id' => 't.status_id',
            'due_date' => 't.due_date',
            'time_spent' => 't.time_spent',
            'created_at' => 't.created_at',
            'updated_at' => 't.updated_at',
        ];

        // Use mapped field or default to due_date
        $dbField = $fieldMap[$sortField] ?? 't.due_date';

        // Build hierarchical ordering: tasks first, then subtasks under their parent tasks
        $hierarchicalOrder = "COALESCE(t.parent_task_id, t.id), t.is_subtask ASC";

        // Special handling for priority (convert to numeric for proper sorting)
        // Using Priority enum sortOrder() values
        if ($sortField === 'priority') {
            $high = Priority::HIGH->sortOrder();
            $medium = Priority::MEDIUM->sortOrder();
            $low = Priority::LOW->sortOrder();
            $none = Priority::NONE->sortOrder();

            return "{$hierarchicalOrder}, CASE
                        WHEN t.priority = '" . Priority::HIGH->value . "' THEN {$high}
                        WHEN t.priority = '" . Priority::MEDIUM->value . "' THEN {$medium}
                        WHEN t.priority = '" . Priority::LOW->value . "' THEN {$low}
                        ELSE {$none}
                    END {$sortDirection}";
        }

        // Special handling for due_date (null values last)
        if ($sortField === 'due_date') {
            return "{$hierarchicalOrder}, CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date {$sortDirection}";
        }

        return "{$hierarchicalOrder}, {$dbField} {$sortDirection}";
    }

    /**
     * Flexible task query method with various filters
     * Consolidates multiple similar query methods into one
     *
     * @param array $filters Filter conditions:
     *   - project_id: int
     *   - assigned_to: int
     *   - unassigned: bool
     *   - exclude_subtasks: bool
     *   - status_id: int
     * @param int $limit Results per page
     * @param int $page Page number
     * @param string $sortField Field to sort by
     * @param string $sortDirection Sort direction (asc/desc)
     * @return array Tasks array
     */
    private function getTasksWithDetails(
        array $filters = [],
        int $limit = 10,
        int $page = 1,
        string $sortField = 'due_date',
        string $sortDirection = 'asc'
    ): array {
        $offset = ($page - 1) * $limit;
        $orderBy = $this->buildOrderByClause($sortField, $sortDirection);

        $where = [];
        $whereRaw = [
            ['sql' => 'p.is_deleted = 0']
        ];

        // Apply filters
        if (isset($filters['project_id'])) {
            $where[] = ['column' => 't.project_id', 'operator' => '=', 'value' => $filters['project_id']];
        }

        if (isset($filters['assigned_to'])) {
            $where[] = ['column' => 't.assigned_to', 'operator' => '=', 'value' => $filters['assigned_to']];
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $whereRaw[] = ['sql' => '(t.assigned_to IS NULL OR t.assigned_to = 0)'];
        }

        if (isset($filters['exclude_subtasks']) && $filters['exclude_subtasks']) {
            $where[] = ['column' => 't.is_subtask', 'operator' => '=', 'value' => 0];
        }

        if (isset($filters['status_id'])) {
            $where[] = ['column' => 't.status_id', 'operator' => '=', 'value' => $filters['status_id']];
        }

        $tasks = $this->queryBuilder([
            'select' => 't.*, p.name as project_name, ts.name as status_name, u.first_name, u.last_name',
            'alias' => 't',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'projects p', 'on' => 't.project_id = p.id'],
                ['type' => 'LEFT', 'table' => 'statuses_task ts', 'on' => 't.status_id = ts.id'],
                ['type' => 'LEFT', 'table' => 'users u', 'on' => 't.assigned_to = u.id'],
            ],
            'where' => $where,
            'whereRaw' => $whereRaw,
            'orderBy' => $orderBy,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        // Format times for display
        foreach ($tasks as $task) {
            $task->formatted_estimated_time = Time::formatSeconds($task->estimated_time);
            $task->formatted_time_spent = Time::formatSeconds($task->time_spent);
            $task->formatted_billable_time = Time::formatSeconds($task->billable_time ?? 0);
        }

        return $tasks;
    }

    /**
     * Get tasks with full details
     * @return ?array
     */
    public function getAllWithDetails(int $limit = 10, int $page = 1, string $sortField = 'due_date', string $sortDirection = 'asc'): ?array
    {
        $tasks = $this->getTasksWithDetails([], $limit, $page, $sortField, $sortDirection);
        return empty($tasks) ? null : $tasks;
    }

    /**
     * Get tasks with full details excluding subtasks
     * @return ?array
     */
    public function getAllWithDetailsNoSubtasks(int $limit = 10, int $page = 1, string $sortField = 'due_date', string $sortDirection = 'asc'): ?array
    {
        return $this->getTasksWithDetails(['exclude_subtasks' => true], $limit, $page, $sortField, $sortDirection);
    }

    /**
     * Get tasks by user ID
     *
     * @param int $userId
     * @param int $limit
     * @param int $page
     * @param string $sortField
     * @param string $sortDirection
     * @return array
     */
    public function getByUserId(int $userId, int $limit = 10, int $page = 1, string $sortField = 'due_date', string $sortDirection = 'asc'): array
    {
        return $this->getTasksWithDetails(['assigned_to' => $userId], $limit, $page, $sortField, $sortDirection);
    }

    /**
     * Get unassigned tasks
     *
     * @param int $limit
     * @param int $page
     * @param string $sortField
     * @param string $sortDirection
     * @return array
     */
    public function getUnassignedTasks(int $limit = 10, int $page = 1, string $sortField = 'due_date', string $sortDirection = 'asc'): array
    {
        return $this->getTasksWithDetails(['unassigned' => true], $limit, $page, $sortField, $sortDirection);
    }

    /**
     * Get tasks by project
     *
     * @param int $projectId
     * @param string $sortField
     * @param string $sortDirection
     * @return array
     */
    public function getByProjectId(int $projectId, string $sortField = 'due_date', string $sortDirection = 'asc'): array
    {
        $orderBy = $this->buildOrderByClause($sortField, $sortDirection);

        $tasks = $this->queryBuilder([
            'select' => 't.*, ts.name AS status_name, u.first_name, u.last_name, p.name as project_name',
            'alias' => 't',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'statuses_task ts', 'on' => 't.status_id = ts.id'],
                ['type' => 'LEFT', 'table' => 'users u', 'on' => 't.assigned_to = u.id'],
                ['type' => 'LEFT', 'table' => 'projects p', 'on' => 't.project_id = p.id'],
            ],
            'where' => [
                ['column' => 't.project_id', 'operator' => '=', 'value' => $projectId],
            ],
            'orderBy' => $orderBy,
        ]);

        return $this->organizeTasksByStatus($tasks);
    }

    /**
     * Get task subtasks
     *
     * @param int $taskId
     * @return array
     */
    public function getSubtasks(int $taskId): array
    {
        try {
            $sql = "SELECT t.*,
                       ts.name as status_name,
                       u.first_name,
                       u.last_name
                FROM tasks t
                LEFT JOIN statuses_task ts ON t.status_id = ts.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.parent_task_id = :task_id
                AND t.is_subtask = 1
                AND t.is_deleted = 0
                ORDER BY t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);
            $subtasks = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Format times for subtasks
            foreach ($subtasks as $subtask) {
                $subtask->formatted_time_spent = Time::formatSeconds($subtask->time_spent);
                $subtask->formatted_estimated_time = Time::formatSeconds($subtask->estimated_time);
            }

            return $subtasks;
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching subtasks: " . $e->getMessage());
        }
    }

    /**
     * Get count of subtasks for a parent task
     *
     * @param int $taskId
     * @return int
     */
    public function getSubtaskCount(int $taskId): int
    {
        try {
            $sql = "SELECT COUNT(*)
                    FROM tasks
                    WHERE parent_task_id = :task_id
                    AND is_subtask = 1
                    AND is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            error_log("Error getting subtask count: " . $e->getMessage());

            return 0;
        }
    }

    /**
     * Get task statuses
     *
     * @return array
     */
    public function getTaskStatuses(): array
    {
        try {
            $sql = "SELECT * FROM statuses_task WHERE is_deleted = 0";
            $stmt = $this->db->executeQuery($sql);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching task statuses: " . $e->getMessage());
        }
    }

    /**
     * Get recent tasks by user
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecentByUser(int $userId, int $limit = 5): array
    {
        try {
            $sql = "SELECT t.*,
                       p.name as project_name,
                       ts.name as status_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN statuses_task ts ON t.status_id = ts.id
                WHERE t.assigned_to = :user_id
                AND t.is_deleted = 0
                ORDER BY t.updated_at DESC
                LIMIT :limit";

            $stmt = $this->db->executeQuery($sql, [
                ':user_id' => $userId,
                ':limit' => $limit,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching recent tasks: " . $e->getMessage());
        }
    }

    /**
     * Get product backlog tasks (not assigned to any sprint)
     *
     * @param int $limit
     * @param int $page
     * @param int|null $projectId Optional project filter
     * @param string $sortField
     * @param string $sortDirection
     * @return array
     */
    public function getProductBacklog(int $limit = 10, int $page = 1, ?int $projectId = null, string $sortField = 'backlog_priority', string $sortDirection = 'asc'): array
    {
        try {
            $offset = ($page - 1) * $limit;

            $whereClause = "WHERE t.is_deleted = 0 AND p.is_deleted = 0
                           AND t.id NOT IN (
                               SELECT st.task_id FROM sprint_tasks st
                               JOIN sprints s ON st.sprint_id = s.id
                               WHERE s.status_id IN (1, 2) AND s.is_deleted = 0
                           )";

            $params = [':limit' => $limit, ':offset' => $offset];

            if ($projectId) {
                $whereClause .= " AND t.project_id = :project_id";
                $params[':project_id'] = $projectId;
            }

            // For backlog, use special ordering if sorting by backlog_priority
            if ($sortField === 'backlog_priority') {
                $orderBy = "ORDER BY
                    CASE WHEN t.backlog_priority IS NULL THEN 1 ELSE 0 END,
                    t.backlog_priority ASC,
                    t.priority DESC,
                    t.created_at DESC";
            } else {
                $orderBy = "ORDER BY " . $this->buildOrderByClause($sortField, $sortDirection);
            }

            $sql = "SELECT t.*,
                p.name as project_name,
                ts.name as status_name,
                u.first_name,
                u.last_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN statuses_task ts ON t.status_id = ts.id
            LEFT JOIN users u ON t.assigned_to = u.id
            {$whereClause}
            {$orderBy}
            LIMIT :limit OFFSET :offset";

            $stmt = $this->db->executeQuery($sql, $params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching product backlog: " . $e->getMessage());
        }
    }

    /**
     * Count product backlog tasks
     *
     * @param int|null $projectId Optional project filter
     * @return int
     */
    public function countProductBacklog(?int $projectId = null): int
    {
        try {
            $whereClause = "WHERE t.is_deleted = 0 AND p.is_deleted = 0
                           AND t.id NOT IN (
                               SELECT st.task_id FROM sprint_tasks st
                               JOIN sprints s ON st.sprint_id = s.id
                               WHERE s.status_id IN (1, 2) AND s.is_deleted = 0
                           )";

            $params = [];

            if ($projectId) {
                $whereClause .= " AND t.project_id = :project_id";
                $params[':project_id'] = $projectId;
            }

            $sql = "SELECT COUNT(*)
                    FROM tasks t
                    LEFT JOIN projects p ON t.project_id = p.id
                    {$whereClause}";

            $stmt = $this->db->executeQuery($sql, $params);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error counting product backlog: " . $e->getMessage());
        }
    }

    /**
     * Count unassigned tasks
     *
     * @return int
     */
    public function countUnassignedTasks(): int
    {
        try {
            $sql = "SELECT COUNT(*)
                    FROM tasks t
                    LEFT JOIN projects p ON t.project_id = p.id
                    WHERE (t.assigned_to IS NULL OR t.assigned_to = 0)
                    AND t.is_deleted = 0 AND p.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error counting unassigned tasks: " . $e->getMessage());
        }
    }

    /**
     * Get tasks available for sprint planning
     *
     * @param int $projectId
     * @return array
     */
    public function getAvailableForSprint(int $projectId): array
    {
        try {
            $sql = "SELECT t.*,
                ts.name as status_name,
                u.first_name,
                u.last_name
            FROM tasks t
            LEFT JOIN statuses_task ts ON t.status_id = ts.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.project_id = :project_id
            AND t.is_deleted = 0
            AND t.is_ready_for_sprint = 1
            AND t.id NOT IN (
                SELECT st.task_id FROM sprint_tasks st
                JOIN sprints s ON st.sprint_id = s.id
                WHERE s.status_id IN (1, 2) AND s.is_deleted = 0
            )
            ORDER BY
                CASE WHEN t.backlog_priority IS NULL THEN 1 ELSE 0 END,
                t.backlog_priority ASC,
                t.priority DESC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching tasks available for sprint: " . $e->getMessage());
        }
    }

    /**
     * Get task time entries
     *
     * @param int $taskId
     * @return array
     */
    public function getTaskTimeEntries(int $taskId): array
    {
        try {
            $sql = "SELECT te.*,
                       u.first_name,
                       u.last_name
                FROM time_entries te
                LEFT JOIN users u ON te.user_id = u.id
                WHERE te.task_id = :task_id
                ORDER BY te.start_time DESC";

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);
            $entries = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Format duration
            foreach ($entries as $entry) {
                $entry->formatted_duration = Time::formatSeconds($entry->duration);
            }

            return $entries;
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching time entries: " . $e->getMessage());
        }
    }

    /**
     * Create a time entry
     *
     * @param array $data
     * @return int The new time entry ID
     */
    public function createTimeEntry(array $data): int
    {
        try {
            $fields = array_keys($data);
            $placeholders = array_map(fn ($field) => ":$field", $fields);

            $sql = sprintf(
                "INSERT INTO time_entries (%s) VALUES (%s)",
                implode(', ', $fields),
                implode(', ', $placeholders)
            );

            $params = array_combine($placeholders, array_values($data));

            $success = $this->db->executeInsertUpdate($sql, $params);

            if (!$success) {
                throw new RuntimeException("Failed to create time entry");
            }

            return $this->db->lastInsertId();
        } catch (\Exception $e) {
            try {
                $securityService = SecurityService::getInstance();
                $safeMessage = $securityService->getSafeErrorMessage($e->getMessage(), "Error creating time entry");

                throw new RuntimeException($safeMessage);
            } catch (\Exception $securityException) {
                throw new RuntimeException("Error creating time entry");
            }
        }
    }

    /**
     * Get task comments
     *
     * @param int $taskId
     * @return array
     */
    public function getTaskComments(int $taskId): array
    {
        try {
            $sql = "SELECT tc.*,
                       u.first_name,
                       u.last_name
                FROM task_comments tc
                LEFT JOIN users u ON tc.user_id = u.id
                WHERE tc.task_id = :task_id
                AND tc.is_deleted = 0
                ORDER BY tc.created_at DESC";

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching task comments: " . $e->getMessage());
        }
    }

    /**
     * Add a comment to a task
     *
     * @param int $taskId
     * @param int $userId
     * @param string $content
     * @return int The new comment ID
     */
    public function addComment(int $taskId, int $userId, string $content): int
    {
        try {
            $sql = "INSERT INTO task_comments (task_id, user_id, content)
                    VALUES (:task_id, :user_id, :content)";

            $success = $this->db->executeInsertUpdate($sql, [
                ':task_id' => $taskId,
                ':user_id' => $userId,
                ':content' => $content,
            ]);

            if (!$success) {
                throw new RuntimeException("Failed to add comment");
            }

            return $this->db->lastInsertId();
        } catch (\Exception $e) {
            throw new RuntimeException("Error adding comment: " . $e->getMessage());
        }
    }

    /**
     * Add a history entry
     *
     * @param int $taskId
     * @param int $userId
     * @param string $action
     * @param string|null $fieldChanged
     * @param string|null $oldValue
     * @param string|null $newValue
     * @return int The new history entry ID
     */
    public function addHistoryEntry(
        int $taskId,
        int $userId,
        string $action,
        ?string $fieldChanged = null,
        ?string $oldValue = null,
        ?string $newValue = null
    ): int {
        try {
            $sql = "INSERT INTO task_history (task_id, user_id, action, field_changed, old_value, new_value)
                    VALUES (:task_id, :user_id, :action, :field_changed, :old_value, :new_value)";

            $success = $this->db->executeInsertUpdate($sql, [
                ':task_id' => $taskId,
                ':user_id' => $userId,
                ':action' => $action,
                ':field_changed' => $fieldChanged,
                ':old_value' => $oldValue,
                ':new_value' => $newValue,
            ]);

            if (!$success) {
                throw new RuntimeException("Failed to add history entry");
            }

            return $this->db->lastInsertId();
        } catch (\Exception $e) {
            throw new RuntimeException("Error adding history entry: " . $e->getMessage());
        }
    }

    /**
     * Get task history
     *
     * @param int $taskId
     * @return array
     */
    public function getTaskHistory(int $taskId): array
    {
        try {
            $sql = "SELECT th.*,
                       u.first_name,
                       u.last_name
                FROM task_history th
                LEFT JOIN users u ON th.user_id = u.id
                WHERE th.task_id = :task_id
                ORDER BY th.created_at DESC";

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching task history: " . $e->getMessage());
        }
    }

    /**
     * Override update method to include history tracking
     *
     * @param int $id Record ID
     * @param array $data Updated record data
     * @param int|null $userId User ID for history tracking (if null, uses session)
     * @return bool Success status
     * @throws RuntimeException
     */
    public function update(int $id, array $data, ?int $userId = null): bool
    {
        try {
            // Get current task data for history tracking
            $currentTask = $this->find($id);
            if (!$currentTask) {
                throw new RuntimeException("Task not found for update");
            }

            // Get user ID for history tracking
            if ($userId === null) {
                $userId = $_SESSION['user']['id'] ?? null;
            }

            // Track changes for history before updating
            if ($userId) {
                $this->trackTaskChanges($id, $userId, $currentTask, $data);
            }

            // Call parent update method
            return parent::update($id, $data);
        } catch (\Exception $e) {
            throw new RuntimeException("Error updating task: " . $e->getMessage());
        }
    }

    /**
     * Track task changes for history
     *
     * @param int $taskId
     * @param int $userId
     * @param object $currentTask
     * @param array $newData
     */
    private function trackTaskChanges(int $taskId, int $userId, object $currentTask, array $newData): void
    {
        $fieldMappings = [
            'title' => 'Title',
            'description' => 'Description',
            'priority' => 'Priority',
            'status_id' => 'Status',
            'project_id' => 'Project',
            'assigned_to' => 'Assigned To',
            'due_date' => 'Due Date',
            'estimated_time' => 'Estimated Time',
            'hourly_rate' => 'Hourly Rate',
            'is_hourly' => 'Billable',
            'parent_task_id' => 'Parent Task',
            'story_points' => 'Story Points',
            'acceptance_criteria' => 'Acceptance Criteria',
            'task_type' => 'Task Type',
            'backlog_priority' => 'Backlog Priority',
            'is_ready_for_sprint' => 'Ready for Sprint',
        ];

        foreach ($newData as $field => $newValue) {
            if (!isset($fieldMappings[$field])) {
                continue;
            }

            $oldValue = $currentTask->$field ?? null;

            // Skip if values are the same
            if ($oldValue == $newValue) {
                continue;
            }

            // Format values for display
            $formattedOldValue = $this->formatFieldValue($field, $oldValue);
            $formattedNewValue = $this->formatFieldValue($field, $newValue);

            $this->addHistoryEntry(
                $taskId,
                $userId,
                'updated',
                $fieldMappings[$field],
                $formattedOldValue,
                $formattedNewValue
            );
        }
    }

    /**
     * Format field values for history display
     *
     * @param string $field
     * @param mixed $value
     * @return string
     */
    private function formatFieldValue(string $field, $value): string
    {
        if ($value === null) {
            return 'None';
        }

        switch ($field) {
            case 'status_id':
                return $this->getStatusName($value);
            case 'project_id':
                return $this->getProjectName($value);
            case 'assigned_to':
                return $this->getUserName($value);
            case 'parent_task_id':
                return $this->getTaskTitle($value);
            case 'priority':
                return ucfirst($value);
            case 'is_hourly':
                return $value ? 'Yes' : 'No';
            case 'estimated_time':
                return Time::formatSeconds($value);
            default:
                return (string) $value;
        }
    }

    /**
     * Get status name by ID with proper formatting
     */
    private function getStatusName(int $statusId): string
    {
        try {
            // Use centralized status helper for consistent formatting
            require_once BASE_PATH . '/../src/views/Layouts/ViewHelpers.php';
            $statusInfo = getTaskStatusInfo($statusId);

            return $statusInfo['label'];
        } catch (\Exception $e) {
            return 'Unknown Status';
        }
    }

    /**
     * Get project name by ID
     */
    private function getProjectName(int $projectId): string
    {
        try {
            $sql = "SELECT name FROM projects WHERE id = :id";
            $stmt = $this->db->executeQuery($sql, [':id' => $projectId]);

            return $stmt->fetchColumn() ?: 'Unknown Project';
        } catch (\Exception $e) {
            return 'Unknown Project';
        }
    }

    /**
     * Get user name by ID
     */
    private function getUserName(int $userId): string
    {
        try {
            $sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = :id";
            $stmt = $this->db->executeQuery($sql, [':id' => $userId]);

            return $stmt->fetchColumn() ?: 'Unknown User';
        } catch (\Exception $e) {
            return 'Unknown User';
        }
    }

    /**
     * Get task title by ID
     */
    private function getTaskTitle(int $taskId): string
    {
        try {
            $sql = "SELECT title FROM tasks WHERE id = :id";
            $stmt = $this->db->executeQuery($sql, [':id' => $taskId]);

            return $stmt->fetchColumn() ?: 'Unknown Task';
        } catch (\Exception $e) {
            return 'Unknown Task';
        }
    }

    /**
     * Override create method to include history tracking
     *
     * @param array $data Record data
     * @param int|null $userId User ID for history tracking (if null, uses session)
     * @return int The new record ID
     * @throws RuntimeException
     */
    public function create(array $data, ?int $userId = null): int
    {
        try {
            // Get user ID for history tracking
            if ($userId === null) {
                $userId = $_SESSION['user']['id'] ?? null;
            }

            // Call parent create method
            $taskId = parent::create($data);

            // Add creation history entry
            if ($userId && $taskId) {
                $this->addHistoryEntry(
                    $taskId,
                    $userId,
                    'created',
                    null,
                    null,
                    'Task created'
                );
            }

            return $taskId;
        } catch (\Exception $e) {
            throw new RuntimeException("Error creating task: " . $e->getMessage());
        }
    }

    /**
     * Add timer start history entry
     *
     * @param int $taskId
     * @param int $userId
     */
    public function addTimerStartHistory(int $taskId, int $userId): void
    {
        $this->addHistoryEntry(
            $taskId,
            $userId,
            'timer_started',
            null,
            null,
            'Timer started at ' . date('Y-m-d H:i:s')
        );
    }

    /**
     * Add timer stop history entry
     *
     * @param int $taskId
     * @param int $userId
     * @param int $duration Duration in seconds
     */
    public function addTimerStopHistory(int $taskId, int $userId, int $duration): void
    {
        $formattedDuration = Time::formatSeconds($duration);
        $this->addHistoryEntry(
            $taskId,
            $userId,
            'timer_stopped',
            null,
            null,
            "Timer stopped at " . date('Y-m-d H:i:s') . " (Duration: {$formattedDuration})"
        );
    }

    /**
     * Get task milestones
     *
     * @param int $taskId
     * @return array
     */
    public function getTaskMilestones(int $taskId): array
    {
        try {
            $sql = "SELECT m.*,
                       ms.name as status_name
                FROM milestones m
                JOIN milestone_tasks mt ON m.id = mt.milestone_id
                LEFT JOIN statuses_milestone ms ON m.status_id = ms.id
                WHERE mt.task_id = :task_id
                AND m.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching task milestones: " . $e->getMessage());
        }
    }

    /**
     * Get total time spent for a user
     *
     * @param int $userId
     * @return int Total time spent in seconds
     */
    public function getTotalTimeSpent(int $userId): int
    {
        try {
            $sql = "SELECT COALESCE(SUM(time_spent), 0) 
                    FROM tasks 
                    WHERE assigned_to = :user_id 
                    AND is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error calculating total time spent: " . $e->getMessage());
        }
    }

    /**
     * Get total billable time for a user
     *
     * @param int $userId
     * @return int Total billable time in seconds
     */
    public function getTotalBillableTime(int $userId): int
    {
        try {
            $sql = "SELECT COALESCE(SUM(billable_time), 0) 
                    FROM tasks 
                    WHERE assigned_to = :user_id 
                    AND is_hourly = 1 
                    AND is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error calculating total billable time: " . $e->getMessage());
        }
    }

    /**
     * Get time spent this week for a user
     *
     * @param int $userId
     * @return int Time spent this week in seconds
     */
    public function getWeeklyTimeSpent(int $userId): int
    {
        try {
            $sql = "SELECT COALESCE(SUM(duration), 0)
                    FROM time_entries 
                    WHERE user_id = :user_id 
                    AND start_time BETWEEN 
                        DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
                        AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error calculating weekly time spent: " . $e->getMessage());
        }
    }

    /**
     * Get time spent this month for a user
     *
     * @param int $userId
     * @return int Time spent this month in seconds
     */
    public function getMonthlyTimeSpent(int $userId): int
    {
        try {
            $sql = "SELECT COALESCE(SUM(duration), 0)
                    FROM time_entries 
                    WHERE user_id = :user_id 
                    AND start_time BETWEEN 
                        DATE_FORMAT(CURDATE(), '%Y-%m-01') 
                        AND LAST_DAY(CURDATE())";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            throw new RuntimeException("Error calculating monthly time spent: " . $e->getMessage());
        }
    }

    /**
     * Organize tasks by status
     *
     * @param array $tasks
     * @return array
     */
    private function organizeTasksByStatus(array $tasks): array
    {
        try {
            // Get status mappings
            $statusMap = [];
            $statuses = $this->getTaskStatuses();
            foreach ($statuses as $status) {
                $normalizedKey = strtolower(str_replace(' ', '_', $status->name));
                $statusMap[$status->id] = $normalizedKey;
            }

            // Organize by status
            $organized = [];
            foreach ($statusMap as $id => $key) {
                $organized[$key] = [];
            }

            // Default fallbacks in case statuses are missing
            if (!isset($organized['open'])) {
                $organized['open'] = [];
            }
            if (!isset($organized['in_progress'])) {
                $organized['in_progress'] = [];
            }
            if (!isset($organized['completed'])) {
                $organized['completed'] = [];
            }

            foreach ($tasks as $task) {
                $statusKey = $statusMap[$task->status_id] ?? 'other';
                if (!isset($organized[$statusKey])) {
                    $organized[$statusKey] = [];
                }
                $organized[$statusKey][] = $task;
            }

            return $organized;
        } catch (\Exception $e) {
            throw new RuntimeException("Error organizing tasks by status: " . $e->getMessage());
        }
    }

    /**
     * Calculate task metrics
     *
     * @param object $task
     * @return array
     */
    private function calculateTaskMetrics(object $task): array
    {
        $now = new \DateTime();
        $dueDate = $task->due_date ? new \DateTime($task->due_date) : null;
        $startDate = $task->start_date ? new \DateTime($task->start_date) : null;

        // Time tracking metrics
        $estimatedTime = $task->estimated_time ?? 0;
        $timeSpent = $task->time_spent ?? 0;
        $timeRemaining = max(0, $estimatedTime - $timeSpent);

        // Progress calculation
        $progressPercentage = $estimatedTime > 0
            ? round(($timeSpent / $estimatedTime) * 100, 2)
            : 0;

        // Deadline metrics
        $isOverdue = Time::isOverdue($task->due_date, $task->status_id, [5, 6]);
        $daysUntilDue = Time::daysRemaining($task->due_date);
        $daysInProgress = $startDate ? $now->diff($startDate)->days : 0;

        return [
            'estimated_time' => $estimatedTime,
            'time_spent' => $timeSpent,
            'time_remaining' => $timeRemaining,
            'progress_percentage' => $progressPercentage,
            'is_overdue' => $isOverdue,
            'days_until_due' => $daysUntilDue,
            'days_in_progress' => $daysInProgress,
            'complexity' => $this->calculateTaskComplexity($task),
        ];
    }

    /**
     * Calculate task complexity
     *
     * @param object $task
     * @return string
     */
    private function calculateTaskComplexity(object $task): string
    {
        $complexityFactors = 0;

        // Estimated time complexity
        $complexityFactors += match (true) {
            ($task->estimated_time ?? 0) > 14400 => 3, // > 4 hours
            ($task->estimated_time ?? 0) > 7200 => 2,  // > 2 hours
            ($task->estimated_time ?? 0) > 3600 => 1,  // > 1 hour
            default => 0
        };

        // Priority complexity
        $complexityFactors += match ($task->priority) {
            'high' => 2,
            'medium' => 1,
            default => 0
        };

        // Subtask complexity
        if ($task->is_subtask) {
            $complexityFactors += 1;
        }

        // Milestone association complexity
        if (!empty($task->milestones)) {
            $complexityFactors += 1;
        }

        return match (true) {
            $complexityFactors >= 4 => 'Very High',
            $complexityFactors >= 3 => 'High',
            $complexityFactors >= 2 => 'Medium',
            $complexityFactors >= 1 => 'Low',
            default => 'Very Low'
        };
    }
}
