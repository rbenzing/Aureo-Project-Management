<?php

// file: Models/Sprint.php
declare(strict_types=1);

namespace App\Models;

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use PDO;
use RuntimeException;

/**
 * Sprint Model
 *
 * Handles all sprint-related database operations
 */
class Sprint extends BaseModel
{
    protected string $table = 'sprints';

    /**
     * Sprint properties
     */
    public ?int $id = null;
    public int $project_id;
    public string $name;
    public ?string $description = null;
    public ?string $sprint_goal = null;
    public string $start_date;
    public string $end_date;
    public int $status_id; // Default: Planning

    public function __construct()
    {
        parent::__construct();
        $this->status_id = SprintStatus::PLANNING->value;
    }
    public bool $is_deleted = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Properties that can be mass assigned
     */
    protected array $fillable = [
        'project_id',
        'name',
        'description',
        'sprint_goal',
        'start_date',
        'end_date',
        'status_id',
    ];

    /**
     * Sprint relationship types
     */
    public const RELATIONSHIP_PROJECT = 'project';
    public const RELATIONSHIP_MILESTONE = 'milestone';
    public const RELATIONSHIP_EPIC = 'epic';

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'name',
        'description',
        'sprint_goal',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'name' => ['required', 'string'],
        'project_id' => ['required'],
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date'],
        'status_id' => ['required'],
    ];

    /**
     * Get sprints with tasks
     *
     * @param int $limit Items per page
     * @param int $page Current page number
     * @return array
     */
    public function getAllWithTasks(int $limit = 10, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;
        $completedStatus = TaskStatus::COMPLETED->value;

        return $this->queryBuilder([
            'select' => "s.*, p.name as project_name, ss.name as status_name,
                COUNT(st.task_id) as task_count,
                (SELECT COUNT(t.id) FROM tasks t
                 JOIN sprint_tasks spt ON t.id = spt.task_id
                 WHERE spt.sprint_id = s.id
                 AND t.status_id = {$completedStatus}
                 AND t.is_deleted = 0) as completed_tasks",
            'joins' => [
                ['type' => 'LEFT', 'table' => 'projects p', 'on' => 's.project_id = p.id'],
                ['type' => 'LEFT', 'table' => 'statuses_sprint ss', 'on' => 's.status_id = ss.id'],
                ['type' => 'LEFT', 'table' => 'sprint_tasks st', 'on' => 's.id = st.sprint_id'],
            ],
            'whereRaw' => [
                ['sql' => 'GROUP BY s.id'],
            ],
            'orderBy' => 's.start_date DESC',
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Find sprint with details including tasks
     *
     * @param int $id
     * @param array $options Load options to control what gets fetched:
     *   - tasks: bool (default: true)
     *   - velocity: bool (default: true)
     *   - relationships: bool (default: true)
     *   - milestones: bool (default: true)
     * @return object|null
     */
    public function findWithDetails(int $id, array $options = []): ?object
    {
        // Default options
        $defaultOptions = [
            'tasks' => true,
            'velocity' => true,
            'relationships' => true,
            'milestones' => true,
        ];

        $options = array_merge($defaultOptions, $options);

        $sprints = $this->queryBuilder([
            'select' => 's.*, p.name as project_name, ss.name as status_name',
            'alias' => 's',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'projects p', 'on' => 's.project_id = p.id'],
                ['type' => 'LEFT', 'table' => 'statuses_sprint ss', 'on' => 's.status_id = ss.id'],
            ],
            'where' => [
                ['column' => 's.id', 'operator' => '=', 'value' => $id],
            ],
            'limit' => 1,
        ]);

        if (empty($sprints)) {
            return null;
        }

        $sprint = $sprints[0];

        // Conditionally load related data based on options
        if ($options['tasks']) {
            $sprint->tasks = $this->getSprintTasks($id);
        }

        if ($options['velocity']) {
            $sprint->velocity = $this->getSprintVelocity($id);
        }

        if ($options['relationships']) {
            $sprint->relationships = $this->getSprintRelationships($id);
        }

        if ($options['milestones']) {
            $sprint->milestones = $this->getSprintMilestones($id);
        }

        return $sprint;
    }

    /**
     * Get sprint basic details without expensive related data
     * Optimized for list views where full details are not needed
     *
     * @param int $id
     * @return object|null
     */
    public function findBasic(int $id): ?object
    {
        return $this->findWithDetails($id, [
            'tasks' => false,
            'velocity' => false,
            'relationships' => false,
            'milestones' => false,
        ]);
    }

    /**
     * Get sprint statuses
     *
     * @return array
     */
    public function getSprintStatuses(): array
    {
        $sql = "SELECT * FROM statuses_sprint WHERE is_deleted = 0";
        $stmt = $this->db->executeQuery($sql);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get tasks for a sprint
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintTasks(int $sprintId): array
    {
        $sql = "SELECT t.*, 
                    ts.name as status_name,
                    u.first_name, 
                    u.last_name
                FROM tasks t
                JOIN sprint_tasks st ON t.id = st.task_id
                LEFT JOIN statuses_task ts ON t.status_id = ts.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE st.sprint_id = :sprint_id AND t.is_deleted = 0
                ORDER BY t.priority DESC, t.due_date ASC";

        $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get hierarchical sprint task data with epics, milestones, and tasks
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintHierarchy(int $sprintId): array
    {
        try {
            $hierarchy = [];

            // Get all epics that have tasks in this sprint
            $epics = $this->getSprintEpics($sprintId);

            foreach ($epics as $epic) {
                $epicData = [
                    'type' => 'epic',
                    'data' => $epic,
                    'milestones' => [],
                    'tasks' => [],
                ];

                // Get milestones for this epic that have tasks in this sprint
                $milestones = $this->getSprintEpicMilestones($sprintId, $epic->id);

                foreach ($milestones as $milestone) {
                    $milestoneData = [
                        'type' => 'milestone',
                        'data' => $milestone,
                        'tasks' => $this->getSprintMilestoneTasks($sprintId, $milestone->id),
                    ];
                    $epicData['milestones'][] = $milestoneData;
                }

                // Get tasks directly assigned to epic (not through milestones) in this sprint
                $epicData['tasks'] = $this->getSprintEpicTasks($sprintId, $epic->id);

                $hierarchy[] = $epicData;
            }

            // Get standalone milestones (not part of any epic) that have tasks in this sprint
            $standaloneMilestones = $this->getSprintStandaloneMilestones($sprintId);

            foreach ($standaloneMilestones as $milestone) {
                $milestoneData = [
                    'type' => 'milestone',
                    'data' => $milestone,
                    'tasks' => $this->getSprintMilestoneTasks($sprintId, $milestone->id),
                ];
                $hierarchy[] = $milestoneData;
            }

            // Get tasks not assigned to any milestone or epic in this sprint
            $unassignedTasks = $this->getSprintUnassignedTasks($sprintId);
            if (!empty($unassignedTasks)) {
                $hierarchy[] = [
                    'type' => 'unassigned_tasks',
                    'data' => (object)['title' => 'Unassigned Tasks'],
                    'tasks' => $unassignedTasks,
                ];
            }

            return $hierarchy;
        } catch (\Exception $e) {
            error_log("Failed to get sprint hierarchy: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get epics that have tasks in this sprint
     *
     * @param int $sprintId
     * @return array
     */
    private function getSprintEpics(int $sprintId): array
    {
        try {
            $sql = "SELECT DISTINCT
                m.*,
                s.name AS status_name
            FROM
                milestones m
            LEFT JOIN
                statuses_milestone s ON m.status_id = s.id
            INNER JOIN
                milestone_tasks mt ON m.id = mt.milestone_id
            INNER JOIN
                sprint_tasks st ON mt.task_id = st.task_id
            WHERE
                st.sprint_id = :sprint_id
                AND m.milestone_type = 'epic'
                AND m.is_deleted = 0
            ORDER BY
                m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get sprint epics: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get milestones for an epic that have tasks in this sprint
     *
     * @param int $sprintId
     * @param int $epicId
     * @return array
     */
    private function getSprintEpicMilestones(int $sprintId, int $epicId): array
    {
        try {
            $sql = "SELECT DISTINCT
                m.*,
                s.name AS status_name
            FROM
                milestones m
            LEFT JOIN
                statuses_milestone s ON m.status_id = s.id
            INNER JOIN
                milestone_tasks mt ON m.id = mt.milestone_id
            INNER JOIN
                sprint_tasks st ON mt.task_id = st.task_id
            WHERE
                st.sprint_id = :sprint_id
                AND m.epic_id = :epic_id
                AND m.milestone_type = 'milestone'
                AND m.is_deleted = 0
            ORDER BY
                m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [
                ':sprint_id' => $sprintId,
                ':epic_id' => $epicId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get sprint epic milestones: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get tasks for a milestone in this sprint
     *
     * @param int $sprintId
     * @param int $milestoneId
     * @return array
     */
    private function getSprintMilestoneTasks(int $sprintId, int $milestoneId): array
    {
        try {
            $sql = "SELECT
                t.*,
                ts.name AS status_name,
                u.first_name,
                u.last_name
            FROM
                tasks t
            INNER JOIN
                sprint_tasks st ON t.id = st.task_id
            INNER JOIN
                milestone_tasks mt ON t.id = mt.task_id
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                st.sprint_id = :sprint_id
                AND mt.milestone_id = :milestone_id
                AND t.is_deleted = 0
            ORDER BY
                t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [
                ':sprint_id' => $sprintId,
                ':milestone_id' => $milestoneId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get sprint milestone tasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get tasks directly assigned to an epic (not through milestones) in this sprint
     *
     * @param int $sprintId
     * @param int $epicId
     * @return array
     */
    private function getSprintEpicTasks(int $sprintId, int $epicId): array
    {
        try {
            $sql = "SELECT
                t.*,
                ts.name AS status_name,
                u.first_name,
                u.last_name
            FROM
                tasks t
            INNER JOIN
                sprint_tasks st ON t.id = st.task_id
            INNER JOIN
                milestone_tasks mt ON t.id = mt.task_id
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                st.sprint_id = :sprint_id
                AND mt.milestone_id = :epic_id
                AND t.is_deleted = 0
            ORDER BY
                t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [
                ':sprint_id' => $sprintId,
                ':epic_id' => $epicId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get sprint epic tasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get standalone milestones (not part of any epic) that have tasks in this sprint
     *
     * @param int $sprintId
     * @return array
     */
    private function getSprintStandaloneMilestones(int $sprintId): array
    {
        try {
            $sql = "SELECT DISTINCT
                m.*,
                s.name AS status_name
            FROM
                milestones m
            LEFT JOIN
                statuses_milestone s ON m.status_id = s.id
            INNER JOIN
                milestone_tasks mt ON m.id = mt.milestone_id
            INNER JOIN
                sprint_tasks st ON mt.task_id = st.task_id
            WHERE
                st.sprint_id = :sprint_id
                AND m.milestone_type = 'milestone'
                AND m.epic_id IS NULL
                AND m.is_deleted = 0
            ORDER BY
                m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get sprint standalone milestones: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get tasks not assigned to any milestone or epic in this sprint
     *
     * @param int $sprintId
     * @return array
     */
    private function getSprintUnassignedTasks(int $sprintId): array
    {
        try {
            $sql = "SELECT
                t.*,
                ts.name AS status_name,
                u.first_name,
                u.last_name
            FROM
                tasks t
            INNER JOIN
                sprint_tasks st ON t.id = st.task_id
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                st.sprint_id = :sprint_id
                AND t.is_subtask = 0
                AND t.is_deleted = 0
                AND t.id NOT IN (
                    SELECT mt.task_id
                    FROM milestone_tasks mt
                    INNER JOIN milestones m ON mt.milestone_id = m.id
                    WHERE m.is_deleted = 0
                )
            ORDER BY
                t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get sprint unassigned tasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Add tasks to sprint
     *
     * @param int $sprintId
     * @param array $taskIds
     * @return bool
     */
    public function addTasks(int $sprintId, array $taskIds): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove existing task associations
            $sql = "DELETE FROM sprint_tasks WHERE sprint_id = :sprint_id";
            $this->db->executeInsertUpdate($sql, [':sprint_id' => $sprintId]);

            // Add new task associations
            if (!empty($taskIds)) {
                $values = [];
                $params = [];

                foreach ($taskIds as $index => $taskId) {
                    $values[] = "(:sprint_id, :task_id_{$index})";
                    $params[":task_id_{$index}"] = $taskId;
                }

                $params[':sprint_id'] = $sprintId;

                $sql = "INSERT INTO sprint_tasks (sprint_id, task_id) VALUES " . implode(', ', $values);
                $this->db->executeInsertUpdate($sql, $params);
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw new RuntimeException("Failed to add tasks to sprint: " . $e->getMessage());
        }
    }

    /**
     * Remove task from sprint
     *
     * @param int $sprintId
     * @param int $taskId
     * @return bool
     */
    public function removeTask(int $sprintId, int $taskId): bool
    {
        $sql = "DELETE FROM sprint_tasks WHERE sprint_id = :sprint_id AND task_id = :task_id";

        return $this->db->executeInsertUpdate($sql, [
            ':sprint_id' => $sprintId,
            ':task_id' => $taskId,
        ]);
    }

    /**
     * Get active sprint for a project
     *
     * @param int $projectId
     * @return object|null
     */
    public function getActiveSprintForProject(int $projectId): ?object
    {
        $sql = "SELECT s.*, ss.name as status_name
                FROM sprints s
                LEFT JOIN statuses_sprint ss ON s.status_id = ss.id
                WHERE s.project_id = :project_id 
                AND s.status_id = " . SprintStatus::ACTIVE->value . " -- Active status
                AND s.is_deleted = 0
                LIMIT 1";

        $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);
        $sprint = $stmt->fetch(PDO::FETCH_OBJ);

        return $sprint ?: null;
    }

    /**
     * Get all sprints for a project
     *
     * @param int $projectId
     * @return array
     */
    public function getByProjectId(int $projectId): array
    {
        try {
            $sql = "SELECT s.*,
                        ss.name as status_name,
                        COUNT(st.task_id) as task_count,
                        (
                            SELECT COUNT(t.id)
                            FROM tasks t
                            JOIN sprint_tasks spt ON t.id = spt.task_id
                            WHERE spt.sprint_id = s.id
                            AND t.status_id = " . TaskStatus::COMPLETED->value . "
                            AND t.is_deleted = 0
                        ) as completed_tasks
                    FROM sprints s
                    LEFT JOIN statuses_sprint ss ON s.status_id = ss.id
                    LEFT JOIN sprint_tasks st ON s.id = st.sprint_id
                    WHERE s.project_id = :project_id
                    AND s.is_deleted = 0
                    GROUP BY s.id
                    ORDER BY s.start_date DESC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching sprints for project: " . $e->getMessage());
        }
    }

    /**
     * Get active sprints for a user
     *
     * @param int $userId
     * @param string|null $status Filter by status name (e.g., 'active')
     * @return array
     */
    public function getProjectSprints(int $userId, ?string $status = null): array
    {
        try {
            $sql = "SELECT s.*, 
               ss.name as status_name,
               p.name as project_name,
               (
                   SELECT COUNT(st.task_id) 
                   FROM sprint_tasks st 
                   JOIN tasks t ON st.task_id = t.id
                   WHERE st.sprint_id = s.id
                   AND t.is_deleted = 0
               ) as total_tasks,
               (
                   SELECT COUNT(t.id) 
                   FROM tasks t 
                   JOIN sprint_tasks st ON t.id = st.task_id 
                   WHERE st.sprint_id = s.id 
                   AND t.status_id = " . TaskStatus::COMPLETED->value . " 
                   AND t.is_deleted = 0
               ) as completed_tasks
        FROM sprints s
        JOIN projects p ON s.project_id = p.id
        JOIN statuses_sprint ss ON s.status_id = ss.id
        WHERE p.owner_id = :user_id 
        AND s.is_deleted = 0";

            $params = [':user_id' => $userId];

            if ($status) {
                $sql .= " AND ss.name = :status";
                $params[':status'] = $status;
            }

            $sql .= " ORDER BY s.start_date DESC";

            $stmt = $this->db->executeQuery($sql, $params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get user sprints: " . $e->getMessage());
        }
    }

    /**
     * Get sprint velocity (total completed tasks vs total tasks)
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintVelocity(int $sprintId): array
    {
        $sql = "SELECT 
                    COUNT(t.id) as total_tasks,
                    SUM(CASE WHEN t.status_id = " . TaskStatus::COMPLETED->value . " THEN 1 ELSE 0 END) as completed_tasks
                FROM tasks t
                JOIN sprint_tasks st ON t.id = st.task_id
                WHERE st.sprint_id = :sprint_id AND t.is_deleted = 0";

        $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalTasks = (int)($result['total_tasks'] ?? 0);
        $completedTasks = (int)($result['completed_tasks'] ?? 0);

        $velocityPercentage = $totalTasks > 0 ?
            round(($completedTasks / $totalTasks) * 100, 2) : 0;

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'velocity_percentage' => $velocityPercentage,
        ];
    }

    /**
     * Start a sprint (change status to active)
     *
     * @param int $sprintId
     * @return bool
     */
    public function startSprint(int $sprintId): bool
    {
        // Check if another sprint is already active for this project
        $sprint = $this->find($sprintId);
        if (!$sprint) {
            throw new RuntimeException("Sprint not found");
        }

        $activeSprintExists = $this->getActiveSprintForProject($sprint->project_id);
        if ($activeSprintExists && $activeSprintExists->id != $sprintId) {
            throw new RuntimeException("Another sprint is already active for this project");
        }

        // Update status to active (2)
        return $this->update($sprintId, ['status_id' => 2]);
    }

    /**
     * Complete a sprint
     *
     * @param int $sprintId
     * @return bool
     */
    public function completeSprint(int $sprintId): bool
    {
        return $this->update($sprintId, ['status_id' => 3]);  // Completed status
    }

    /**
     * Get the sprint a task is assigned to (if any)
     *
     * @param int $taskId
     * @return object|null
     */
    public function getTaskSprint(int $taskId): ?object
    {
        try {
            $sql = "SELECT s.*, st.created_at as assigned_at
                    FROM sprints s
                    JOIN sprint_tasks st ON s.id = st.sprint_id
                    WHERE st.task_id = :task_id
                    AND s.is_deleted = 0
                    AND s.status_id IN (1, 2)"; // Planning or Active status

            $stmt = $this->db->executeQuery($sql, [':task_id' => $taskId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            return $result ?: null;
        } catch (\Exception $e) {
            throw new RuntimeException("Error getting task sprint: " . $e->getMessage());
        }
    }

    /**
     * Assign a single task to a sprint with optional subtask inheritance
     *
     * @param int $sprintId
     * @param int $taskId
     * @param bool $includeSubtasks Whether to automatically include subtasks
     * @return bool
     */
    public function assignTask(int $sprintId, int $taskId, bool $includeSubtasks = true): bool
    {
        try {
            $this->db->beginTransaction();

            // Check if task is already assigned to this sprint
            $sql = "SELECT COUNT(*) FROM sprint_tasks WHERE sprint_id = :sprint_id AND task_id = :task_id";
            $stmt = $this->db->executeQuery($sql, [
                ':sprint_id' => $sprintId,
                ':task_id' => $taskId,
            ]);

            if ($stmt->fetchColumn() > 0) {
                $this->db->commit();

                return true; // Already assigned
            }

            // Remove task from any other active sprints first
            $this->removeTaskFromActiveSprints($taskId);

            // Assign main task to the new sprint
            $sql = "INSERT INTO sprint_tasks (sprint_id, task_id) VALUES (:sprint_id, :task_id)";
            $result = $this->db->executeInsertUpdate($sql, [
                ':sprint_id' => $sprintId,
                ':task_id' => $taskId,
            ]);

            if (!$result) {
                $this->db->rollBack();

                return false;
            }

            // If includeSubtasks is true, also assign all subtasks
            if ($includeSubtasks) {
                $this->assignSubtasksToSprint($sprintId, $taskId);
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw new RuntimeException("Error assigning task to sprint: " . $e->getMessage());
        }
    }

    /**
     * Assign all subtasks of a parent task to sprint
     *
     * @param int $sprintId
     * @param int $parentTaskId
     * @return bool
     */
    public function assignSubtasksToSprint(int $sprintId, int $parentTaskId): bool
    {
        try {
            // Get all subtasks of the parent task
            $sql = "SELECT id FROM tasks
                    WHERE parent_task_id = :parent_task_id
                    AND is_subtask = 1
                    AND is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':parent_task_id' => $parentTaskId]);
            $subtasks = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($subtasks as $subtask) {
                // Remove subtask from any other active sprints
                $this->removeTaskFromActiveSprints($subtask->id);

                // Assign subtask to the sprint
                $sql = "INSERT IGNORE INTO sprint_tasks (sprint_id, task_id) VALUES (:sprint_id, :task_id)";
                $this->db->executeInsertUpdate($sql, [
                    ':sprint_id' => $sprintId,
                    ':task_id' => $subtask->id,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error assigning subtasks to sprint: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Remove task from any active sprints
     *
     * @param int $taskId
     * @return bool
     */
    private function removeTaskFromActiveSprints(int $taskId): bool
    {
        try {
            $sql = "DELETE st FROM sprint_tasks st
                    JOIN sprints s ON st.sprint_id = s.id
                    WHERE st.task_id = :task_id
                    AND s.status_id IN (1, 2)
                    AND s.is_deleted = 0";

            return $this->db->executeInsertUpdate($sql, [':task_id' => $taskId]);
        } catch (\Exception $e) {
            throw new RuntimeException("Error removing task from active sprints: " . $e->getMessage());
        }
    }

    /**
     * Remove task and its subtasks from sprint
     *
     * @param int $sprintId
     * @param int $taskId
     * @param bool $includeSubtasks Whether to also remove subtasks
     * @return bool
     */
    public function removeTaskFromSprint(int $sprintId, int $taskId, bool $includeSubtasks = true): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove main task from sprint
            $sql = "DELETE FROM sprint_tasks WHERE sprint_id = :sprint_id AND task_id = :task_id";
            $result = $this->db->executeInsertUpdate($sql, [
                ':sprint_id' => $sprintId,
                ':task_id' => $taskId,
            ]);

            // If includeSubtasks is true, also remove all subtasks
            if ($includeSubtasks) {
                $this->removeSubtasksFromSprint($sprintId, $taskId);
            }

            $this->db->commit();

            return $result;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error removing task from sprint: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Remove all subtasks of a parent task from sprint
     *
     * @param int $sprintId
     * @param int $parentTaskId
     * @return bool
     */
    public function removeSubtasksFromSprint(int $sprintId, int $parentTaskId): bool
    {
        try {
            $sql = "DELETE st FROM sprint_tasks st
                    JOIN tasks t ON st.task_id = t.id
                    WHERE st.sprint_id = :sprint_id
                    AND t.parent_task_id = :parent_task_id
                    AND t.is_subtask = 1
                    AND t.is_deleted = 0";

            return $this->db->executeInsertUpdate($sql, [
                ':sprint_id' => $sprintId,
                ':parent_task_id' => $parentTaskId,
            ]);
        } catch (\Exception $e) {
            error_log("Error removing subtasks from sprint: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Get tasks with subtask information for sprint
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintTasksWithSubtasks(int $sprintId): array
    {
        try {
            $sql = "SELECT t.*,
                        ts.name as status_name,
                        u.first_name,
                        u.last_name,
                        CASE WHEN t.is_subtask = 1 THEN pt.title ELSE NULL END as parent_title,
                        (
                            SELECT COUNT(*)
                            FROM tasks st
                            WHERE st.parent_task_id = t.id
                            AND st.is_subtask = 1
                            AND st.is_deleted = 0
                        ) as subtask_count,
                        (
                            SELECT COUNT(*)
                            FROM tasks st
                            JOIN sprint_tasks sst ON st.id = sst.task_id
                            WHERE st.parent_task_id = t.id
                            AND st.is_subtask = 1
                            AND st.is_deleted = 0
                            AND sst.sprint_id = :sprint_id
                        ) as subtasks_in_sprint
                    FROM tasks t
                    JOIN sprint_tasks st ON t.id = st.task_id
                    LEFT JOIN statuses_task ts ON t.status_id = ts.id
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN tasks pt ON t.parent_task_id = pt.id
                    WHERE st.sprint_id = :sprint_id
                    AND t.is_deleted = 0
                    ORDER BY t.is_subtask ASC, t.parent_task_id ASC, t.title ASC";

            $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error getting sprint tasks with subtasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get active sprints where user has tasks assigned
     *
     * @param int $userId
     * @return array
     */
    public function getActiveSprintsForUser(int $userId): array
    {
        try {
            $sql = "SELECT DISTINCT s.*,
                        p.name as project_name,
                        ss.name as status_name
                    FROM sprints s
                    JOIN projects p ON s.project_id = p.id
                    JOIN statuses_sprint ss ON s.status_id = ss.id
                    JOIN sprint_tasks st ON s.id = st.sprint_id
                    JOIN tasks t ON st.task_id = t.id
                    WHERE t.assigned_to = :user_id
                    AND s.status_id = " . SprintStatus::ACTIVE->value . " -- Active status
                    AND s.is_deleted = 0
                    AND t.is_deleted = 0
                    ORDER BY s.start_date ASC";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $result ?: [];
        } catch (\Exception $e) {
            error_log("Error getting active sprints for user: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get active sprints in projects user has access to
     *
     * @param int $userId
     * @return array
     */
    public function getActiveSprintsInUserProjects(int $userId): array
    {
        try {
            $sql = "SELECT s.*,
                        p.name as project_name,
                        ss.name as status_name
                    FROM sprints s
                    JOIN projects p ON s.project_id = p.id
                    JOIN statuses_sprint ss ON s.status_id = ss.id
                    WHERE p.owner_id = :user_id
                    AND s.status_id = " . SprintStatus::ACTIVE->value . " -- Active status
                    AND s.is_deleted = 0
                    AND p.is_deleted = 0
                    ORDER BY s.start_date ASC";

            $stmt = $this->db->executeQuery($sql, [':user_id' => $userId]);
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $result ?: [];
        } catch (\Exception $e) {
            error_log("Error getting active sprints in user projects: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Add milestones to sprint
     *
     * @param int $sprintId
     * @param array $milestoneIds
     * @return bool
     */
    public function addMilestones(int $sprintId, array $milestoneIds): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove existing milestone associations
            $sql = "DELETE FROM sprint_milestones WHERE sprint_id = :sprint_id";
            $this->db->executeInsertUpdate($sql, [':sprint_id' => $sprintId]);

            // Add new milestone associations
            if (!empty($milestoneIds)) {
                $values = [];
                $params = [];

                foreach ($milestoneIds as $index => $milestoneId) {
                    $values[] = "(:sprint_id, :milestone_id_{$index})";
                    $params[":milestone_id_{$index}"] = $milestoneId;
                }

                $params[':sprint_id'] = $sprintId;

                $sql = "INSERT INTO sprint_milestones (sprint_id, milestone_id) VALUES " . implode(', ', $values);
                $this->db->executeInsertUpdate($sql, $params);
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error adding milestones to sprint: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Get milestones associated with a sprint
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintMilestones(int $sprintId): array
    {
        try {
            $sql = "SELECT m.*,
                        ms.name as status_name,
                        sm.created_at as assigned_at
                    FROM milestones m
                    JOIN sprint_milestones sm ON m.id = sm.milestone_id
                    LEFT JOIN statuses_milestone ms ON m.status_id = ms.id
                    WHERE sm.sprint_id = :sprint_id
                    AND m.is_deleted = 0
                    ORDER BY m.milestone_type DESC, m.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error getting sprint milestones: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get sprints associated with a milestone
     *
     * @param int $milestoneId
     * @return array
     */
    public function getSprintsByMilestone(int $milestoneId): array
    {
        try {
            $sql = "SELECT s.*,
                        ss.name as status_name,
                        p.name as project_name,
                        sm.created_at as assigned_at
                    FROM sprints s
                    JOIN sprint_milestones sm ON s.id = sm.sprint_id
                    LEFT JOIN statuses_sprint ss ON s.status_id = ss.id
                    LEFT JOIN projects p ON s.project_id = p.id
                    WHERE sm.milestone_id = :milestone_id
                    AND s.is_deleted = 0
                    ORDER BY s.start_date DESC";

            $stmt = $this->db->executeQuery($sql, [':milestone_id' => $milestoneId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error getting sprints by milestone: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get sprint relationship type and related entities
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintRelationships(int $sprintId): array
    {
        try {
            $relationships = [
                'type' => self::RELATIONSHIP_PROJECT,
                'project' => null,
                'milestones' => [],
                'epics' => [],
            ];

            // Get project information
            $sprint = $this->find($sprintId);
            if ($sprint) {
                $projectModel = new \App\Models\Project();
                $relationships['project'] = $projectModel->find($sprint->project_id);
            }

            // Get associated milestones
            $milestones = $this->getSprintMilestones($sprintId);
            $relationships['milestones'] = array_filter($milestones, function ($m) {
                return $m->milestone_type === 'milestone';
            });

            $relationships['epics'] = array_filter($milestones, function ($m) {
                return $m->milestone_type === 'epic';
            });

            // Determine relationship type
            if (!empty($relationships['epics'])) {
                $relationships['type'] = self::RELATIONSHIP_EPIC;
            } elseif (!empty($relationships['milestones'])) {
                $relationships['type'] = self::RELATIONSHIP_MILESTONE;
            }

            return $relationships;
        } catch (\Exception $e) {
            error_log("Error getting sprint relationships: " . $e->getMessage());

            return [
                'type' => self::RELATIONSHIP_PROJECT,
                'project' => null,
                'milestones' => [],
                'epics' => [],
            ];
        }
    }

    /**
     * Get available tasks for sprint planning based on milestones
     *
     * @param array $milestoneIds
     * @return array
     */
    public function getTasksFromMilestones(array $milestoneIds): array
    {
        if (empty($milestoneIds)) {
            return [];
        }

        try {
            $placeholders = str_repeat('?,', count($milestoneIds) - 1) . '?';
            $sql = "SELECT DISTINCT t.*,
                        ts.name as status_name,
                        u.first_name,
                        u.last_name,
                        m.title as milestone_title,
                        m.milestone_type
                    FROM tasks t
                    JOIN milestone_tasks mt ON t.id = mt.task_id
                    JOIN milestones m ON mt.milestone_id = m.id
                    LEFT JOIN statuses_task ts ON t.status_id = ts.id
                    LEFT JOIN users u ON t.assigned_to = u.id
                    WHERE mt.milestone_id IN ($placeholders)
                    AND t.is_deleted = 0
                    AND m.is_deleted = 0
                    AND t.is_ready_for_sprint = 1
                    AND t.id NOT IN (
                        SELECT st.task_id FROM sprint_tasks st
                        JOIN sprints s ON st.sprint_id = s.id
                        WHERE s.status_id IN (1, 2) AND s.is_deleted = 0
                    )
                    ORDER BY
                        m.milestone_type DESC,
                        CASE
                            WHEN t.priority = 'high' THEN 1
                            WHEN t.priority = 'medium' THEN 2
                            WHEN t.priority = 'low' THEN 3
                            ELSE 4
                        END,
                        t.backlog_priority ASC,
                        t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, $milestoneIds);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error getting tasks from milestones: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Create sprint from milestone(s)
     *
     * @param array $data Sprint data
     * @param array $milestoneIds Milestone IDs to associate
     * @param array $taskIds Task IDs to include
     * @return int Sprint ID
     */
    public function createFromMilestones(array $data, array $milestoneIds = [], array $taskIds = []): int
    {
        try {
            $this->db->beginTransaction();

            // Create the sprint
            $sprintId = $this->create($data);

            // Associate milestones if provided
            if (!empty($milestoneIds)) {
                $this->addMilestones($sprintId, $milestoneIds);
            }

            // Associate tasks if provided
            if (!empty($taskIds)) {
                $this->addTasks($sprintId, $taskIds);
            }

            $this->db->commit();

            return $sprintId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error creating sprint from milestones: " . $e->getMessage());

            throw new RuntimeException("Failed to create sprint from milestones: " . $e->getMessage());
        }
    }

    /**
     * Get sprint capacity based on associated milestones and tasks
     *
     * @param int $sprintId
     * @return array
     */
    public function getSprintCapacityBreakdown(int $sprintId): array
    {
        try {
            $breakdown = [
                'total_story_points' => 0,
                'total_estimated_hours' => 0,
                'by_milestone' => [],
                'by_epic' => [],
                'direct_tasks' => [],
            ];

            // Get sprint tasks with milestone associations
            $sql = "SELECT t.*,
                        COALESCE(t.story_points, 0) as story_points,
                        COALESCE(t.estimated_time, 0) as estimated_time,
                        m.id as milestone_id,
                        m.title as milestone_title,
                        m.milestone_type
                    FROM tasks t
                    JOIN sprint_tasks st ON t.id = st.task_id
                    LEFT JOIN milestone_tasks mt ON t.id = mt.task_id
                    LEFT JOIN milestones m ON mt.milestone_id = m.id
                    WHERE st.sprint_id = :sprint_id
                    AND t.is_deleted = 0
                    ORDER BY m.milestone_type DESC, m.title ASC, t.title ASC";

            $stmt = $this->db->executeQuery($sql, [':sprint_id' => $sprintId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($tasks as $task) {
                $breakdown['total_story_points'] += $task->story_points;
                $breakdown['total_estimated_hours'] += $task->estimated_time / 3600; // Convert to hours

                if ($task->milestone_id) {
                    $key = $task->milestone_type === 'epic' ? 'by_epic' : 'by_milestone';
                    $milestoneKey = $task->milestone_id;

                    if (!isset($breakdown[$key][$milestoneKey])) {
                        $breakdown[$key][$milestoneKey] = [
                            'title' => $task->milestone_title,
                            'story_points' => 0,
                            'estimated_hours' => 0,
                            'task_count' => 0,
                        ];
                    }

                    $breakdown[$key][$milestoneKey]['story_points'] += $task->story_points;
                    $breakdown[$key][$milestoneKey]['estimated_hours'] += $task->estimated_time / 3600;
                    $breakdown[$key][$milestoneKey]['task_count']++;
                } else {
                    $breakdown['direct_tasks'][] = [
                        'id' => $task->id,
                        'title' => $task->title,
                        'story_points' => $task->story_points,
                        'estimated_hours' => $task->estimated_time / 3600,
                    ];
                }
            }

            return $breakdown;
        } catch (\Exception $e) {
            error_log("Error getting sprint capacity breakdown: " . $e->getMessage());

            return [
                'total_story_points' => 0,
                'total_estimated_hours' => 0,
                'by_milestone' => [],
                'by_epic' => [],
                'direct_tasks' => [],
            ];
        }
    }
}
