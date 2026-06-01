<?php

// file: Models/Milestone.php
declare(strict_types=1);

namespace App\Models;

use App\Enums\MilestoneType;
use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\Searchable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Milestone Model
 *
 * Handles all milestone-related database operations
 */
class Milestone extends BaseModel
{
    use Searchable;

    protected string $table = 'milestones';

    /**
     * Milestone properties
     */
    public ?int $id = null;
    public string $title;
    public ?string $description = null;
    public string $milestone_type = MilestoneType::MILESTONE->value;
    public ?string $start_date = null;
    public ?string $due_date = null;
    public ?string $complete_date = null;
    public ?int $epic_id = null;
    public int $project_id;
    public int $status_id;
    public bool $is_deleted = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'title',
        'description',
        'milestone_type',
        'start_date',
        'due_date',
        'complete_date',
        'epic_id',
        'project_id',
        'status_id',
    ];

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'title',
        'description',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'title' => ['required', 'string'],
        'project_id' => ['required'],
        'status_id' => ['required'],
        'milestone_type' => ['required', 'enum:App\Enums\MilestoneType'],
    ];

    /**
     * Find milestone with detailed information
     * Uses selective loading to prevent N+1 queries
     *
     * @param int $id
     * @param array $options Selective loading options:
     *   - tasks: bool (default true) - Load milestone tasks
     *   - related_milestones: bool (default true) - Load related milestones for epics
     *   - related_sprints: bool (default false) - Load related sprints
     * @return object|null
     */
    public function findWithDetails(int $id, array $options = []): ?object
    {
        try {
            // Default options - load common data unless specified otherwise
            $defaultOptions = [
                'tasks' => true,
                'related_milestones' => true,
                'related_sprints' => false,
            ];

            $options = array_merge($defaultOptions, $options);

            $sql = "SELECT
            m.*,
            s.name AS status_name,
            p.name AS project_name
        FROM milestones m
        LEFT JOIN statuses_milestone s ON m.status_id = s.id
        LEFT JOIN projects p ON m.project_id = p.id
        WHERE m.id = :id AND m.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':id' => $id]);
            $milestone = $stmt->fetch(PDO::FETCH_OBJ);

            if ($milestone) {
                // Selectively load related data based on options
                if ($options['tasks']) {
                    $milestone->tasks = $this->getTasks($id);
                }

                // If epic, get related milestones
                if ($options['related_milestones'] && $milestone->milestone_type === 'epic') {
                    $milestone->related_milestones = $this->getEpicMilestones($id);
                }

                // Optionally load related sprints
                if ($options['related_sprints']) {
                    $milestone->related_sprints = $this->getRelatedSprints($id);
                }

                // Ensure status_name is set
                $milestone->status_name = $milestone->status_name ?? 'Unknown';
            }

            return $milestone ?: null;
        } catch (\Exception $e) {
            try {
                $securityService = \App\Services\SecurityService::getInstance();
                $safeMessage = $securityService->getSafeErrorMessage($e->getMessage(), "Failed to find milestone details");

                throw new \RuntimeException($safeMessage);
            } catch (\Exception $securityException) {
                throw new \RuntimeException("Failed to find milestone details");
            }
        }
    }

    /**
     * Find milestone with basic information only (no related data)
     *
     * @param int $id
     * @return object|null
     */
    public function findBasic(int $id): ?object
    {
        return $this->findWithDetails($id, [
            'tasks' => false,
            'related_milestones' => false,
            'related_sprints' => false,
        ]);
    }

    // Modify the find method similarly
    public function find(int $id): object|false
    {
        try {
            $sql = "SELECT 
            m.*,
            s.name AS status_name
        FROM milestones m
        LEFT JOIN statuses_milestone s ON m.status_id = s.id
        WHERE m.id = :id AND m.is_deleted = 0";

            $stmt = $this->db->executeQuery($sql, [':id' => $id]);
            $milestone = $stmt->fetch(PDO::FETCH_OBJ);

            // Ensure status_name is set
            if ($milestone) {
                $milestone->status_name = $milestone->status_name ?? 'Unknown';
            }

            return $milestone ?: false;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to find milestone: " . $e->getMessage());
        }
    }

    /**
     * Get milestones with completion rates and time remaining
     *
     * @param int   $limit      Items per page
     * @param int   $page       Current page number
     * @param array $conditions Optional conditions to filter by
     * @return array
     */
    public function getAllWithProgress(int $limit = 10, int $page = 1, array $conditions = []): array
    {
        $limit = (int) $limit; // ensure integer
        $page = (int) $page;  // ensure integer
        $offset = ($page - 1) * $limit;

        // Start with a default clause to exclude deleted milestones
        $whereClauses = ['m.is_deleted = 0'];
        $params = [];

        // Process additional conditions
        foreach ($conditions as $column => $value) {
            // Skip anything that doesn't match your allowed column format
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                continue;
            }

            // Process complex conditions (operators)
            if (is_array($value)) {
                $operator = key($value);
                $condValue = $value[$operator];

                switch ($operator) {
                    case '>':
                        $whereClauses[] = "m.{$column} > :{$column}";
                        $params[":{$column}"] = $condValue;

                        break;
                    case '<':
                        $whereClauses[] = "m.{$column} < :{$column}";
                        $params[":{$column}"] = $condValue;

                        break;
                    default:
                        // Fallback to '=' if the operator isn't recognized
                        $whereClauses[] = "m.{$column} = :{$column}";
                        $params[":{$column}"] = $condValue;
                }
            } else {
                // Simple equality
                $whereClauses[] = "m.{$column} = :{$column}";
                $params[":{$column}"] = $value;
            }
        }

        // If for some reason the array is empty (which won’t happen in this code
        // because of "m.is_deleted = 0"), you can default to 1=1
        if (empty($whereClauses)) {
            $whereClause = '1=1';
        } else {
            $whereClause = implode(' AND ', $whereClauses);
        }

        $sql = "SELECT
                m.id,
                m.title,
                m.start_date,
                m.due_date,
                m.complete_date,
                m.status_id,
                m.project_id,
                m.milestone_type,
                m.epic_id,
                p.name AS project_name,
                s.name AS status_name,
                CASE
                    WHEN m.complete_date IS NOT NULL THEN 100
                    WHEN m.status_id = " . MilestoneStatus::COMPLETED->value . " THEN 100
                    ELSE COALESCE(
                        (
                            SELECT
                                CASE
                                    WHEN COUNT(t.id) = 0 THEN 0
                                    ELSE ROUND((SUM(CASE WHEN t.status_id = " . TaskStatus::COMPLETED->value . " THEN 1 ELSE 0 END) * 100.0) / COUNT(t.id), 1)
                                END
                            FROM milestone_tasks mt
                            JOIN tasks t ON mt.task_id = t.id
                            WHERE mt.milestone_id = m.id
                            AND t.is_deleted = 0
                        ), 0
                    )
                END AS completion_rate,
                CASE
                    WHEN m.due_date IS NULL THEN NULL
                    ELSE DATEDIFF(m.due_date, CURDATE())
                END AS time_remaining,
                (
                    SELECT COUNT(*)
                    FROM milestone_tasks mt
                    JOIN tasks t ON mt.task_id = t.id
                    WHERE mt.milestone_id = m.id
                      AND t.is_deleted = 0
                ) AS task_count
            FROM milestones m
            LEFT JOIN projects p ON m.project_id = p.id
            LEFT JOIN statuses_milestone s ON m.status_id = s.id
            WHERE {$whereClause}
            ORDER BY m.due_date ASC, m.title ASC
            LIMIT :limit OFFSET :offset";

        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get all milestones for a project
     *
     * @param int $projectId
     * @return array
     */
    public function getByProjectId(int $projectId): array
    {
        try {
            $sql = "SELECT
                    m.id,
                    m.title,
                    m.start_date,
                    m.due_date,
                    m.complete_date,
                    m.status_id,
                    m.project_id,
                    m.milestone_type,
                    m.epic_id,
                    p.name AS project_name,
                    s.name AS status_name,
                    CASE
                        WHEN m.complete_date IS NOT NULL THEN 100
                        WHEN m.status_id = " . MilestoneStatus::COMPLETED->value . " THEN 100
                        ELSE COALESCE(
                            (
                                SELECT
                                    CASE
                                        WHEN COUNT(t.id) = 0 THEN 0
                                        ELSE ROUND((SUM(CASE WHEN t.status_id = " . TaskStatus::COMPLETED->value . " THEN 1 ELSE 0 END) * 100.0) / COUNT(t.id), 1)
                                    END
                                FROM milestone_tasks mt
                                JOIN tasks t ON mt.task_id = t.id
                                WHERE mt.milestone_id = m.id
                                AND t.is_deleted = 0
                            ), 0
                        )
                    END AS completion_rate,
                    CASE
                        WHEN m.due_date IS NULL THEN NULL
                        ELSE DATEDIFF(m.due_date, CURDATE())
                    END AS time_remaining,
                    (
                        SELECT COUNT(*)
                        FROM milestone_tasks mt
                        JOIN tasks t ON mt.task_id = t.id
                        WHERE mt.milestone_id = m.id
                          AND t.is_deleted = 0
                    ) AS task_count
                FROM milestones m
                LEFT JOIN projects p ON m.project_id = p.id
                LEFT JOIN statuses_milestone s ON m.status_id = s.id
                WHERE m.project_id = :project_id
                AND m.is_deleted = 0
                ORDER BY m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching milestones for project: " . $e->getMessage());
        }
    }

    /**
     * Get milestone statuses
     *
     * @return array
     */
    public function getMilestoneStatuses(): array
    {
        $sql = "SELECT * FROM statuses_milestone WHERE is_deleted = 0";
        $stmt = $this->db->executeQuery($sql);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get epics for project
     *
     * @param int $projectId
     * @return array
     */
    public function getProjectEpics(int $projectId): array
    {
        $sql = "SELECT m.*, s.name as status_name 
                FROM milestones m
                JOIN statuses_milestone s ON m.status_id = s.id
                WHERE m.project_id = :project_id 
                AND m.milestone_type = 'epic' 
                AND m.is_deleted = 0";

        $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get milestones for epic
     *
     * @param int $epicId
     * @return array
     */
    public function getEpicMilestones(int $epicId): array
    {
        $sql = "SELECT m.*, s.name as status_name
                FROM milestones m
                JOIN statuses_milestone s ON m.status_id = s.id
                WHERE m.epic_id = :epic_id 
                AND m.milestone_type = 'milestone' 
                AND m.is_deleted = 0";

        $stmt = $this->db->executeQuery($sql, [':epic_id' => $epicId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get tasks associated with a milestone
     *
     * @param int $milestoneId
     * @return array
     */
    public function getTasks(int $milestoneId): array
    {
        $sql = "SELECT t.*, ts.name as status_name, u.first_name, u.last_name
                FROM tasks t
                JOIN milestone_tasks mt ON t.id = mt.task_id
                JOIN statuses_task ts ON t.status_id = ts.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE mt.milestone_id = :milestone_id
                AND t.is_deleted = 0";

        $stmt = $this->db->executeQuery($sql, [':milestone_id' => $milestoneId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get sprints related to a milestone
     * This includes sprints that share tasks with the milestone or are in the same project
     *
     * @param int $milestoneId
     * @return array
     */
    public function getRelatedSprints(int $milestoneId): array
    {
        try {
            // First get the milestone's project_id
            $milestoneQuery = "SELECT project_id FROM milestones WHERE id = :milestone_id AND is_deleted = 0";
            $milestoneStmt = $this->db->executeQuery($milestoneQuery, [':milestone_id' => $milestoneId]);
            $milestone = $milestoneStmt->fetch(PDO::FETCH_OBJ);

            if (!$milestone) {
                return [];
            }

            // Get sprints in the same project with basic info
            $sql = "SELECT DISTINCT s.*,
                        ss.name as status_name,
                        COALESCE(shared_count.shared_tasks, 0) as shared_tasks,
                        COALESCE(total_count.total_sprint_tasks, 0) as total_sprint_tasks,
                        COALESCE(completed_count.completed_tasks, 0) as completed_tasks
                    FROM sprints s
                    LEFT JOIN statuses_sprint ss ON s.status_id = ss.id
                    LEFT JOIN (
                        SELECT st.sprint_id, COUNT(DISTINCT st.task_id) as shared_tasks
                        FROM sprint_tasks st
                        INNER JOIN milestone_tasks mt ON st.task_id = mt.task_id
                        WHERE mt.milestone_id = :milestone_id
                        GROUP BY st.sprint_id
                    ) shared_count ON s.id = shared_count.sprint_id
                    LEFT JOIN (
                        SELECT st.sprint_id, COUNT(DISTINCT st.task_id) as total_sprint_tasks
                        FROM sprint_tasks st
                        INNER JOIN tasks t ON st.task_id = t.id
                        WHERE t.is_deleted = 0
                        GROUP BY st.sprint_id
                    ) total_count ON s.id = total_count.sprint_id
                    LEFT JOIN (
                        SELECT st.sprint_id, COUNT(DISTINCT t.id) as completed_tasks
                        FROM sprint_tasks st
                        INNER JOIN tasks t ON st.task_id = t.id
                        WHERE t.status_id = " . TaskStatus::COMPLETED->value . " AND t.is_deleted = 0
                        GROUP BY st.sprint_id
                    ) completed_count ON s.id = completed_count.sprint_id
                    WHERE s.is_deleted = 0
                    AND s.project_id = :project_id
                    ORDER BY shared_tasks DESC, s.start_date DESC";

            $stmt = $this->db->executeQuery($sql, [
                ':milestone_id' => $milestoneId,
                ':project_id' => $milestone->project_id,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error in getRelatedSprints: " . $e->getMessage());

            return []; // Return empty array on error to not break the page
        }
    }

    /**
     * Add tasks to milestone
     *
     * @param int $milestoneId
     * @param array $taskIds
     * @return bool
     */
    public function addTasks(int $milestoneId, array $taskIds): bool
    {
        try {
            $this->db->beginTransaction();

            // First remove existing associations to ensure clean state
            $sql = "DELETE FROM milestone_tasks WHERE milestone_id = :milestone_id";
            $this->db->executeInsertUpdate($sql, [':milestone_id' => $milestoneId]);

            // Then add new associations
            foreach ($taskIds as $taskId) {
                $sql = "INSERT INTO milestone_tasks (milestone_id, task_id) 
                        VALUES (:milestone_id, :task_id)";
                $this->db->executeInsertUpdate($sql, [
                    ':milestone_id' => $milestoneId,
                    ':task_id' => $taskId,
                ]);
            }

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw new \RuntimeException("Failed to add tasks to milestone: " . $e->getMessage());
        }
    }

    /**
     * Calculate milestone progress based on completed tasks
     *
     * @param int $milestoneId
     * @return float Percentage of completion (0-100)
     */
    public function calculateTaskProgress(int $milestoneId): float
    {
        $sql = "SELECT 
                    COUNT(t.id) as total_tasks,
                    SUM(CASE WHEN t.status_id = " . TaskStatus::COMPLETED->value . " THEN 1 ELSE 0 END) as completed_tasks
                FROM tasks t
                JOIN milestone_tasks mt ON t.id = mt.task_id
                WHERE mt.milestone_id = :milestone_id
                AND t.is_deleted = 0";

        $stmt = $this->db->executeQuery($sql, [':milestone_id' => $milestoneId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$result || $result->total_tasks == 0) {
            return 0;
        }

        return ($result->completed_tasks / $result->total_tasks) * 100;
    }

    /**
     * Check for circular epic references
     *
     * @param int $currentId
     * @param int $newParentId
     * @throws InvalidArgumentException
     */
    public function checkCircularEpicReference(int $currentId, int $newParentId): void
    {
        // Check if any child milestones of current milestone have the new parent as a child
        $sql = "WITH RECURSIVE milestone_hierarchy AS (
                    SELECT id, epic_id FROM milestones WHERE id = :start_id
                    UNION ALL
                    SELECT m.id, m.epic_id 
                    FROM milestones m
                    JOIN milestone_hierarchy mh ON m.epic_id = mh.id
                )
                SELECT COUNT(*) FROM milestone_hierarchy WHERE id = :check_id";

        $stmt = $this->db->executeQuery($sql, [
            ':start_id' => $newParentId,
            ':check_id' => $currentId,
        ]);

        if ($stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Circular epic reference detected');
        }
    }

    /**
     * Get sprints directly associated with this milestone
     *
     * @param int $milestoneId
     * @return array
     */
    public function getAssociatedSprints(int $milestoneId): array
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
            error_log("Error getting associated sprints: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get available milestones for sprint planning
     *
     * @param int $projectId
     * @param string $type 'epic', 'milestone', or 'all'
     * @return array
     */
    public function getAvailableForSprint(int $projectId, string $type = 'all'): array
    {
        try {
            $sql = "SELECT m.*,
                        ms.name as status_name,
                        (
                            SELECT COUNT(mt.task_id)
                            FROM milestone_tasks mt
                            JOIN tasks t ON mt.task_id = t.id
                            WHERE mt.milestone_id = m.id
                            AND t.is_deleted = 0
                            AND t.is_ready_for_sprint = 1
                        ) as ready_task_count,
                        (
                            SELECT COUNT(mt.task_id)
                            FROM milestone_tasks mt
                            JOIN tasks t ON mt.task_id = t.id
                            WHERE mt.milestone_id = m.id
                            AND t.is_deleted = 0
                        ) as total_task_count
                    FROM milestones m
                    LEFT JOIN statuses_milestone ms ON m.status_id = ms.id
                    WHERE m.project_id = :project_id
                    AND m.is_deleted = 0";

            $params = [':project_id' => $projectId];

            if ($type !== 'all') {
                $sql .= " AND m.milestone_type = :type";
                $params[':type'] = $type;
            }

            $sql .= " ORDER BY m.milestone_type DESC, m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, $params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error getting available milestones for sprint: " . $e->getMessage());

            return [];
        }
    }

    public function searchEntityType(): string
    {
        return 'milestone';
    }

    public function toSearchIndexRow(int $id): ?array
    {
        $row = $this->find($id);
        if ($row === false) {
            return null;
        }

        $title = (string) ($row->title ?? '');
        $desc = (string) ($row->description ?? '');

        return [
            $title,
            substr($desc, 0, 200),
            isset($row->project_id) ? (int) $row->project_id : null,
            trim($title . ' ' . $desc),
        ];
    }
}
