<?php

// file: Controllers/TaskController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\EventDispatcher;
use App\Events\TaskAssigned;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\SecurityService;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class TaskController extends BaseController
{
    private Task $taskModel;
    private Project $projectModel;
    private User $userModel;
    private Sprint $sprintModel;

    public function __construct(
        ?Task $taskModel = null,
        ?Project $projectModel = null,
        ?User $userModel = null,
        ?Sprint $sprintModel = null
    ) {
        parent::__construct();
        $this->taskModel = $taskModel ?? new Task();
        $this->projectModel = $projectModel ?? new Project();
        $this->userModel = $userModel ?? new User();
        $this->sprintModel = $sprintModel ?? new Sprint();
    }

    /**
     * Display paginated task list with optional filters
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_tasks');

            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $limit = $this->settingsService->getResultsPerPage();

            $assignedUserId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            $projectId = filter_var($data['project_id'] ?? null, FILTER_VALIDATE_INT);

            // /tasks/unassigned
            $isUnassigned = str_contains($_SERVER['REQUEST_URI'], '/tasks/unassigned');

            if ($isUnassigned) {
                $tasks = $this->taskModel->getUnassignedTasks($limit, $page);
                $totalTasks = $this->taskModel->countUnassignedTasks();
            } elseif ($assignedUserId) {
                $tasks = $this->taskModel->getByUserId($assignedUserId, $limit, $page);
                $totalTasks = $this->taskModel->count(['assigned_to' => $assignedUserId, 'is_deleted' => 0]);
            } elseif ($projectId) {
                $tasks = $this->taskModel->getByProjectId($projectId);
                $totalTasks = count($tasks);
            } else {
                $tasks = $this->taskModel->getAllWithDetails($limit, $page);
                $totalTasks = $this->taskModel->count(['is_deleted' => 0]);
            }

            $totalPages = $totalTasks > 0 ? (int) ceil($totalTasks / $limit) : 1;
            $statuses = $this->taskModel->getTaskStatuses();
            $projects = $this->projectModel->getAllWithDetails();

            $this->render('Tasks/index', compact('tasks', 'totalTasks', 'totalPages', 'page', 'limit', 'statuses', 'projects', 'assignedUserId', 'projectId', 'isUnassigned'));
        } catch (\Exception $e) {
            error_log("Exception in TaskController::index: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching tasks.');
        }
    }

    /**
     * Display product backlog
     */
    public function backlog(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_tasks');

            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $limit = $this->settingsService->getResultsPerPage();
            $projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

            $tasks = $this->taskModel->getProductBacklog($limit, $page, $projectId);
            $totalTasks = $this->taskModel->countProductBacklog($projectId);
            $totalPages = $totalTasks > 0 ? (int) ceil($totalTasks / $limit) : 1;
            $projects = $this->projectModel->getAllWithDetails();

            $this->render('Tasks/backlog', compact('tasks', 'totalTasks', 'totalPages', 'page', 'limit', 'projects', 'projectId'));
        } catch (\Exception $e) {
            error_log("Exception in TaskController::backlog: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while fetching the backlog.');
        }
    }

    /**
     * Display sprint planning view
     */
    public function sprintPlanning(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_tasks');

            $userId = $_SESSION['user']['profile']['id'] ?? null;
            $projects = $userId ? $this->userModel->getUserProjects($userId) : [];
            $statuses = $this->taskModel->getTaskStatuses();

            $this->render('Tasks/sprint-planning', compact('projects', 'statuses'));
        } catch (\Exception $e) {
            error_log("Exception in TaskController::sprintPlanning: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while loading sprint planning.');
        }
    }

    /**
     * View task details
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_tasks');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $task = $this->taskModel->findWithDetails($id);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            $project = $this->projectModel->findWithDetails($task->project_id);
            $statuses = $this->taskModel->getTaskStatuses();
            $users = $this->userModel->getAllUsers();

            $this->render('Tasks/view', compact('task', 'project', 'statuses', 'users'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/tasks', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in TaskController::view: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while fetching task details.');
        }
    }

    /**
     * Display task creation form
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_tasks');

            $projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $parentTaskId = filter_var($_GET['parent_task_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

            $projects = $this->projectModel->getAllWithDetails();
            $statuses = $this->taskModel->getTaskStatuses();
            $users = $this->userModel->getAllUsers();
            $priorities = Priority::options();
            $taskTypes = TaskType::options();
            $formData = $_SESSION['form_data'] ?? [];
            unset($_SESSION['form_data']);

            $this->render('Tasks/create', compact('projects', 'statuses', 'users', 'priorities', 'taskTypes', 'projectId', 'parentTaskId', 'formData'));
        } catch (\Exception $e) {
            error_log("Exception in TaskController::createForm: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while loading the creation form.');
        }
    }

    /**
     * Create a new task
     */
    public function create(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->createForm($requestMethod, $data);

            return;
        }

        try {
            $this->requirePermission('create_tasks');

            $validator = new Validator($data, [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'required|integer|exists:projects,id',
                'assigned_to' => 'nullable|integer|exists:users,id',
                'priority' => 'nullable|in:none,low,medium,high',
                'status_id' => 'required|integer|exists:statuses_task,id',
                'due_date' => 'nullable|date',
                'estimated_time' => 'nullable|integer',
                'task_type' => 'required|in:story,bug,task,epic',
                'story_points' => 'nullable|integer',
                'acceptance_criteria' => 'nullable|string',
                'parent_task_id' => 'nullable|integer|exists:tasks,id',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $securityService = SecurityService::getInstance();
            $assignedTo = !empty($data['assigned_to']) ? filter_var($data['assigned_to'], FILTER_VALIDATE_INT) : null;

            $taskData = [
                'title' => htmlspecialchars($data['title']),
                'description' => isset($data['description']) ? $securityService->sanitizeRichContent($data['description']) : null,
                'project_id' => filter_var($data['project_id'], FILTER_VALIDATE_INT),
                'assigned_to' => $assignedTo,
                'priority' => $data['priority'] ?? Priority::NONE->value,
                'status_id' => filter_var($data['status_id'], FILTER_VALIDATE_INT),
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
                'estimated_time' => !empty($data['estimated_time']) ? intval($data['estimated_time']) : null,
                'task_type' => $data['task_type'] ?? TaskType::TASK->value,
                'story_points' => !empty($data['story_points']) ? intval($data['story_points']) : null,
                'acceptance_criteria' => $data['acceptance_criteria'] ?? null,
                'parent_task_id' => !empty($data['parent_task_id']) ? filter_var($data['parent_task_id'], FILTER_VALIDATE_INT) : null,
                'is_subtask' => !empty($data['parent_task_id']) ? true : false,
            ];

            $taskId = $this->taskModel->create($taskData);

            if ($assignedTo) {
                $currentUserId = $this->getCurrentUserId();
                if ($currentUserId) {
                    $event = new TaskAssigned($taskId, $assignedTo, $currentUserId);
                    EventDispatcher::getInstance()->dispatch($event);
                }
            }

            $this->redirectWithSuccess('/tasks/view/' . $taskId, 'Task created successfully.');
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            $this->redirect('/tasks/create');
        } catch (\Exception $e) {
            error_log("Exception in TaskController::create: " . $e->getMessage());
            $this->redirectWithError('/tasks/create', 'An error occurred while creating the task.');
        }
    }

    /**
     * Display task edit form
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_tasks');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $task = $this->taskModel->find($id);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            $projects = $this->projectModel->getAllWithDetails();
            $statuses = $this->taskModel->getTaskStatuses();
            $users = $this->userModel->getAllUsers();
            $priorities = Priority::options();
            $taskTypes = TaskType::options();
            $formData = $_SESSION['form_data'] ?? [];
            unset($_SESSION['form_data']);

            $this->render('Tasks/edit', compact('task', 'projects', 'statuses', 'users', 'priorities', 'taskTypes', 'formData'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/tasks', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in TaskController::editForm: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Update an existing task
     */
    public function update(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->editForm($requestMethod, $data);

            return;
        }

        try {
            $this->requirePermission('edit_tasks');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $validator = new Validator($data, [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'required|integer|exists:projects,id',
                'assigned_to' => 'nullable|integer|exists:users,id',
                'priority' => 'nullable|in:none,low,medium,high',
                'status_id' => 'required|integer|exists:statuses_task,id',
                'due_date' => 'nullable|date',
                'estimated_time' => 'nullable|integer',
                'task_type' => 'required|in:story,bug,task,epic',
                'story_points' => 'nullable|integer',
                'acceptance_criteria' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $currentTask = $this->taskModel->find($id);
            if (!$currentTask || $currentTask->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            $securityService = SecurityService::getInstance();
            $assignedTo = !empty($data['assigned_to']) ? filter_var($data['assigned_to'], FILTER_VALIDATE_INT) : null;

            $taskData = [
                'title' => htmlspecialchars($data['title']),
                'description' => isset($data['description']) ? $securityService->sanitizeRichContent($data['description']) : null,
                'project_id' => filter_var($data['project_id'], FILTER_VALIDATE_INT),
                'assigned_to' => $assignedTo,
                'priority' => $data['priority'] ?? Priority::NONE->value,
                'status_id' => filter_var($data['status_id'], FILTER_VALIDATE_INT),
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
                'estimated_time' => !empty($data['estimated_time']) ? intval($data['estimated_time']) : null,
                'task_type' => $data['task_type'] ?? TaskType::TASK->value,
                'story_points' => !empty($data['story_points']) ? intval($data['story_points']) : null,
                'acceptance_criteria' => $data['acceptance_criteria'] ?? null,
            ];

            // Set complete_date when transitioning to completed
            if (intval($data['status_id']) === TaskStatus::COMPLETED->value && !$currentTask->complete_date) {
                $taskData['complete_date'] = date('Y-m-d');
            }

            $this->taskModel->update($id, $taskData);

            // Fire assignment event if assignee changed
            if ($assignedTo && $assignedTo !== $currentTask->assigned_to) {
                $currentUserId = $this->getCurrentUserId();
                if ($currentUserId) {
                    EventDispatcher::getInstance()->dispatch(new TaskAssigned($id, $assignedTo, $currentUserId));
                }
            }

            $this->redirectWithSuccess('/tasks/view/' . $id, 'Task updated successfully.');
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header('Location: /tasks/edit/' . ($data['id'] ?? ''));
            exit;
        } catch (\Exception $e) {
            error_log("Exception in TaskController::update: " . $e->getMessage());
            $this->redirectWithError('/tasks/edit/' . ($data['id'] ?? ''), 'An error occurred while updating the task.');
        }
    }

    /**
     * Update task status via AJAX
     */
    public function updateStatus(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);

            return;
        }

        try {
            $this->requirePermission('edit_tasks');

            $input = json_decode(file_get_contents('php://input'), true);
            $taskId = filter_var($input['task_id'] ?? null, FILTER_VALIDATE_INT);
            $statusId = filter_var($input['status_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$taskId || !$statusId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'task_id and status_id are required']);

                return;
            }

            $task = $this->taskModel->find($taskId);
            if (!$task || $task->is_deleted) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);

                return;
            }

            $updateData = ['status_id' => $statusId];
            if ($statusId === TaskStatus::COMPLETED->value && !$task->complete_date) {
                $updateData['complete_date'] = date('Y-m-d');
            }

            $this->taskModel->update($taskId, $updateData);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Status updated']);
        } catch (\Exception $e) {
            error_log("Exception in TaskController::updateStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred']);
        }
    }

    /**
     * Delete task (soft delete)
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/tasks', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_tasks');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $task = $this->taskModel->find($id);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            $projectId = $task->project_id;
            $this->taskModel->update($id, ['is_deleted' => true]);

            $this->redirectWithSuccess('/tasks/project/' . $projectId, 'Task deleted successfully.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/tasks', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in TaskController::delete: " . $e->getMessage());
            $this->redirectWithError('/tasks', 'An error occurred while deleting the task.');
        }
    }

    /**
     * Start timer for a task
     */
    public function startTimer(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);

            return;
        }

        try {
            $this->requirePermission('edit_tasks');

            $taskId = filter_var($data['task_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$taskId) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $userId = $this->getCurrentUserId();
            if (!$userId) {
                throw new RuntimeException('User session invalid');
            }

            $task = $this->taskModel->find($taskId);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            $this->taskModel->update($taskId, [
                'timer_start' => date('Y-m-d H:i:s'),
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Timer started']);
        } catch (\Exception $e) {
            error_log("Exception in TaskController::startTimer: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Stop timer for a task and record the time entry
     */
    public function stopTimer(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);

            return;
        }

        try {
            $this->requirePermission('edit_tasks');

            $taskId = filter_var($data['task_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$taskId) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $userId = $this->getCurrentUserId();
            if (!$userId) {
                throw new RuntimeException('User session invalid');
            }

            $task = $this->taskModel->find($taskId);
            if (!$task || $task->is_deleted || empty($task->timer_start)) {
                throw new InvalidArgumentException('No running timer found for this task');
            }

            $elapsed = time() - strtotime($task->timer_start);
            $newTimeSpent = ($task->time_spent ?? 0) + $elapsed;

            $this->taskModel->createTimeEntry([
                'task_id' => $taskId,
                'user_id' => $userId,
                'start_time' => $task->timer_start,
                'end_time' => date('Y-m-d H:i:s'),
                'duration' => $elapsed,
            ]);

            $this->taskModel->update($taskId, [
                'timer_start' => null,
                'time_spent' => $newTimeSpent,
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'elapsed' => $elapsed, 'time_spent' => $newTimeSpent]);
        } catch (\Exception $e) {
            error_log("Exception in TaskController::stopTimer: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Add a comment to a task
     */
    public function addComment(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);

            return;
        }

        try {
            $this->requirePermission('edit_tasks');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid task ID');
            }

            $task = $this->taskModel->find($id);
            if (!$task || $task->is_deleted) {
                throw new InvalidArgumentException('Task not found');
            }

            $content = trim($data['content'] ?? '');
            if ($content === '') {
                throw new InvalidArgumentException('Comment cannot be empty');
            }

            $userId = $this->getCurrentUserId();
            if (!$userId) {
                throw new RuntimeException('User session invalid');
            }

            $commentId = $this->taskModel->addComment($id, $userId, htmlspecialchars($content));

            $input = file_get_contents('php://input');
            if (!empty($input) && json_decode($input)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'comment_id' => $commentId]);

                return;
            }

            $this->redirectWithSuccess('/tasks/view/' . $id, 'Comment added.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/tasks/view/' . ($id ?? ''), $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in TaskController::addComment: " . $e->getMessage());
            $this->redirectWithError('/tasks/view/' . ($id ?? ''), 'An error occurred while adding the comment.');
        }
    }

    /**
     * Update backlog priorities via AJAX drag-and-drop
     */
    public function updateBacklogPriorities(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);

            return;
        }

        try {
            $this->requirePermission('edit_tasks');

            $input = json_decode(file_get_contents('php://input'), true);
            $priorities = $input['priorities'] ?? [];

            if (empty($priorities) || !is_array($priorities)) {
                throw new InvalidArgumentException('priorities array is required');
            }

            foreach ($priorities as $entry) {
                $taskId = filter_var($entry['task_id'] ?? null, FILTER_VALIDATE_INT);
                $priority = filter_var($entry['backlog_priority'] ?? null, FILTER_VALIDATE_INT);

                if ($taskId && $priority !== false) {
                    $this->taskModel->update($taskId, ['backlog_priority' => $priority]);
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Priorities updated']);
        } catch (\Exception $e) {
            error_log("Exception in TaskController::updateBacklogPriorities: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred']);
        }
    }
}
