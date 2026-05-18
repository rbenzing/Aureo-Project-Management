<?php

// file: Models/Project.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Enums\TaskStatus;
use App\Enums\MilestoneStatus;
use PDO;
use RuntimeException;

/**
 * Project Model
 *
 * Handles all project-related database operations
 */
class Project extends BaseModel
{
    protected string $table = 'projects';

    /**
     * Project properties
     */
    public ?int $id = null;
    public int $company_id;
    public int $owner_id;
    public string $name;
    public ?string $description = null;
    public ?string $key_code = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public int $status_id;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public bool $is_deleted = false;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'company_id',
        'owner_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status_id',
        'key_code',
    ];

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'name',
        'description',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'name' => ['required', 'string'],
        'company_id' => ['required'],
        'owner_id' => ['required'],
        'status_id' => ['required'],
    ];

    /**
     * Get project with full details
     *
     * @param int $id
     * @param array $options Load options to control what gets fetched:
     *   - tasks: bool (default: true)
     *   - milestones: bool (default: true)
     *   - sprints: bool (default: true)
     *   - team_members: bool (default: true)
     *   - hierarchy: bool (default: true) - Warning: expensive operation
     *   - health_metrics: bool (default: true)
     * @return object|null
     */
    public function findWithDetails(int $id, array $options = []): ?object
    {
        // Default options
        $defaultOptions = [
            'tasks' => true,
            'milestones' => true,
            'sprints' => true,
            'team_members' => true,
            'hierarchy' => true,
            'health_metrics' => true,
        ];

        $options = array_merge($defaultOptions, $options);

        $projects = $this->queryBuilder([
            'select' => 'p.*, c.name AS company_name, ps.name AS status_name, u.first_name AS owner_firstname, u.last_name AS owner_lastname',
            'alias' => 'p',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'statuses_project ps', 'on' => 'p.status_id = ps.id'],
                ['type' => 'LEFT', 'table' => 'companies c', 'on' => 'c.id = p.company_id'],
                ['type' => 'LEFT', 'table' => 'users u', 'on' => 'u.id = p.owner_id'],
            ],
            'where' => [
                ['column' => 'p.id', 'operator' => '=', 'value' => $id],
            ],
            'limit' => 1,
        ]);

        if (empty($projects)) {
            return null;
        }

        $project = $projects[0];

        // Conditionally load related data based on options
        if ($options['tasks']) {
            $project->tasks = $this->getProjectTasks($id);
        }

        if ($options['milestones']) {
            $project->milestones = $this->getProjectMilestones($id);
        }

        if ($options['sprints']) {
            $project->sprints = $this->getProjectSprints($id);
        }

        if ($options['team_members']) {
            $project->team_members = $this->getProjectTeamMembers($id);
        }

        if ($options['hierarchy']) {
            // Add hierarchical task data - expensive operation
            $project->hierarchy = $this->getProjectHierarchy($id);
        }

        if ($options['health_metrics']) {
            // Calculate project health metrics
            $project->health_metrics = $this->calculateProjectHealth($id);
        }

        return $project;
    }

    /**
     * Get project basic details without expensive related data
     * Optimized for list views where full hierarchy is not needed
     *
     * @param int $id
     * @return object|null
     */
    public function findBasic(int $id): ?object
    {
        return $this->findWithDetails($id, [
            'tasks' => false,
            'milestones' => false,
            'sprints' => false,
            'team_members' => false,
            'hierarchy' => false,
            'health_metrics' => false,
        ]);
    }

    /**
     * Get all projects with full details
     *
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getAllWithDetails(int $limit = 10, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;

        return $this->queryBuilder([
            'select' => 'p.*, c.name AS company_name, ps.name AS status_name, u.first_name AS owner_firstname, u.last_name AS owner_lastname,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0) as task_count,
                (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.is_deleted = 0) as milestone_count',
            'alias' => 'p',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'statuses_project ps', 'on' => 'p.status_id = ps.id'],
                ['type' => 'LEFT', 'table' => 'companies c', 'on' => 'c.id = p.company_id'],
                ['type' => 'LEFT', 'table' => 'users u', 'on' => 'u.id = p.owner_id'],
            ],
            'orderBy' => 'p.updated_at DESC',
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Calculate project health metrics
     *
     * @param int $projectId
     * @return array
     */
    public function calculateProjectHealth(int $projectId): array
    {
        try {
            $completedTaskStatus = TaskStatus::COMPLETED->value;
            $completedMilestoneStatus = MilestoneStatus::COMPLETED->value;

            // Task completion rate
            $taskSql = "SELECT
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status_id = :completed_a THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN due_date < CURDATE() AND status_id != :completed_b THEN 1 ELSE 0 END) as overdue_tasks
            FROM tasks
            WHERE project_id = :project_id AND is_deleted = 0";

            $taskStmt = $this->db->executeQuery($taskSql, [
                ':project_id' => $projectId,
                ':completed_a' => $completedTaskStatus,
                ':completed_b' => $completedTaskStatus,
            ]);
            $taskMetrics = $taskStmt->fetch(PDO::FETCH_ASSOC);

            // Milestone completion rate
            $milestoneSql = "SELECT
                COUNT(*) as total_milestones,
                SUM(CASE WHEN status_id = :completed_a THEN 1 ELSE 0 END) as completed_milestones,
                SUM(CASE WHEN due_date < CURDATE() AND status_id != :completed_b THEN 1 ELSE 0 END) as overdue_milestones
            FROM milestones
            WHERE project_id = :project_id AND is_deleted = 0";

            $milestoneStmt = $this->db->executeQuery($milestoneSql, [
                ':project_id' => $projectId,
                ':completed_a' => $completedMilestoneStatus,
                ':completed_b' => $completedMilestoneStatus,
            ]);
            $milestoneMetrics = $milestoneStmt->fetch(PDO::FETCH_ASSOC);

            // Calculate percentages
            $taskCompletionRate = $taskMetrics['total_tasks'] > 0
                ? round(($taskMetrics['completed_tasks'] / $taskMetrics['total_tasks']) * 100, 2)
                : 0;

            $milestoneCompletionRate = $milestoneMetrics['total_milestones'] > 0
                ? round(($milestoneMetrics['completed_milestones'] / $milestoneMetrics['total_milestones']) * 100, 2)
                : 0;

            return [
                'task_completion_rate' => $taskCompletionRate,
                'milestone_completion_rate' => $milestoneCompletionRate,
                'total_tasks' => $taskMetrics['total_tasks'],
                'completed_tasks' => $taskMetrics['completed_tasks'],
                'overdue_tasks' => $taskMetrics['overdue_tasks'],
                'total_milestones' => $milestoneMetrics['total_milestones'],
                'completed_milestones' => $milestoneMetrics['completed_milestones'],
                'overdue_milestones' => $milestoneMetrics['overdue_milestones'],
                'overall_health' => $this->calculateOverallProjectHealth(
                    $taskCompletionRate,
                    $milestoneCompletionRate
                ),
            ];
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to calculate project health: " . $e->getMessage());
        }
    }

    /**
     * Calculate overall project health score
     *
     * @param float $taskCompletionRate
     * @param float $milestoneCompletionRate
     * @return string
     */
    private function calculateOverallProjectHealth(float $taskCompletionRate, float $milestoneCompletionRate): string
    {
        $averageCompletionRate = ($taskCompletionRate + $milestoneCompletionRate) / 2;

        return match (true) {
            $averageCompletionRate >= 85 => 'Excellent',
            $averageCompletionRate >= 70 => 'Good',
            $averageCompletionRate >= 50 => 'At Risk',
            default => 'Critical'
        };
    }

    /**
     * Get project tasks with assignee details
     *
     * @param int $projectId
     * @return array
     */
    public function getProjectTasks(int $projectId): array
    {
        try {
            $sql = "SELECT
                t.*,
                ts.name AS status_name,
                u.first_name,
                u.last_name
            FROM
                tasks t
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                t.is_subtask = 0
                AND t.project_id = :project_id
                AND t.is_deleted = 0
            ORDER BY
                t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get project tasks with assignees: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get hierarchical project data with epics, milestones, and tasks
     *
     * @param int $projectId
     * @return array
     */
    public function getProjectHierarchy(int $projectId): array
    {
        try {
            $hierarchy = [];

            // Get all epics for the project
            $epics = $this->getProjectEpics($projectId);

            foreach ($epics as $epic) {
                $epicData = [
                    'type' => 'epic',
                    'data' => $epic,
                    'milestones' => [],
                    'tasks' => [],
                ];

                // Get milestones for this epic
                $milestones = $this->getEpicMilestones($epic->id);

                foreach ($milestones as $milestone) {
                    $milestoneData = [
                        'type' => 'milestone',
                        'data' => $milestone,
                        'tasks' => $this->getMilestoneTasks($milestone->id),
                    ];
                    $epicData['milestones'][] = $milestoneData;
                }

                // Get tasks directly assigned to epic (not through milestones)
                $epicData['tasks'] = $this->getEpicTasks($epic->id);

                $hierarchy[] = $epicData;
            }

            // Get standalone milestones (not part of any epic)
            $standaloneMilestones = $this->getStandaloneMilestones($projectId);

            foreach ($standaloneMilestones as $milestone) {
                $milestoneData = [
                    'type' => 'milestone',
                    'data' => $milestone,
                    'tasks' => $this->getMilestoneTasks($milestone->id),
                ];
                $hierarchy[] = $milestoneData;
            }

            // Get tasks not assigned to any milestone or epic
            $unassignedTasks = $this->getUnassignedTasks($projectId);
            if (!empty($unassignedTasks)) {
                $hierarchy[] = [
                    'type' => 'unassigned_tasks',
                    'data' => (object)['title' => 'Unassigned Tasks'],
                    'tasks' => $unassignedTasks,
                ];
            }

            return $hierarchy;
        } catch (\Exception $e) {
            error_log("Failed to get project hierarchy: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get epics for a project
     *
     * @param int $projectId
     * @return array
     */
    private function getProjectEpics(int $projectId): array
    {
        try {
            $sql = "SELECT
                m.*,
                s.name AS status_name
            FROM
                milestones m
            LEFT JOIN
                statuses_milestone s ON m.status_id = s.id
            WHERE
                m.project_id = :project_id
                AND m.milestone_type = 'epic'
                AND m.is_deleted = 0
            ORDER BY
                m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get project epics: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get milestones for an epic
     *
     * @param int $epicId
     * @return array
     */
    private function getEpicMilestones(int $epicId): array
    {
        try {
            $sql = "SELECT
                m.*,
                s.name AS status_name
            FROM
                milestones m
            LEFT JOIN
                statuses_milestone s ON m.status_id = s.id
            WHERE
                m.epic_id = :epic_id
                AND m.milestone_type = 'milestone'
                AND m.is_deleted = 0
            ORDER BY
                m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':epic_id' => $epicId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get epic milestones: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get tasks for a milestone
     *
     * @param int $milestoneId
     * @return array
     */
    private function getMilestoneTasks(int $milestoneId): array
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
                milestone_tasks mt ON t.id = mt.task_id
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                mt.milestone_id = :milestone_id
                AND t.is_deleted = 0
            ORDER BY
                t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [':milestone_id' => $milestoneId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get milestone tasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get tasks directly assigned to an epic (not through milestones)
     *
     * @param int $epicId
     * @return array
     */
    private function getEpicTasks(int $epicId): array
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
                milestone_tasks mt ON t.id = mt.task_id
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                mt.milestone_id = :epic_id
                AND t.is_deleted = 0
            ORDER BY
                t.priority DESC, t.due_date ASC";

            $stmt = $this->db->executeQuery($sql, [':epic_id' => $epicId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get epic tasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get standalone milestones (not part of any epic)
     *
     * @param int $projectId
     * @return array
     */
    private function getStandaloneMilestones(int $projectId): array
    {
        try {
            $sql = "SELECT
                m.*,
                s.name AS status_name
            FROM
                milestones m
            LEFT JOIN
                statuses_milestone s ON m.status_id = s.id
            WHERE
                m.project_id = :project_id
                AND m.milestone_type = 'milestone'
                AND m.epic_id IS NULL
                AND m.is_deleted = 0
            ORDER BY
                m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get standalone milestones: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get tasks not assigned to any milestone or epic
     *
     * @param int $projectId
     * @return array
     */
    private function getUnassignedTasks(int $projectId): array
    {
        try {
            $sql = "SELECT
                t.*,
                ts.name AS status_name,
                u.first_name,
                u.last_name
            FROM
                tasks t
            LEFT JOIN
                statuses_task ts ON t.status_id = ts.id
            LEFT JOIN
                users u ON t.assigned_to = u.id
            WHERE
                t.project_id = :project_id
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

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Failed to get unassigned tasks: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Get project milestones
     *
     * @param int $projectId
     * @return array
     */
    public function getProjectMilestones(int $projectId): array
    {
        try {
            $sql = "SELECT 
                    m.*,
                    s.name AS status_name
                FROM 
                    milestones m
                LEFT JOIN 
                    statuses_milestone s ON m.status_id = s.id
                WHERE 
                    m.project_id = :project_id
                    AND m.is_deleted = 0
                ORDER BY
                    m.due_date ASC, m.title ASC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get project milestones: " . $e->getMessage());
        }
    }

    /**
     * Get project sprints
     *
     * @param int $projectId
     * @return array
     */
    public function getProjectSprints(int $projectId): array
    {
        try {
            $sql = "SELECT 
                    s.*,
                    ss.name AS status_name,
                    (
                        SELECT COUNT(*) FROM sprint_tasks st
                        JOIN tasks t ON st.task_id = t.id
                        WHERE st.sprint_id = s.id AND t.is_deleted = 0
                    ) as task_count
                FROM 
                    sprints s
                LEFT JOIN 
                    statuses_sprint ss ON s.status_id = ss.id
                WHERE 
                    s.project_id = :project_id
                    AND s.is_deleted = 0
                ORDER BY
                    s.start_date DESC";

            $stmt = $this->db->executeQuery($sql, [':project_id' => $projectId]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get project sprints: " . $e->getMessage());
        }
    }

    /**
     * Get project team members
     *
     * @param int $projectId
     * @return array
     */
    public function getProjectTeamMembers(int $projectId): array
    {
        try {
            $sql = "SELECT DISTINCT 
                    u.*,
                    r.name AS role_name
                FROM 
                    users u
                LEFT JOIN
                    roles r ON u.role_id = r.id
                WHERE 
                    (
                        u.id IN (
                            SELECT assigned_to FROM tasks 
                            WHERE project_id = :project_id AND is_deleted = 0
                        )
                        OR
                        u.id IN (
                            SELECT user_id FROM user_projects 
                            WHERE project_id = :project_id_2
                        )
                    )
                    AND u.is_deleted = 0
                ORDER BY
                    u.first_name, u.last_name";

            $stmt = $this->db->executeQuery($sql, [
                ':project_id' => $projectId,
                ':project_id_2' => $projectId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get project team members: " . $e->getMessage());
        }
    }

    /**
     * Transform project name into a project key code format
     *
     * @param string $name Project name
     * @return string Key code (e.g. "Project Management System" -> "PMS")
     */
    public function transformKeyCodeFormat(string $name): string
    {
        // Remove any non-alphanumeric characters and split by spaces
        $words = preg_split('/[^a-zA-Z0-9]/', $name, -1, PREG_SPLIT_NO_EMPTY);

        // Simple project code - take first letter of each word and uppercase it
        $code = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }

        // If code is empty or less than 2 chars, use first 3 letters of first word
        if (strlen($code) < 2 && !empty($words[0])) {
            $code = strtoupper(substr($words[0], 0, min(3, strlen($words[0]))));
        }

        // Ensure code is valid (fallback to "PRJ" if empty)
        return !empty($code) ? $code : 'PRJ';
    }

    /**
     * Get all project statuses
     *
     * @return array
     */
    public function getAllStatuses(): array
    {
        try {
            $sql = "SELECT * FROM statuses_project WHERE is_deleted = 0";
            $stmt = $this->db->executeQuery($sql);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to fetch project statuses: " . $e->getMessage());
        }
    }

    /**
     * Get recent projects by user (projects where user has tasks or is owner)
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecentByUser(int $userId, int $limit = 15): array
    {
        try {
            $sql = "SELECT DISTINCT p.*,
                           c.name as company_name,
                           ps.name as status_name,
                           GREATEST(p.updated_at, COALESCE(MAX(t.updated_at), p.updated_at)) as last_activity
                    FROM projects p
                    LEFT JOIN companies c ON p.company_id = c.id
                    LEFT JOIN statuses_project ps ON p.status_id = ps.id
                    LEFT JOIN tasks t ON p.id = t.project_id AND t.assigned_to = :user_id1 AND t.is_deleted = 0
                    WHERE (
                        p.owner_id = :user_id2
                        OR EXISTS (
                            SELECT 1 FROM tasks t2
                            WHERE t2.project_id = p.id
                            AND t2.assigned_to = :user_id3
                            AND t2.is_deleted = 0
                        )
                    )
                    AND p.is_deleted = 0
                    GROUP BY p.id, p.name, p.description, p.status_id, p.owner_id,
                             p.company_id, p.start_date, p.end_date, p.created_at,
                             p.updated_at, p.is_deleted, c.name, ps.name
                    ORDER BY last_activity DESC
                    LIMIT :limit";

            $stmt = $this->db->executeQuery($sql, [
                ':user_id1' => $userId,
                ':user_id2' => $userId,
                ':user_id3' => $userId,
                ':limit' => $limit,
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Debug logging to help troubleshoot
            error_log("Recent projects query for user $userId returned " . count($results) . " results (projects where user is owner or has assigned tasks)");

            return $results;
        } catch (\Exception $e) {
            throw new RuntimeException("Error fetching recent projects: " . $e->getMessage());
        }
    }
}
