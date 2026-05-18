<?php

// file: Controllers/ActivityController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use PDO;
use RuntimeException;

class ActivityController extends BaseController
{
    private Database $db;
    private User $userModel;

    public function __construct(?User $userModel = null)
    {
        parent::__construct();

        // Check authentication first
        if (!$this->authMiddleware->isAuthenticated()) {
            $this->redirect('/login');
        }

        $this->userModel = $userModel ?? new User();

        $this->db = Database::getInstance();
        $this->userModel = new User();
    }

    /**
     * Display paginated list of activity logs
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

            // Check if user has permission to view activity logs
            $this->requirePermission('view_activity');

            $userPermissions = $currentUser['permissions'] ?? [];

            // Get filter parameters
            $filters = $this->getFilters($data);
            $page = max(1, (int)($data['page'] ?? 1));
            $limit = max(10, min(100, (int)($data['limit'] ?? 25)));
            $offset = ($page - 1) * $limit;

            // Get activity logs with filters
            $activities = $this->getActivities($filters, $limit, $offset);
            $totalActivities = $this->getTotalActivities($filters);

            // Get activity statistics
            $stats = $this->getActivityStats($filters);

            // Get all users for filter dropdown (only if user can view all users)
            $users = [];
            if (in_array('view_users', $userPermissions)) {
                $users = $this->userModel->getAllUsers();
            }

            // Calculate pagination
            $totalPages = (int)ceil($totalActivities / $limit);
            $pagination = [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalActivities,
                'items_per_page' => $limit,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1),
            ];

            // Render the view
            $this->renderActivityIndex($activities, $stats, $pagination, $filters, $users);

        } catch (RuntimeException $e) {
            error_log("Activity index error: " . $e->getMessage());
            $this->handleError($e->getMessage());
        }
    }

    /**
     * Get filters from request data
     */
    private function getFilters(array $data): array
    {
        $filters = [
            'event_type' => $data['event_type'] ?? '',
            'entity_type' => $data['entity_type'] ?? '',
            'user_id' => (int)($data['user_id'] ?? 0),
            'date_from' => $data['date_from'] ?? '',
            'date_to' => $data['date_to'] ?? '',
            'search' => trim($data['search'] ?? ''),
        ];

        // Validate and sanitize filters
        if (!empty($filters['date_from']) && !strtotime($filters['date_from'])) {
            $filters['date_from'] = '';
        }
        if (!empty($filters['date_to']) && !strtotime($filters['date_to'])) {
            $filters['date_to'] = '';
        }

        return $filters;
    }

