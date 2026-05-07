<?php

// file: Controllers/SprintController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Template;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class SprintController extends BaseController
{
    private Sprint $sprintModel;
    private Project $projectModel;
    private Task $taskModel;
    private Template $templateModel;

    public function __construct()
    {
        parent::__construct();
        $this->sprintModel = new Sprint();
        $this->projectModel = new Project();
        $this->taskModel = new Task();
        $this->templateModel = new Template();
    }

    /**
     * Display paginated list of sprints
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $project_id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $settingsService = \App\Services\SettingsService::getInstance();
            $limit = $settingsService->getResultsPerPage();

            if (!empty($project_id)) {
                $project = $this->projectModel->findWithDetails($project_id);
                if (!$project || $project->is_deleted) {
                    throw new InvalidArgumentException('Project not found');
                }
                // Get sprints only for this specific project
                $sprints = $this->sprintModel->getByProjectId($project_id);
            } else {
                $projects = $this->projectModel->getAllWithDetails($limit, $page);
                // Get all sprints when no specific project is selected
                $sprints = $this->sprintModel->getAllWithTasks($limit, $page);

                // Calculate sprint counts for each project for the project selection view
                $projectSprintCounts = [];
                if (!empty($projects)) {
                    foreach ($projects as $proj) {
                        $projectSprints = $this->sprintModel->getByProjectId($proj->id);
                        $counts = [
                            'active' => 0,
                            'completed' => 0,
                            'planning' => 0,
                            'total' => 0,
                        ];

                        foreach ($projectSprints as $sprint) {
                            $counts['total']++;
                            switch ($sprint->status_id) {
                                case 1: // Planning
                                    $counts['planning']++;

                                    break;
                                case 2: // Active
                                    $counts['active']++;

                                    break;
                                case 4: // Completed
                                    $counts['completed']++;

                                    break;
                            }
                        }

                        $projectSprintCounts[$proj->id] = $counts;
                    }
                }
            }

            $project = $project ?? null;
            $projects = $projects ?? [];
            $projectSprintCounts = $projectSprintCounts ?? [];
            $sprints = $sprints ?? [];
            $this->render('Sprints/index', compact('sprints', 'projects', 'project', 'projectSprintCounts', 'page', 'limit'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in SprintController::index: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching sprints.');
        }
    }

    /**
     * View sprint details
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            $tasks = $this->sprintModel->getSprintTasks($id);
            $project = $this->projectModel->find($sprint->project_id);

            // Add hierarchical task data
            $sprint->hierarchy = $this->sprintModel->getSprintHierarchy($id);

            $this->render('Sprints/view', compact('sprint', 'project', 'tasks'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in SprintController::view: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching sprint details.');
        }
    }

    /**
     * Display current sprint dashboard showing all active sprints user is involved in
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function current(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $userId = $_SESSION['user']['profile']['id'] ?? null;
            if (!$userId) {
                throw new \RuntimeException('User session invalid');
            }

            // Get all active sprints where user has tasks assigned
            $activeSprintsWithTasks = $this->sprintModel->getActiveSprintsForUser($userId);

            // Get all active sprints in projects user has access to (even if no tasks assigned)
            $activeSprintsInProjects = $this->sprintModel->getActiveSprintsInUserProjects($userId);

            // Merge and deduplicate sprints
            $allActiveSprints = [];
            $sprintIds = [];

            foreach ($activeSprintsWithTasks as $sprint) {
                if (!in_array($sprint->id, $sprintIds)) {
                    $allActiveSprints[] = $sprint;
                    $sprintIds[] = $sprint->id;
                }
            }

            foreach ($activeSprintsInProjects as $sprint) {
                if (!in_array($sprint->id, $sprintIds)) {
                    $allActiveSprints[] = $sprint;
                    $sprintIds[] = $sprint->id;
                }
            }

            // Get detailed information for each sprint
            $sprintDetails = [];
            foreach ($allActiveSprints as $sprint) {
                $tasks = $this->sprintModel->getSprintTasks($sprint->id);
                $userTasks = array_filter($tasks, function ($task) use ($userId) {
                    return $task->assigned_to == $userId;
                });

                $sprintDetails[] = [
                    'sprint' => $sprint,
                    'all_tasks' => $tasks,
                    'user_tasks' => $userTasks,
                    'progress' => $this->calculateSprintProgress($tasks),
                    'burndown_data' => $this->getBurndownData($sprint->id),
                    'project' => $this->projectModel->find($sprint->project_id),
                ];
            }

            // Get today's focus items across all active sprints
            $todaysFocus = !empty($sprintIds) ? $this->getTodaysFocusItems($userId, $sprintIds) : [];

            $this->render('Sprints/current', compact('sprintDetails', 'todaysFocus'));
        } catch (\Exception $e) {
            error_log("Exception in SprintController::current: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while fetching current sprint information.');
        }
    }

    /**
     * Display sprint board (Kanban view)
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function board(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->findWithDetails($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            // Get project details
            $project = $this->projectModel->findWithDetails($sprint->project_id);
            if (!$project || $project->is_deleted) {
                throw new InvalidArgumentException('Project not found');
            }

            // Get sprint tasks organized by status
            $tasks = $this->sprintModel->getSprintTasks($id);

            // Get all task statuses for the board columns
            $taskStatuses = $this->taskModel->getTaskStatuses();

            // Organize tasks by status
            $tasksByStatus = [];
            foreach ($taskStatuses as $status) {
                $tasksByStatus[$status->id] = [
                    'status' => $status,
                    'tasks' => [],
                ];
            }

            foreach ($tasks as $task) {
                if (isset($tasksByStatus[$task->status_id])) {
                    $tasksByStatus[$task->status_id]['tasks'][] = $task;
                }
            }

            // Get sprint statistics
            $sprintStats = $this->calculateSprintStats($tasks);

            $this->render('Sprints/board', compact('sprint', 'project', 'tasksByStatus', 'sprintStats'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in SprintController::board: " . $e->getMessage());
            $this->redirectWithError('/sprints', 'An error occurred while loading the sprint board.');
        }
    }

    /**
     * Display sprint planning page
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function planning(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $userId = $_SESSION['user']['profile']['id'] ?? null;
            if (!$userId) {
                throw new \RuntimeException('User session invalid');
            }

            // Get user's projects for project selection
            $userModel = new \App\Models\User();
            $userProjects = $userModel->getUserProjects($userId);

            // Get selected project if provided
            $selectedProjectId = filter_var($data['project_id'] ?? null, FILTER_VALIDATE_INT);
            $selectedProject = null;
            $productBacklog = [];
            $activeSprints = [];
            $sprintCapacity = [];

            if ($selectedProjectId) {
                $selectedProject = $this->projectModel->findWithDetails($selectedProjectId);
                if ($selectedProject && !$selectedProject->is_deleted) {
                    // Get product backlog (unassigned tasks)
                    $productBacklog = $this->taskModel->getProductBacklog(50, 1, $selectedProjectId);

                    // Get active sprints for this project
                    $activeSprints = $this->sprintModel->getByProjectId($selectedProjectId);
                    $activeSprints = array_filter($activeSprints, function ($sprint) {
                        return $sprint->status_id == 2; // Active status
                    });

                    // Calculate sprint capacity based on settings
                    $settingsService = \App\Services\SettingsService::getInstance();
                    $sprintSettings = $settingsService->getSprintSettings();
                    $sprintCapacity = [
                        'hours' => $sprintSettings['team_capacity_hours'],
                        'story_points' => $sprintSettings['team_capacity_story_points'],
                        'estimation_method' => $sprintSettings['estimation_method'],
                        'team_size' => $sprintSettings['team_size'] ?? 5,
                    ];
                }
            }

            $this->render('Sprints/planning', compact('userProjects', 'selectedProject', 'selectedProjectId', 'productBacklog', 'activeSprints', 'sprintCapacity'));
        } catch (\Exception $e) {
            error_log("Exception in SprintController::planning: " . $e->getMessage());
            $this->redirectWithError('/sprints', 'An error occurred while loading sprint planning.');
        }
    }

    /**
     * Display sprint creation form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_sprints');

            // Get project ID from route parameter or query parameter
            $projectId = filter_var($data['id'] ?? $data['project_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$projectId) {
                throw new InvalidArgumentException('Project ID is required');
            }

            $project = $this->projectModel->find($projectId);
            if (!$project || $project->is_deleted) {
                throw new InvalidArgumentException('Project not found');
            }

            // Get all tasks from the project that could be added to the sprint
            $project_tasks = $this->taskModel->getByProjectId($projectId);

            // Get sprint statuses
            $sprint_statuses = $this->sprintModel->getSprintStatuses();

            // Get company ID from project
            $companyId = $project->company_id ?? null;
            // Load templates available for this company or global templates
            $templates = $this->templateModel->getAvailableTemplates('sprint', $companyId);

            $this->render('Sprints/create', compact('templates', 'companyId', 'sprint_statuses', 'project_tasks'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in SprintController::createForm: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while loading the sprint creation form.');
        }
    }

    /**
     * Create a new sprint from planning page
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createFromPlanning(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_sprints');

            // Validate required fields
            if (empty($data['name']) || empty($data['project_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Sprint name and project are required']);

                return;
            }

            $projectId = filter_var($data['project_id'], FILTER_VALIDATE_INT);
            if (!$projectId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid project ID']);

                return;
            }

            // Verify user has access to the project
            $project = $this->projectModel->findWithDetails($projectId);
            if (!$project || $project->is_deleted) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Project not found']);

                return;
            }

            // Get sprint settings for default length
            $settingsService = \App\Services\SettingsService::getInstance();
            $sprintSettings = $settingsService->getSprintSettings();

            // Calculate dates
            $startDate = new \DateTime();
            $endDate = clone $startDate;
            $endDate->add(new \DateInterval('P' . $sprintSettings['default_sprint_length'] . 'D'));

            // Create sprint
            $sprintData = [
                'name' => trim($data['name']),
                'description' => trim($data['goal'] ?? ''),
                'project_id' => $projectId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status_id' => 1, // Planning status
                'sprint_goal' => trim($data['goal'] ?? ''),
            ];

            $sprintId = $this->sprintModel->create($sprintData);

            if (!$sprintId) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create sprint']);

                return;
            }

            // Add tasks to sprint if provided
            if (!empty($data['task_ids'])) {
                $taskIds = json_decode($data['task_ids'], true);
                if (is_array($taskIds) && !empty($taskIds)) {
                    $this->sprintModel->addTasks($sprintId, $taskIds);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Sprint created successfully',
                'sprint_id' => $sprintId,
            ]);

        } catch (\Exception $e) {
            error_log("Exception in SprintController::createFromPlanning: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred while creating the sprint']);
        }
    }

    /**
     * Create new sprint
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function create(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->createForm($requestMethod, $data);

            return;
        }

        try {
            $this->requirePermission('create_sprints');

            $validator = new Validator($data, [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'required|integer|exists:projects,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'status_id' => 'required|integer|exists:statuses_sprint,id',
                'sprint_type' => 'required|in:project,milestone',
                'milestone_ids' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $sprintData = [
                'name' => htmlspecialchars($data['name']),
                'description' => isset($data['description']) ?
                    htmlspecialchars($data['description']) : null,
                'project_id' => filter_var($data['project_id'], FILTER_VALIDATE_INT),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status_id' => filter_var($data['status_id'], FILTER_VALIDATE_INT),
            ];

            $sprintType = $data['sprint_type'] ?? 'project';
            $milestoneIds = $data['milestone_ids'] ?? [];

            if ($sprintType === 'milestone' && !empty($milestoneIds)) {
                // Create milestone-based sprint
                $milestoneIds = array_map('intval', $milestoneIds);
                $taskIds = !empty($data['tasks']) ? array_map('intval', $data['tasks']) : [];
                $sprintId = $this->sprintModel->createFromMilestones($sprintData, $milestoneIds, $taskIds);
            } else {
                // Create project-based sprint
                $sprintId = $this->sprintModel->create($sprintData);

                // Add tasks to sprint if provided
                if (!empty($data['tasks'])) {
                    $taskIds = array_map('intval', $data['tasks']);
                    $this->sprintModel->addTasks($sprintId, $taskIds);
                }
            }

            $_SESSION['success'] = 'Sprint created successfully.';
            header('Location: /sprints/view/' . $sprintId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header('Location: /sprints/create/' . $data['project_id']);
            exit;
        } catch (\Exception $e) {
            error_log("Exception in SprintController::create: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while creating the sprint.';
            header('Location: /sprints/create/' . $data['project_id']);
            exit;
        }
    }

    /**
     * Display sprint edit form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            $project = $this->projectModel->find($sprint->project_id);
            if (!$project || $project->is_deleted) {
                throw new InvalidArgumentException('Associated project not found');
            }

            // Get all tasks from the project that could be added to the sprint
            $projectTasks = $this->taskModel->getByProjectId($sprint->project_id);

            // Get tasks that are already in the sprint
            $sprintTasks = $this->sprintModel->getSprintTasks($id);
            $sprintTaskIds = array_map(function ($task) {
                return $task->id;
            }, $sprintTasks);

            // Get sprint statuses
            $statuses = $this->sprintModel->getSprintStatuses();

            // Get company ID from project
            $companyId = $project->company_id ?? null;
            // Load templates available for this company or global templates
            $templates = $this->templateModel->getAvailableTemplates('sprint', $companyId);

            $this->render('Sprints/edit', compact('sprint', 'project', 'templates', 'companyId', 'statuses', 'projectTasks', 'sprintTasks', 'sprintTaskIds'));
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /sprints/view/' . $id);
            exit;
        } catch (\Exception $e) {
            error_log("Exception in SprintController::editForm: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Update existing sprint
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function update(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->editForm($requestMethod, $data);

            return;
        }

        try {
            $this->requirePermission('edit_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $validator = new Validator($data, [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'status_id' => 'required|integer|exists:statuses_sprint,id',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            // Get the existing sprint to preserve project_id
            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            $sprintData = [
                'name' => htmlspecialchars($data['name']),
                'description' => isset($data['description']) ?
                    htmlspecialchars($data['description']) : null,
                'project_id' => $sprint->project_id, // Preserve project_id
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status_id' => filter_var($data['status_id'], FILTER_VALIDATE_INT),
            ];

            $this->sprintModel->update($id, $sprintData);

            // Update tasks in sprint if provided
            if (isset($data['tasks'])) {
                $taskIds = !empty($data['tasks']) ? array_map('intval', $data['tasks']) : [];
                $this->sprintModel->addTasks($id, $taskIds);
            }

            $_SESSION['success'] = 'Sprint updated successfully.';
            header('Location: /sprints/view/' . $id);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header("Location: /sprints/edit/{$id}");
            exit;
        } catch (\Exception $e) {
            error_log("Exception in SprintController::update: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating the sprint.';
            header("Location: /sprints/edit/{$id}");
            exit;
        }
    }

    /**
     * Delete sprint (soft delete)
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            // Store project_id for redirection after deletion
            $projectId = $sprint->project_id;

            // Get sprint tasks to check for active tasks
            $sprintTasks = $this->sprintModel->getSprintTasks($id);
            $activeTasks = array_filter($sprintTasks, function ($task) {
                // Not completed or closed
                return $task->status_id != TaskStatus::COMPLETED->value
                    && $task->status_id != TaskStatus::CLOSED->value;
            });

            // Only allow deletion if sprint has no active tasks or is not active
            if ($sprint->status_id == SprintStatus::ACTIVE->value && !empty($activeTasks)) {
                throw new InvalidArgumentException('Cannot delete active sprint with incomplete tasks');
            }

            $this->sprintModel->update($id, ['is_deleted' => true]);

            $_SESSION['success'] = 'Sprint deleted successfully.';
            header('Location: /sprints/view/' . $projectId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /sprints/view/' . ($id ?? ''));
            exit;
        } catch (\Exception $e) {
            error_log("Error in SprintController::delete: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while deleting the sprint.';
            header('Location: /sprints/view/' . ($id ?? ''));
            exit;
        }
    }

    /**
     * Add tasks to sprint
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function addTasks(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints', 'Invalid request method.');
        }

        try {
            $this->requirePermission('edit_sprints');

            $sprintId = filter_var($data['sprint_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$sprintId) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            if (empty($data['task_ids']) || !is_array($data['task_ids'])) {
                throw new InvalidArgumentException('No tasks selected');
            }

            $taskIds = array_map('intval', $data['task_ids']);
            $this->sprintModel->addTasks($sprintId, $taskIds);

            $_SESSION['success'] = 'Tasks added to sprint successfully.';
            header('Location: /sprints/view/' . $sprintId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /sprints/view/' . ($sprintId ?? ''));
            exit;
        } catch (\Exception $e) {
            error_log("Error in SprintController::addTasks: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while adding tasks to the sprint.';
            header('Location: /sprints/view/' . ($sprintId ?? ''));
            exit;
        }
    }

    /**
     * Assign task to sprint via AJAX
     * @param string $requestMethod
     * @param array $data
     */
    public function assignTask(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);

            return;
        }

        try {
            $this->requirePermission('edit_sprints');

            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['task_id']) || !isset($input['sprint_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing task_id or sprint_id']);

                return;
            }

            $taskId = intval($input['task_id']);
            $sprintId = intval($input['sprint_id']);
            $includeSubtasks = filter_var($input['include_subtasks'] ?? true, FILTER_VALIDATE_BOOLEAN);

            // Validate that the task exists and is not already assigned to an active sprint
            $task = $this->taskModel->find($taskId);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);

                return;
            }

            // Validate that the sprint exists and is active
            $sprint = $this->sprintModel->find($sprintId);
            if (!$sprint) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sprint not found']);

                return;
            }

            // Check if task is already assigned to an active sprint
            $existingAssignment = $this->sprintModel->getTaskSprint($taskId);
            if ($existingAssignment) {
                echo json_encode(['success' => false, 'message' => 'Task is already assigned to an active sprint']);

                return;
            }

            // Assign task to sprint (with optional subtask inheritance)
            $success = $this->sprintModel->assignTask($sprintId, $taskId, $includeSubtasks);

            if ($success) {
                // Add history entry for task assignment
                $this->taskModel->addHistoryEntry(
                    $taskId,
                    $_SESSION['user']['id'],
                    'assigned_to_sprint',
                    'Sprint',
                    null,
                    $sprint->name
                );

                $message = 'Task assigned to sprint successfully';
                if ($includeSubtasks) {
                    // Check if task has subtasks
                    $subtaskCount = $this->taskModel->getSubtaskCount($taskId);
                    if ($subtaskCount > 0) {
                        $message .= " (including {$subtaskCount} subtasks)";
                    }
                }

                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign task to sprint']);
            }

        } catch (\Exception $e) {
            error_log("Error in SprintController::assignTask: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    /**
     * Calculate sprint progress based on task completion
     * @param array $tasks
     * @return array
     */
    private function calculateSprintProgress(array $tasks): array
    {
        $totalTasks = count($tasks);
        if ($totalTasks === 0) {
            return [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'in_progress_tasks' => 0,
                'not_started_tasks' => 0,
                'completion_percentage' => 0,
            ];
        }

        $completed = 0;
        $inProgress = 0;
        $notStarted = 0;

        foreach ($tasks as $task) {
            switch ($task->status_id) {
                case 6: // Completed
                    $completed++;

                    break;
                case 3: // In Progress
                case 4: // Under Review
                    $inProgress++;

                    break;
                default: // Not Started, Blocked, etc.
                    $notStarted++;

                    break;
            }
        }

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completed,
            'in_progress_tasks' => $inProgress,
            'not_started_tasks' => $notStarted,
            'completion_percentage' => round(($completed / $totalTasks) * 100, 1),
        ];
    }

    /**
     * Get burndown data for a sprint
     * @param int $sprintId
     * @return array
     */
    private function getBurndownData(int $sprintId): array
    {
        // This is a placeholder for burndown chart data
        // In a full implementation, this would calculate daily progress
        return [
            'ideal_line' => [],
            'actual_line' => [],
            'dates' => [],
        ];
    }

    /**
     * Get today's focus items for user across active sprints
     * @param int $userId
     * @param array $sprintIds
     * @return array
     */
    private function getTodaysFocusItems(int $userId, array $sprintIds): array
    {
        if (empty($sprintIds)) {
            return [];
        }

        // Get tasks assigned to user in active sprints that are in progress or high priority
        $placeholders = str_repeat('?,', count($sprintIds) - 1) . '?';
        $sql = "SELECT t.*, p.name as project_name, s.name as sprint_name
                FROM tasks t
                JOIN sprints s ON t.sprint_id = s.id
                JOIN projects p ON t.project_id = p.id
                WHERE t.assigned_to = ?
                AND t.sprint_id IN ($placeholders)
                AND t.status_id IN (2, 3, 4) -- Not Started, In Progress, Under Review
                AND t.is_deleted = 0
                ORDER BY
                    CASE
                        WHEN t.priority = 'high' THEN 1
                        WHEN t.priority = 'medium' THEN 2
                        WHEN t.priority = 'low' THEN 3
                        ELSE 4
                    END,
                    t.due_date ASC
                LIMIT 10";

        try {
            $params = array_merge([$userId], $sprintIds);
            $db = \App\Core\Database::getInstance();
            $stmt = $db->executeQuery($sql, $params);

            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error getting today's focus items: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Calculate sprint statistics
     * @param array $tasks
     * @return array
     */
    private function calculateSprintStats(array $tasks): array
    {
        $stats = [
            'total_tasks' => count($tasks),
            'completed_tasks' => 0,
            'in_progress_tasks' => 0,
            'blocked_tasks' => 0,
            'not_started_tasks' => 0,
            'total_story_points' => 0,
            'completed_story_points' => 0,
            'total_estimated_hours' => 0,
            'completed_estimated_hours' => 0,
        ];

        foreach ($tasks as $task) {
            // Count story points and hours
            if (isset($task->story_points)) {
                $stats['total_story_points'] += $task->story_points;
                if ($task->status_id == 6) { // Completed
                    $stats['completed_story_points'] += $task->story_points;
                }
            }

            if (isset($task->estimated_time)) {
                $stats['total_estimated_hours'] += $task->estimated_time / 3600; // Convert seconds to hours
                if ($task->status_id == 6) { // Completed
                    $stats['completed_estimated_hours'] += $task->estimated_time / 3600;
                }
            }

            // Count tasks by status
            switch ($task->status_id) {
                case 6: // Completed
                    $stats['completed_tasks']++;

                    break;
                case 3: // In Progress
                case 4: // Under Review
                    $stats['in_progress_tasks']++;

                    break;
                case 5: // Blocked
                    $stats['blocked_tasks']++;

                    break;
                default: // Not Started, etc.
                    $stats['not_started_tasks']++;

                    break;
            }
        }

        // Calculate percentages
        $stats['completion_percentage'] = $stats['total_tasks'] > 0
            ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1)
            : 0;

        $stats['story_points_percentage'] = $stats['total_story_points'] > 0
            ? round(($stats['completed_story_points'] / $stats['total_story_points']) * 100, 1)
            : 0;

        $stats['hours_percentage'] = $stats['total_estimated_hours'] > 0
            ? round(($stats['completed_estimated_hours'] / $stats['total_estimated_hours']) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Create sprint from milestone planning
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createFromMilestones(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints/planning', 'Invalid request method.');
        }

        try {
            $this->requirePermission('create_sprints');

            // Get JSON input for AJAX requests
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                $data = array_merge($data, $input);
            }

            $validator = new \App\Utils\Validator($data, [
                'name' => 'required|string|max:255',
                'project_id' => 'required|integer|exists:projects,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'milestone_ids' => 'nullable|array',
                'task_ids' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                throw new \InvalidArgumentException(implode(', ', $validator->errors()));
            }

            // Prepare sprint data
            $sprintData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sprint_goal' => $data['goal'] ?? null,
                'project_id' => $data['project_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status_id' => 1, // Planning status
            ];

            $milestoneIds = $data['milestone_ids'] ?? [];
            $taskIds = $data['task_ids'] ?? [];

            // Create sprint with milestone associations
            $sprintId = $this->sprintModel->createFromMilestones($sprintData, $milestoneIds, $taskIds);

            // Return JSON response for AJAX requests
            if (!empty($input)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Sprint created successfully',
                    'sprint_id' => $sprintId,
                ]);

                return;
            }

            $_SESSION['success'] = 'Sprint created successfully from milestones.';
            header('Location: /sprints/view/' . $sprintId);
            exit;

        } catch (\InvalidArgumentException $e) {
            $errorMessage = 'Validation error: ' . $e->getMessage();

            if (!empty($input)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $errorMessage]);

                return;
            }

            $this->redirectWithError('/sprints/planning', $errorMessage);
        } catch (\Exception $e) {
            error_log("Exception in SprintController::createFromMilestones: " . $e->getMessage());
            $errorMessage = 'An error occurred while creating the sprint.';

            if (!empty($input)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $errorMessage]);

                return;
            }

            $this->redirectWithError('/sprints/planning', $errorMessage);
        }
    }

    /**
     * Get milestones for sprint planning (API endpoint)
     * @param string $requestMethod
     * @param array $data
     */
    public function getMilestonesForPlanning(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $projectId = filter_var($data['project_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$projectId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid project ID']);

                return;
            }

            $type = $_GET['type'] ?? 'all'; // 'epic', 'milestone', or 'all'

            $milestoneModel = new \App\Models\Milestone();
            $milestones = $milestoneModel->getAvailableForSprint($projectId, $type);

            echo json_encode([
                'success' => true,
                'milestones' => $milestones,
            ]);

        } catch (\Exception $e) {
            error_log("Exception in SprintController::getMilestonesForPlanning: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching milestones']);
        }
    }

    /**
     * Get tasks from milestones (API endpoint)
     * @param string $requestMethod
     * @param array $data
     */
    public function getTasksFromMilestones(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_tasks');

            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            $milestoneIds = $input['milestone_ids'] ?? [];

            if (empty($milestoneIds)) {
                echo json_encode(['success' => true, 'tasks' => []]);

                return;
            }

            $tasks = $this->sprintModel->getTasksFromMilestones($milestoneIds);

            echo json_encode([
                'success' => true,
                'tasks' => $tasks,
            ]);

        } catch (\Exception $e) {
            error_log("Exception in SprintController::getTasksFromMilestones: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching tasks']);
        }
    }

    /**
     * Start a sprint (transition Planning → Active)
     * @param string $requestMethod
     * @param array $data
     */
    public function startSprint(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints', 'Invalid request method.');
        }

        try {
            $this->requirePermission('edit_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            if ($sprint->status_id !== SprintStatus::PLANNING->value) {
                throw new InvalidArgumentException('Only sprints in Planning status can be started');
            }

            $this->sprintModel->update($id, ['status_id' => SprintStatus::ACTIVE->value]);

            $this->redirectWithSuccess('/sprints/view/' . $id, 'Sprint started successfully.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error in SprintController::startSprint: " . $e->getMessage());
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), 'An error occurred while starting the sprint.');
        }
    }

    /**
     * Complete a sprint (transition Active/Review → Completed)
     * @param string $requestMethod
     * @param array $data
     */
    public function completeSprint(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints', 'Invalid request method.');
        }

        try {
            $this->requirePermission('edit_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            $allowedFrom = [SprintStatus::ACTIVE->value, SprintStatus::REVIEW->value];
            if (!in_array($sprint->status_id, $allowedFrom, true)) {
                throw new InvalidArgumentException('Only active or in-review sprints can be completed');
            }

            $this->sprintModel->update($id, ['status_id' => SprintStatus::COMPLETED->value]);

            $this->redirectWithSuccess('/sprints/view/' . $id, 'Sprint completed successfully.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error in SprintController::completeSprint: " . $e->getMessage());
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), 'An error occurred while completing the sprint.');
        }
    }

    /**
     * Delay a sprint (transition Active → Delayed)
     * @param string $requestMethod
     * @param array $data
     */
    public function delaySprint(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints', 'Invalid request method.');
        }

        try {
            $this->requirePermission('edit_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            if ($sprint->status_id !== SprintStatus::ACTIVE->value) {
                throw new InvalidArgumentException('Only active sprints can be delayed');
            }

            $this->sprintModel->update($id, ['status_id' => SprintStatus::DELAYED->value]);

            $this->redirectWithSuccess('/sprints/view/' . $id, 'Sprint marked as delayed.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error in SprintController::delaySprint: " . $e->getMessage());
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), 'An error occurred while delaying the sprint.');
        }
    }

    /**
     * Cancel a sprint (any non-final status → Cancelled)
     * @param string $requestMethod
     * @param array $data
     */
    public function cancelSprint(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/sprints', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_sprints');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid sprint ID');
            }

            $sprint = $this->sprintModel->find($id);
            if (!$sprint || $sprint->is_deleted) {
                throw new InvalidArgumentException('Sprint not found');
            }

            if (SprintStatus::tryFrom($sprint->status_id)?->isFinal()) {
                throw new InvalidArgumentException('Sprint is already in a final state and cannot be cancelled');
            }

            $this->sprintModel->update($id, ['status_id' => SprintStatus::CANCELLED->value]);

            $projectId = $sprint->project_id;
            $this->redirectWithSuccess('/sprints/project/' . $projectId, 'Sprint cancelled successfully.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error in SprintController::cancelSprint: " . $e->getMessage());
            $this->redirectWithError('/sprints/view/' . ($id ?? ''), 'An error occurred while cancelling the sprint.');
        }
    }

    /**
     * Get sprints for a project (API endpoint)
     * @param string $requestMethod
     * @param array $data
     */
    public function getProjectSprintsApi(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_sprints');

            $projectId = filter_var($data['project_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$projectId) {
                throw new InvalidArgumentException('Invalid project ID');
            }

            // Get sprints for the project
            $sprints = $this->sprintModel->getByProjectId($projectId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'sprints' => $sprints,
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
