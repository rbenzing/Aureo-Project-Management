<?php

// file: public/index.php
declare(strict_types=1);

// Start the session
session_start();

// Define base path
define('BASE_PATH', __DIR__);

// CSP Headers
$cspHeader = "Content-Security-Policy: ".
    "default-src 'self'; ".
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ".
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ".
    "img-src 'self' data: https://cdn.jsdelivr.net; ".
    "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ".
    "connect-src 'self'; ".
    "frame-src 'self'; ".
    "object-src 'none'; ".
    "base-uri 'self';";
header($cspHeader);

// Include Composer's autoloader
require_once BASE_PATH . '/../vendor/autoload.php';

// Load environment + configuration BEFORE building the DI container,
// since container factories (e.g. SettingsService → Database) read $_ENV.
\App\Core\Config::init();

// Load the dependency injection container
$container = require_once BASE_PATH . '/../config/container.php';

// Get services from container
$securityService = $container->get(\App\Services\SecurityService::class);
$logger = $container->get(\App\Services\LoggerService::class);

// Apply security headers based on settings
try {
    $securityService->applySecurityHeaders();
} catch (\Exception $e) {
    // Fallback to basic security headers if settings not available
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=()");
}

// Error handling
try {
    // Rate limiting check
    if (!$securityService->checkRateLimit()) {
        http_response_code(429);
        echo "Too many requests. Please try again later.";
        exit;
    }

    // Input size validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        if (!$securityService->validateInputSize($input)) {
            http_response_code(413);
            echo "Request too large.";
            exit;
        }
    }

    // Initialize middleware stack
    $container->get(\App\Middleware\CsrfMiddleware::class)->handleToken();
    $container->get(\App\Middleware\ActivityMiddleware::class)->handle();

    // Global authentication gate — runs before routing.
    // Public paths (login, register, activation, password reset) are exempted.
    $publicPaths = ['login', 'register', 'activate', 'reset-password', 'forgot-password'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $firstSegment = trim(explode('/', ltrim($uri, '/'))[0]);
    if (!in_array($firstSegment, $publicPaths, true)) {
        $container->get(\App\Middleware\AuthMiddleware::class)->isAuthenticated();
    }

    // Register event listeners
    $dispatcher = \App\Events\EventDispatcher::getInstance();
    $dispatcher->listen(\App\Events\TaskAssigned::class, \App\Listeners\LogTaskAssignment::class);
    $dispatcher->listen(\App\Events\TaskAssigned::class, \App\Listeners\SendTaskAssignmentEmail::class);

    // Create Router Instance with DI container
    $router = new \App\Core\Router($container);

    // Define Routes
    // Auth Routes
    $router->get('', ['controller' => 'Dashboard', 'action' => 'index']);
    $router->get('login', ['controller' => 'Auth', 'action' => 'loginForm']);
    $router->post('login', ['controller' => 'Auth', 'action' => 'login']);
    $router->get('logout', ['controller' => 'Auth', 'action' => 'logout']);
    $router->get('register', ['controller' => 'Auth', 'action' => 'registerForm']);
    $router->post('register', ['controller' => 'Auth', 'action' => 'register']);
    $router->get('activate/:token', ['controller' => 'Auth', 'action' => 'activate', 'params' => ['token']]);
    $router->get('reset-password/:token', ['controller' => 'Auth', 'action' => 'resetPassword', 'params' => ['token']]);
    $router->post('reset-password/:token', ['controller' => 'Auth', 'action' => 'resetPassword', 'params' => ['token']]);
    $router->get('forgot-password', ['controller' => 'Auth', 'action' => 'forgotPassword']);
    $router->post('forgot-password', ['controller' => 'Auth', 'action' => 'forgotPassword']);

    // Dashboard Routes
    $router->get('dashboard', ['controller' => 'Dashboard', 'action' => 'index']);

    // Project Routes
    $router->get('projects', ['controller' => 'Project', 'action' => 'index']);
    $router->get('projects/page/:page', ['controller' => 'Project', 'action' => 'index', 'params' => ['page']]);
    $router->get('projects/view/:id', ['controller' => 'Project', 'action' => 'view', 'params' => ['id']]);
    $router->get('projects/create', ['controller' => 'Project', 'action' => 'createForm']);
    $router->post('projects/create', ['controller' => 'Project', 'action' => 'create']);
    $router->get('projects/edit/:id', ['controller' => 'Project', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('projects/update', ['controller' => 'Project', 'action' => 'update']);
    $router->post('projects/delete/:id', ['controller' => 'Project', 'action' => 'delete', 'params' => ['id']]);

    // Task Routes
    $router->get('tasks', ['controller' => 'Task', 'action' => 'index']);
    $router->get('tasks/page/:page', ['controller' => 'Task', 'action' => 'index', 'params' => ['page']]);
    $router->get('tasks/backlog', ['controller' => 'Task', 'action' => 'backlog']);
    $router->get('tasks/backlog/page/:page', ['controller' => 'Task', 'action' => 'backlog', 'params' => ['page']]);
    $router->get('tasks/unassigned', ['controller' => 'Task', 'action' => 'index']);
    $router->get('tasks/unassigned/page/:page', ['controller' => 'Task', 'action' => 'index', 'params' => ['page']]);
    $router->get('tasks/sprint-planning', ['controller' => 'Task', 'action' => 'sprintPlanning']);
    $router->get('tasks/assigned/:id', ['controller' => 'Task', 'action' => 'index', 'params' => ['id']]);
    $router->get('tasks/assigned/:id/page/:page', ['controller' => 'Task', 'action' => 'index', 'params' => ['id', 'page']]);
    $router->get('tasks/project/:id', ['controller' => 'Task', 'action' => 'index', 'params' => ['id']]);
    $router->get('tasks/view/:id', ['controller' => 'Task', 'action' => 'view', 'params' => ['id']]);
    $router->get('tasks/create', ['controller' => 'Task', 'action' => 'createForm']);
    $router->post('tasks/create', ['controller' => 'Task', 'action' => 'create']);
    $router->get('tasks/edit/:id', ['controller' => 'Task', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('tasks/update', ['controller' => 'Task', 'action' => 'update']);
    $router->post('tasks/update-status', ['controller' => 'Task', 'action' => 'updateStatus']);
    $router->post('tasks/delete/:id', ['controller' => 'Task', 'action' => 'delete', 'params' => ['id']]);
    $router->post('tasks/start-timer/:task_id', ['controller' => 'Task', 'action' => 'startTimer', 'params' => ['task_id']]);
    $router->post('tasks/stop-timer/:task_id', ['controller' => 'Task', 'action' => 'stopTimer', 'params' => ['task_id']]);
    $router->post('tasks/add-comment/:id', ['controller' => 'Task', 'action' => 'addComment', 'params' => ['id']]);

    // API Routes for AJAX
    $router->post('api/tasks/update-backlog-priorities', ['controller' => 'Task', 'action' => 'updateBacklogPriorities']);
    $router->post('api/sprints/assign-task', ['controller' => 'Sprint', 'action' => 'assignTask']);
    $router->get('api/projects/:id/epics', ['controller' => 'Milestone', 'action' => 'getProjectEpicsApi', 'params' => ['id']]);

    // Favorites API Routes
    $router->get('api/favorites', ['controller' => 'Favorites', 'action' => 'index']);
    $router->post('api/favorites/add', ['controller' => 'Favorites', 'action' => 'add']);
    $router->post('api/favorites/remove', ['controller' => 'Favorites', 'action' => 'remove']);
    $router->post('api/favorites/update-order', ['controller' => 'Favorites', 'action' => 'updateOrder']);
    $router->get('api/favorites/check', ['controller' => 'Favorites', 'action' => 'check']);

    // User Routes
    $router->get('users', ['controller' => 'User', 'action' => 'index']);
    $router->get('users/page/:page', ['controller' => 'User', 'action' => 'index', 'params' => ['page']]);
    $router->get('users/view/:id', ['controller' => 'User', 'action' => 'view', 'params' => ['id']]);
    $router->get('users/create', ['controller' => 'User', 'action' => 'createForm']);
    $router->post('users/create', ['controller' => 'User', 'action' => 'create']);
    $router->get('users/edit/:id', ['controller' => 'User', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('users/update', ['controller' => 'User', 'action' => 'update']);
    $router->post('users/delete/:id', ['controller' => 'User', 'action' => 'delete', 'params' => ['id']]);

    // Profile Routes
    $router->get('profile', ['controller' => 'User', 'action' => 'profile']);

    // Role Routes
    $router->get('roles', ['controller' => 'Role', 'action' => 'index']);
    $router->get('roles/page/:page', ['controller' => 'Role', 'action' => 'index', 'params' => ['page']]);
    $router->get('roles/view/:id', ['controller' => 'Role', 'action' => 'view', 'params' => ['id']]);
    $router->get('roles/create', ['controller' => 'Role', 'action' => 'createForm']);
    $router->post('roles/create', ['controller' => 'Role', 'action' => 'create']);
    $router->get('roles/edit/:id', ['controller' => 'Role', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('roles/update', ['controller' => 'Role', 'action' => 'update']);
    $router->post('roles/delete/:id', ['controller' => 'Role', 'action' => 'delete', 'params' => ['id']]);

    // Company Routes
    $router->get('companies', ['controller' => 'Company', 'action' => 'index']);
    $router->get('companies/page/:page', ['controller' => 'Company', 'action' => 'index', 'params' => ['page']]);
    $router->get('companies/view/:id', ['controller' => 'Company', 'action' => 'view', 'params' => ['id']]);
    $router->get('companies/create', ['controller' => 'Company', 'action' => 'createForm']);
    $router->post('companies/create', ['controller' => 'Company', 'action' => 'create']);
    $router->get('companies/edit/:id', ['controller' => 'Company', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('companies/update', ['controller' => 'Company', 'action' => 'update']);
    $router->post('companies/delete/:id', ['controller' => 'Company', 'action' => 'delete', 'params' => ['id']]);

    // Milestone Routes
    $router->get('milestones', ['controller' => 'Milestone', 'action' => 'index']);
    $router->get('milestones/page/:page', ['controller' => 'Milestone', 'action' => 'index', 'params' => ['page']]);
    $router->get('milestones/project/:id', ['controller' => 'Milestone', 'action' => 'index', 'params' => ['id']]);
    $router->get('milestones/project/:id/page/:page', ['controller' => 'Milestone', 'action' => 'index', 'params' => ['id', 'page']]);
    $router->get('milestones/view/:id', ['controller' => 'Milestone', 'action' => 'view', 'params' => ['id']]);
    $router->get('milestones/create', ['controller' => 'Milestone', 'action' => 'createForm']);
    $router->post('milestones/create', ['controller' => 'Milestone', 'action' => 'create']);
    $router->get('milestones/edit/:id', ['controller' => 'Milestone', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('milestones/update', ['controller' => 'Milestone', 'action' => 'update']);
    $router->post('milestones/delete/:id', ['controller' => 'Milestone', 'action' => 'delete', 'params' => ['id']]);

    // Sprint Routes
    $router->get('sprints', ['controller' => 'Sprint', 'action' => 'index']);
    $router->get('sprints/current', ['controller' => 'Sprint', 'action' => 'current']);
    $router->get('sprints/planning', ['controller' => 'Sprint', 'action' => 'planning']);
    $router->get('sprints/page/:page', ['controller' => 'Sprint', 'action' => 'index', 'params' => ['page']]);
    $router->get('sprints/project/:id', ['controller' => 'Sprint', 'action' => 'index', 'params' => ['id']]);
    $router->get('sprints/project/:id/page/:page', ['controller' => 'Sprint', 'action' => 'index', 'params' => ['id', 'page']]);
    $router->get('sprints/view/:id', ['controller' => 'Sprint', 'action' => 'view', 'params' => ['id']]);
    $router->get('sprints/board/:id', ['controller' => 'Sprint', 'action' => 'board', 'params' => ['id']]);
    $router->get('sprints/create/:id', ['controller' => 'Sprint', 'action' => 'createForm', 'params' => ['id']]);
    $router->post('sprints/create', ['controller' => 'Sprint', 'action' => 'create']);
    $router->post('sprints/create-from-planning', ['controller' => 'Sprint', 'action' => 'createFromPlanning']);
    $router->get('sprints/edit/:id', ['controller' => 'Sprint', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('sprints/update/:id', ['controller' => 'Sprint', 'action' => 'update', 'params' => ['id']]);
    $router->post('sprints/delete/:id', ['controller' => 'Sprint', 'action' => 'delete', 'params' => ['id']]);
    $router->post('sprints/start/:id', ['controller' => 'Sprint', 'action' => 'startSprint', 'params' => ['id']]);
    $router->post('sprints/complete/:id', ['controller' => 'Sprint', 'action' => 'completeSprint', 'params' => ['id']]);
    $router->post('sprints/delay/:id', ['controller' => 'Sprint', 'action' => 'delaySprint', 'params' => ['id']]);
    $router->post('sprints/cancel/:id', ['controller' => 'Sprint', 'action' => 'cancelSprint', 'params' => ['id']]);
    $router->post('sprints/add-tasks/:id', ['controller' => 'Sprint', 'action' => 'addTasks', 'params' => ['id']]);
    $router->post('sprints/create-from-milestones', ['controller' => 'Sprint', 'action' => 'createFromMilestones']);
    $router->get('api/sprints/milestones/:project_id', ['controller' => 'Sprint', 'action' => 'getMilestonesForPlanning', 'params' => ['project_id']]);
    $router->post('api/sprints/tasks-from-milestones', ['controller' => 'Sprint', 'action' => 'getTasksFromMilestones']);
    $router->get('api/sprints/project/:project_id', ['controller' => 'Sprint', 'action' => 'getProjectSprintsApi', 'params' => ['project_id']]);

    // Template Routes
    $router->get('templates', ['controller' => 'Template', 'action' => 'index']);
    $router->get('templates/page/:page', ['controller' => 'Template', 'action' => 'index', 'params' => ['page']]);
    $router->get('templates/type/:type', ['controller' => 'Template', 'action' => 'index', 'params' => ['type']]);
    $router->get('templates/view/:id', ['controller' => 'Template', 'action' => 'view', 'params' => ['id']]);
    $router->get('templates/create', ['controller' => 'Template', 'action' => 'createForm']);
    $router->post('templates/create', ['controller' => 'Template', 'action' => 'create']);
    $router->get('templates/edit/:id', ['controller' => 'Template', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('templates/update', ['controller' => 'Template', 'action' => 'update']);
    $router->post('templates/delete/:id', ['controller' => 'Template', 'action' => 'delete', 'params' => ['id']]);
    $router->get('templates/get/:id', ['controller' => 'Template', 'action' => 'getTemplate', 'params' => ['id']]);

    // Legacy Project Template Routes (for backward compatibility)
    $router->get('project-templates', ['controller' => 'Template', 'action' => 'index']);
    $router->get('project-templates/get/:id', ['controller' => 'Template', 'action' => 'getTemplate', 'params' => ['id']]);

    // Sprint Template Routes
    $router->get('sprint-templates', ['controller' => 'SprintTemplate', 'action' => 'index']);
    $router->get('sprint-templates/create', ['controller' => 'SprintTemplate', 'action' => 'createForm']);
    $router->post('sprint-templates/create', ['controller' => 'SprintTemplate', 'action' => 'create']);
    $router->get('sprint-templates/edit/:id', ['controller' => 'SprintTemplate', 'action' => 'editForm', 'params' => ['id']]);
    $router->post('sprint-templates/update', ['controller' => 'SprintTemplate', 'action' => 'update']);
    $router->post('sprint-templates/delete/:id', ['controller' => 'SprintTemplate', 'action' => 'delete', 'params' => ['id']]);
    $router->get('sprint-templates/get/:id', ['controller' => 'SprintTemplate', 'action' => 'getTemplate', 'params' => ['id']]);
    $router->get('sprint-templates/apply', ['controller' => 'SprintTemplate', 'action' => 'applyTemplate']);
    $router->get('api/sprint-templates', ['controller' => 'SprintTemplate', 'action' => 'getTemplatesApi']);

    // Settings Routes
    $router->get('settings', ['controller' => 'Settings', 'action' => 'index']);
    $router->post('settings/update', ['controller' => 'Settings', 'action' => 'update']);

    // Activity Routes
    $router->get('activity', ['controller' => 'Activity', 'action' => 'index']);

    // Time Tracking Routes
    $router->get('time-tracking', ['controller' => 'TimeTracking', 'action' => 'index']);
    $router->post('time-tracking/start', ['controller' => 'TimeTracking', 'action' => 'startTimer']);
    $router->post('time-tracking/stop', ['controller' => 'TimeTracking', 'action' => 'stopTimer']);

    // Get request URI and method
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', ltrim($uri, '/'));

    // Dispatch Request with proper error handling
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'],
        $segments
    );
} catch (\PDOException $e) {
    // Database errors
    $logger->exception($e, ['type' => 'database_error']);
    http_response_code(500);

    // Check if we should hide error details
    try {
        $securityService = \App\Services\SecurityService::getInstance();
        if ($securityService->shouldHideErrorDetails()) {
            echo "An error occurred. Please try again later.";
        } else {
            echo "Database error occurred";
        }
    } catch (\Exception $securityException) {
        $logger->exception($securityException, ['type' => 'security_service_error']);
        echo "Database error occurred";
    }
} catch (\Exception $e) {
    // Other errors
    $logger->exception($e, ['type' => 'general_error']);
    $code = $e->getCode() ?: 500;
    http_response_code($code);

    // Check if we should hide error details
    try {
        $securityService = \App\Services\SecurityService::getInstance();
        if ($securityService->shouldHideErrorDetails()) {
            echo "An error occurred. Please try again later.";
        } else {
            echo $e->getMessage();
        }
    } catch (\Exception $securityException) {
        $logger->exception($securityException, ['type' => 'security_service_error']);
        echo "An error occurred. Please try again later.";
    }
}