    /**
     * Get activity logs with filters and pagination
     */
    private function getActivities(array $filters, int $limit, int $offset): array
    {
        try {
            // Start with the most basic query possible
            $query = "
                SELECT 
                    al.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    NULL as entity_type,
                    NULL as entity_id,
                    NULL as description,
                    NULL as metadata,
                    NULL as entity_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1
            ";

            $params = [];

            // Apply basic filters only
            if (!empty($filters['event_type'])) {
                $query .= " AND al.event_type = :event_type";
                $params[':event_type'] = $filters['event_type'];
            }

            if (!empty($filters['user_id'])) {
                $query .= " AND al.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $query .= " AND DATE(al.created_at) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $query .= " AND DATE(al.created_at) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $query .= " AND (al.path LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            $query .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";

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
            error_log("Activity query error: " . $e->getMessage());
            error_log("Query: " . $query);
            error_log("Params: " . json_encode($params));

            throw new RuntimeException("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Check if enhanced columns exist in activity_logs table
     */
    private function checkEnhancedColumns(): bool
    {
        try {
            $query = "SHOW COLUMNS FROM activity_logs LIKE 'entity_type'";
            $result = $this->db->executeQuery($query, []);

            return $result->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get total count of activities matching filters
     */
    private function getTotalActivities(array $filters): int
    {
        $hasEnhancedColumns = $this->checkEnhancedColumns();

        $query = "SELECT COUNT(*) FROM activity_logs al WHERE 1=1";
        $params = [];

        // Apply same filters as getActivities
        if (!empty($filters['event_type'])) {
            $query .= " AND al.event_type = :event_type";
            $params[':event_type'] = $filters['event_type'];
        }

        if ($hasEnhancedColumns && !empty($filters['entity_type'])) {
            $query .= " AND al.entity_type = :entity_type";
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['user_id'])) {
            $query .= " AND al.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(al.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(al.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            if ($hasEnhancedColumns) {
                $query .= " AND (al.description LIKE :search OR al.path LIKE :search)";
            } else {
                $query .= " AND al.path LIKE :search";
            }
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        try {
            $result = $this->db->executeQuery($query, $params);

            return (int)$result->fetchColumn();
        } catch (\Exception $e) {
            error_log("Activity count query error: " . $e->getMessage());

            return 0;
        }
    }

    /**
     * Get activity statistics for the dashboard cards
     */
    private function getActivityStats(array $filters): array
    {
        $hasEnhancedColumns = $this->checkEnhancedColumns();

        try {
            $baseQuery = "SELECT COUNT(*) FROM activity_logs al WHERE 1=1";
            $params = [];

            // Apply entity and user filters to all stats
            if ($hasEnhancedColumns && !empty($filters['entity_type'])) {
                $baseQuery .= " AND al.entity_type = :entity_type";
                $params[':entity_type'] = $filters['entity_type'];
            }

            if (!empty($filters['user_id'])) {
                $baseQuery .= " AND al.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            // Total activities (with date filters if applied)
            $totalQuery = $baseQuery;
            $totalParams = $params;
            if (!empty($filters['date_from'])) {
                $totalQuery .= " AND DATE(al.created_at) >= :date_from";
                $totalParams[':date_from'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $totalQuery .= " AND DATE(al.created_at) <= :date_to";
                $totalParams[':date_to'] = $filters['date_to'];
            }

            $totalResult = $this->db->executeQuery($totalQuery, $totalParams);
            $totalActivities = (int)$totalResult->fetchColumn();

            // Today's activities
            $todayQuery = $baseQuery . " AND DATE(al.created_at) = CURDATE()";
            $todayResult = $this->db->executeQuery($todayQuery, $params);
            $todayActivities = (int)$todayResult->fetchColumn();

            // Recent logins (last 24 hours)
            $loginQuery = $baseQuery . " AND al.event_type = 'login_attempt' AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $loginResult = $this->db->executeQuery($loginQuery, $params);
            $recentLogins = (int)$loginResult->fetchColumn();

            // Active users (users with activity in last 24 hours)
            $activeUsersQuery = "SELECT COUNT(DISTINCT al.user_id) FROM activity_logs al WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $activeUsersParams = [];
            if ($hasEnhancedColumns && !empty($filters['entity_type'])) {
                $activeUsersQuery .= " AND al.entity_type = :entity_type";
                $activeUsersParams[':entity_type'] = $filters['entity_type'];
            }
            $activeUsersResult = $this->db->executeQuery($activeUsersQuery, $activeUsersParams);
            $activeUsers = (int)$activeUsersResult->fetchColumn();

            return [
                'total_activities' => $totalActivities,
                'today_activities' => $todayActivities,
                'recent_logins' => $recentLogins,
                'active_users' => $activeUsers,
            ];
        } catch (\Exception $e) {
            error_log("Activity stats query error: " . $e->getMessage());

            return [
                'total_activities' => 0,
                'today_activities' => 0,
                'recent_logins' => 0,
                'active_users' => 0,
            ];
        }
    }

    /**
     * Render the activity index view
     */
    private function renderActivityIndex(array $activities, array $stats, array $pagination, array $filters, array $users): void
    {
        // Set up view data
        $viewTitle = 'Activity Log';

        // Event type options for filter
        $eventTypeOptions = [
            '' => 'All Events',
            'login_attempt' => 'Login Attempts',
            'logout' => 'Logouts',
            'create' => 'Create Actions',
            'update' => 'Update Actions',
            'delete' => 'Delete Actions',
            'list_view' => 'List Views',
            'detail_view' => 'Detail Views',
            'form_view' => 'Form Views',
        ];

        // Entity type options for filter
        $entityTypeOptions = [
            '' => 'All Entities',
            'project' => 'Projects',
            'task' => 'Tasks',
            'user' => 'Users',
            'company' => 'Companies',
            'sprint' => 'Sprints',
            'milestone' => 'Milestones',
            'auth' => 'Authentication',
            'dashboard' => 'Dashboard',
            'system' => 'System',
        ];

        // Render the view
        $this->render('Activity/index', compact(
            'activities',
            'stats',
            'pagination',
            'filters',
            'users',
            'viewTitle',
            'eventTypeOptions',
            'entityTypeOptions'
        ));
    }

    /**
     * Handle errors by redirecting with error message
     */
    private function handleError(string $message): void
    {
        $this->redirectWithError('/dashboard', $message);
    }
}
