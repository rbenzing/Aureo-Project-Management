<?php

// file: Controllers/TimeTrackingController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Utils\Time;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class TimeTrackingController extends BaseController
{
    private Task $taskModel;
    private Project $projectModel;
    private User $userModel;
    private Database $db;

    public function __construct(
        ?Task $taskModel = null,
        ?Project $projectModel = null,
        ?User $userModel = null
    ) {
        parent::__construct();
        $this->requirePermission('view_time_tracking');

        $this->taskModel = $taskModel ?? new Task();
        $this->projectModel = $projectModel ?? new Project();
        $this->userModel = $userModel ?? new User();

        $this->taskModel = new Task();
        $this->projectModel = new Project();
        $this->userModel = new User();
        $this->db = Database::getInstance();
    }

    /**
     * Display paginated list of time entries
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            // Get current user
            $currentUser = $_SESSION['user'] ?? null;
            if (!$currentUser) {
                throw new RuntimeException('User not authenticated');
            }

            // Get filter parameters
            $filters = $this->getFilters($data);
            $page = max(1, (int)($data['page'] ?? 1));
            $limit = max(10, min(100, (int)($data['limit'] ?? 25)));
            $offset = ($page - 1) * $limit;

            // Get time entries with filters
            $timeEntries = $this->getTimeEntries($filters, $limit, $offset);
            $totalEntries = $this->getTotalTimeEntries($filters);

            // Get time tracking statistics
            $timeData = $this->getTimeData($filters);

            // Get projects and users for filter dropdowns
            $projectsResult = $this->projectModel->getAll(['is_deleted' => 0], 1, 1000);
            $projects = $projectsResult['records'];
            $users = [];
            if (in_array('view_users', $_SESSION['user']['permissions'] ?? [])) {
                $usersResult = $this->userModel->getAll(['is_deleted' => 0], 1, 1000);
                $users = $usersResult['records'];
            }

            // Calculate pagination
            $totalPages = (int)ceil($totalEntries / $limit);
            $pagination = [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total' => $totalEntries,
                'start' => $offset + 1,
                'end' => min($offset + $limit, $totalEntries),
            ];

            // Render the view
            $this->render('time-tracking/index', compact('pagination', 'totalPages'));

        } catch (RuntimeException $e) {
            error_log("Time tracking index error: " . $e->getMessage());
            $this->redirectWithError('/dashboard', $e->getMessage());
        }
    }

    /**
     * Get filters from request data
     */
    private function getFilters(array $data): array
    {
        $filters = [
            'date_range' => $data['date_range'] ?? 'this_month',
            'project_id' => (int)($data['project_id'] ?? 0),
            'user_id' => (int)($data['user_id'] ?? 0),
            'billable_only' => !empty($data['billable_only']),
        ];

        return $filters;
    }

    /**
     * Get time entries with filters and pagination
     */
    private function getTimeEntries(array $filters, int $limit, int $offset): array
    {
        try {
            $query = "
                SELECT 
                    te.*,
                    t.title as task_title,
                    t.task_type,
                    p.name as project_name,
                    u.first_name,
                    u.last_name
                FROM time_entries te
                LEFT JOIN tasks t ON te.task_id = t.id
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON te.user_id = u.id
                WHERE 1=1
            ";

            $params = [];

            // Apply date range filter
            $dateCondition = $this->getDateRangeCondition($filters['date_range']);
            if ($dateCondition) {
                $query .= " AND " . $dateCondition['condition'];
                $params = array_merge($params, $dateCondition['params']);
            }

            // Apply project filter
            if (!empty($filters['project_id'])) {
                $query .= " AND t.project_id = :project_id";
                $params[':project_id'] = $filters['project_id'];
            }

            // Apply user filter
            if (!empty($filters['user_id'])) {
                $query .= " AND te.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            // Apply billable filter
            if ($filters['billable_only']) {
                $query .= " AND te.is_billable = 1";
            }

            $query .= " ORDER BY te.start_time DESC LIMIT :limit OFFSET :offset";

            // Add LIMIT and OFFSET to params
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            // Use the Database class executeQuery method
            $stmt = $this->db->executeQuery($query, $params);

            // Bind integer parameters for LIMIT and OFFSET
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (\Exception $e) {
            error_log("Time entries query error: " . $e->getMessage());

            throw new RuntimeException("Failed to fetch time entries: " . $e->getMessage());
        }
    }

    /**
     * Get total count of time entries matching filters
     */
    private function getTotalTimeEntries(array $filters): int
    {
        try {
            $query = "
                SELECT COUNT(*) 
                FROM time_entries te
                LEFT JOIN tasks t ON te.task_id = t.id
                WHERE 1=1
            ";

            $params = [];

            // Apply same filters as getTimeEntries
            $dateCondition = $this->getDateRangeCondition($filters['date_range']);
            if ($dateCondition) {
                $query .= " AND " . $dateCondition['condition'];
                $params = array_merge($params, $dateCondition['params']);
            }

            if (!empty($filters['project_id'])) {
                $query .= " AND t.project_id = :project_id";
                $params[':project_id'] = $filters['project_id'];
            }

            if (!empty($filters['user_id'])) {
                $query .= " AND te.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            if ($filters['billable_only']) {
                $query .= " AND te.is_billable = 1";
            }

            $result = $this->db->executeQuery($query, $params);

            return (int)$result->fetchColumn();

        } catch (\Exception $e) {
            error_log("Time entries count query error: " . $e->getMessage());

            return 0;
        }
    }

    /**
     * Get time tracking statistics
     */
    private function getTimeData(array $filters): array
    {
        try {
            $stats = [
                'today' => $this->getTimeByRange('today'),
                'this_week' => $this->getTimeByRange('this_week'),
                'this_month' => $this->getTimeByRange('this_month'),
                'billable' => $this->getBillableTime(),
            ];

            return $stats;

        } catch (\Exception $e) {
            error_log("Time data query error: " . $e->getMessage());

            return [
                'today' => 0,
                'this_week' => 0,
                'this_month' => 0,
                'billable' => 0,
            ];
        }
    }

    /**
     * Get time by date range
     */
    private function getTimeByRange(string $range): int
    {
        $dateCondition = $this->getDateRangeCondition($range);
        if (!$dateCondition) {
            return 0;
        }

        $query = "
            SELECT COALESCE(SUM(duration), 0) 
            FROM time_entries 
            WHERE " . $dateCondition['condition'];

        $result = $this->db->executeQuery($query, $dateCondition['params']);

        return (int)$result->fetchColumn();
    }

    /**
     * Get billable time for current month
     */
    private function getBillableTime(): int
    {
        $query = "
            SELECT COALESCE(SUM(duration), 0) 
            FROM time_entries 
            WHERE is_billable = 1 
            AND MONTH(start_time) = MONTH(NOW()) 
            AND YEAR(start_time) = YEAR(NOW())";

        $result = $this->db->executeQuery($query, []);

        return (int)$result->fetchColumn();
    }

    /**
     * Get date range condition for SQL queries
     */
    private function getDateRangeCondition(string $range): ?array
    {
        return match($range) {
            'today' => [
                'condition' => 'DATE(start_time) = CURDATE()',
                'params' => [],
            ],
            'yesterday' => [
                'condition' => 'DATE(start_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
                'params' => [],
            ],
            'this_week' => [
                'condition' => 'start_time >= DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY) AND start_time < DATE_ADD(DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY), INTERVAL 7 DAY)',
                'params' => [],
            ],
            'last_week' => [
                'condition' => 'start_time >= DATE_SUB(DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY), INTERVAL 7 DAY) AND start_time < DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY)',
                'params' => [],
            ],
            'this_month' => [
                'condition' => 'MONTH(start_time) = MONTH(NOW()) AND YEAR(start_time) = YEAR(NOW())',
                'params' => [],
            ],
            'last_month' => [
                'condition' => 'start_time >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), "%Y-%m-01") AND start_time < DATE_FORMAT(NOW(), "%Y-%m-01")',
                'params' => [],
            ],
            default => null
        };
    }

    /**
     * Start a timer for a task
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function startTimer(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/tasks', 'Invalid request method.');
        }

        try {
            $this->requirePermission('edit_tasks');

            $taskId = filter_var($data['task_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$taskId) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            // Fetch and validate task
            $task = $this->taskModel->find($taskId);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            // Check if user has permission to track time on this task
            $userId = $_SESSION['user']['id'] ?? null;
            if ($task->assigned_to !== $userId && !$this->requirePermission('manage_tasks')) {
                throw new InvalidArgumentException('You do not have permission to track time for this task');
            }

            // Store timer start in session
            $_SESSION['active_timer'] = [
                'task_id' => $taskId,
                'start_time' => time(),
            ];

            // Add timer start history entry
            $this->taskModel->addTimerStartHistory($taskId, $userId);

            $_SESSION['success'] = 'Timer started successfully.';
            header("Location: /tasks/view/{$taskId}");
            exit;

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/tasks', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in TimeTrackingController::startTimer: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while starting the timer.');
        }
    }

    /**
     * Stop a timer for a task
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function stopTimer(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/tasks', 'Invalid request method.');
        }

        try {
            $this->requirePermission('edit_tasks');

            // Check if there's an active timer
            if (empty($_SESSION['active_timer'])) {
                throw new InvalidArgumentException('No active timer found');
            }

            $activeTimer = $_SESSION['active_timer'];
            $taskId = $activeTimer['task_id'];
            $startTime = $activeTimer['start_time'];

            // Fetch and validate task
            $task = $this->taskModel->find($taskId);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            // Calculate duration
            $duration = time() - $startTime;

            // Update task time
            $taskUpdate = [
                'time_spent' => ($task->time_spent ?? 0) + $duration,
            ];

            // If task is billable, update billable time too
            if ($task->is_hourly) {
                $taskUpdate['billable_time'] = ($task->billable_time ?? 0) + $duration;
            }

            $this->taskModel->update($taskId, $taskUpdate);

            // Create time entry record
            $this->createTimeEntry($taskId, $startTime, time(), $duration, $task->is_hourly);

            // Add timer stop history entry
            $userId = $_SESSION['user']['id'] ?? null;
            if ($userId) {
                $this->taskModel->addTimerStopHistory($taskId, $userId, $duration);
            }

            // Clear the active timer
            unset($_SESSION['active_timer']);

            $_SESSION['success'] = 'Timer stopped successfully.';
            header("Location: /tasks/view/{$taskId}");
            exit;

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/tasks', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in TimeTrackingController::stopTimer: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while stopping the timer.');
        }
    }

    /**
     * Create a time entry record
     * @param int $taskId
     * @param int $startTime Unix timestamp
     * @param int $endTime Unix timestamp
     * @param int $duration Duration in seconds
     * @param bool $isBillable
     * @return int Time entry ID
     */
    private function createTimeEntry(int $taskId, int $startTime, int $endTime, int $duration, bool $isBillable): int
    {
        // Get the current user ID
        $userId = $_SESSION['user']['id'] ?? null;
        if (!$userId) {
            throw new RuntimeException('User session not found');
        }

        // Format start and end times for database
        $startDateTime = date('Y-m-d H:i:s', $startTime);
        $endDateTime = date('Y-m-d H:i:s', $endTime);

        // Create time entry record
        $entryData = [
            'task_id' => $taskId,
            'user_id' => $userId,
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'duration' => $duration,
            'is_billable' => $isBillable ? 1 : 0,
        ];

        return $this->taskModel->createTimeEntry($entryData);
    }
}
