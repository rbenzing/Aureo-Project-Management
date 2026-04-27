<?php

// file: Controllers/MilestoneController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Template;
use App\Services\SecurityService;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class MilestoneController extends BaseController
{
    private Milestone $milestoneModel;
    private Project $projectModel;
    private Template $templateModel;

    public function __construct(
        ?Milestone $milestoneModel = null,
        ?Project $projectModel = null,
        ?Template $templateModel = null
    ) {
        parent::__construct();
        $this->milestoneModel = $milestoneModel ?? new Milestone();
        $this->projectModel = $projectModel ?? new Project();
        $this->templateModel = $templateModel ?? new Template();
    }

    /**
     * Display paginated list of milestones
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_milestones');

            $project_id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $settingsService = \App\Services\SettingsService::getInstance();
            $limit = $settingsService->getResultsPerPage();

            if (!empty($project_id)) {
                $project = $this->projectModel->findWithDetails($project_id);
                if (!$project || $project->is_deleted) {
                    throw new InvalidArgumentException('Project not found');
                }
                // Get milestones only for this specific project
                $milestones = $this->milestoneModel->getByProjectId($project_id);
            } else {
                $projects = $this->projectModel->getAllWithDetails($limit, $page);
                // Get all milestones when no specific project is selected
                $milestones = $this->milestoneModel->getAllWithProgress($limit, $page);
            }

            $totalMilestones = $this->milestoneModel->count(['is_deleted' => 0]);
            $totalPages = ceil($totalMilestones / $limit);

            $this->render('Milestones/index', compact('totalPages', 'totalMilestones'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/milestones', $e->getMessage());
        } catch (\Exception $e) {
            $securityService = SecurityService::getInstance();
            $this->redirectWithError('/dashboard', $securityService->handleError($e, 'MilestoneController::index', 'An error occurred while fetching milestones.'));
        }
    }

    /**
     * View milestone details
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_milestones');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid milestone ID');
            }

            $milestone = $this->milestoneModel->findWithDetails($id);
            if (!$milestone || $milestone->is_deleted) {
                throw new InvalidArgumentException('Milestone not found');
            }

            $project = $this->projectModel->findWithDetails($milestone->project_id);
            if (!$project) {
                throw new RuntimeException('Associated project not found');
            }

            // Initialize optional variables
            $epic = null;
            $relatedMilestones = [];
            $relatedSprints = [];

            // If this is a milestone under an epic, fetch the epic
            if ($milestone->epic_id) {
                $epic = $this->milestoneModel->find($milestone->epic_id);
            }

            // If this is an epic, fetch its milestones
            if ($milestone->milestone_type === 'epic') {
                $relatedMilestones = $this->milestoneModel->getEpicMilestones($id);
            }

            // Get related sprints (sprints that share tasks or are in the same project)
            // Use try-catch to ensure this new feature doesn't break existing functionality
            try {
                $relatedSprints = $this->milestoneModel->getRelatedSprints($id);
            } catch (\Exception $e) {
                error_log("Error fetching related sprints for milestone {$id}: " . $e->getMessage());
                $relatedSprints = []; // Default to empty array if there's an error
            }

            $this->render('Milestones/view');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/milestones', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::view: " . $e->getMessage());
            $this->redirectWithError('/milestones', 'An error occurred while fetching milestone details.');
        }
    }

    /**
     * Display milestone creation form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_milestones');

            $projectsResult = $this->projectModel->getAll(['is_deleted' => 0], 1, 1000);
            $projects = $projectsResult['records'];
            $statuses = $this->milestoneModel->getMilestoneStatuses();
            $projectId = filter_var($data['project_id'] ?? 0, FILTER_VALIDATE_INT);
            $epicId = filter_var($data['epic_id'] ?? 0, FILTER_VALIDATE_INT);
            $epics = $this->milestoneModel->getProjectEpics($projectId ?: 0);

            // Store the selected values for the view
            $selectedProjectId = $projectId ?: 0;
            $selectedEpicId = $epicId ?: 0;

            // Get company ID from user session if available
            $companyId = $_SESSION['user']['profile']['company_id'] ?? null;
            // Load templates available for this company or global templates
            $templates = $this->templateModel->getAvailableTemplates('milestone', $companyId);

            $this->render('Milestones/create', compact('templates', 'companyId', 'selectedEpicId', 'selectedProjectId', 'epics', 'epicId', 'projectId', 'statuses', 'projects', 'projectsResult'));
        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::createForm: " . $e->getMessage());
            $this->redirectWithError('/milestones', 'An error occurred while loading the creation form.');
        }
    }

    /**
     * Create new milestone
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
            $this->requirePermission('create_milestones');

            $validator = new Validator($data, [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'milestone_type' => 'required|in:epic,milestone',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after:start_date',
                'status_id' => 'required|integer|exists:statuses_milestone,id',
                'project_id' => 'required|integer|exists:projects,id',
                'epic_id' => 'nullable|integer|exists:milestones,id',
            ]);

            // If this is an epic itself, check for circular references
            if (isset($data['milestone_type']) && $data['milestone_type'] === 'epic' && isset($id)) {
                $this->milestoneModel->checkCircularEpicReference($id, $data['epic_id']);
            }

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $milestoneData = [
                'title' => htmlspecialchars($data['title']),
                'description' => isset($data['description']) ?
                    htmlspecialchars($data['description']) : null,
                'milestone_type' => $data['milestone_type'],
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status_id' => filter_var($data['status_id'], FILTER_VALIDATE_INT),
                'project_id' => filter_var($data['project_id'], FILTER_VALIDATE_INT),
                'epic_id' => isset($data['epic_id']) ?
                    filter_var($data['epic_id'], FILTER_VALIDATE_INT) : null,
            ];

            $milestoneId = $this->milestoneModel->create($milestoneData);

            $_SESSION['success'] = 'Milestone created successfully.';
            header('Location: /milestones/view/' . $milestoneId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            $this->redirect('/milestones/create');
        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::create: " . $e->getMessage());
            $this->redirectWithError('/milestones/create', 'An error occurred while creating the milestone.');
        }
    }

    /**
     * Display milestone edit form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_milestones');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid milestone ID');
            }

            $milestone = $this->milestoneModel->find($id);
            if (!$milestone || $milestone->is_deleted) {
                throw new InvalidArgumentException('Milestone not found');
            }

            $projectsResult = $this->projectModel->getAll(['is_deleted' => 0], 1, 1000);
            $projects = $projectsResult['records'];
            $statuses = $this->milestoneModel->getMilestoneStatuses();
            $epics = $this->milestoneModel->getProjectEpics($milestone->project_id);

            // Get company ID from milestone's project
            $companyId = null;
            if ($milestone->project_id) {
                $project = $this->projectModel->find($milestone->project_id);
                $companyId = $project->company_id ?? null;
            }
            // Load templates available for this company or global templates
            $templates = $this->templateModel->getAvailableTemplates('milestone', $companyId);

            $this->render('Milestones/edit', compact('templates'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/milestones', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::editForm: " . $e->getMessage());
            $this->redirectWithError('/milestones', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Update existing milestone
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
            $this->requirePermission('edit_milestones');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid milestone ID');
            }

            $validator = new Validator($data, [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'milestone_type' => 'required|in:epic,milestone',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after:start_date',
                'status_id' => 'required|integer|exists:statuses_milestone,id',
                'project_id' => 'required|integer|exists:projects,id',
                'epic_id' => 'nullable|integer|exists:milestones,id',
            ]);

            // If this is an epic itself, check for circular references
            if (isset($data['milestone_type']) && $data['milestone_type'] === 'epic' && isset($id)) {
                $this->milestoneModel->checkCircularEpicReference($id, $data['epic_id']);
            }

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $milestoneData = [
                'id' => $id,
                'title' => htmlspecialchars($data['title']),
                'description' => isset($data['description']) ?
                    htmlspecialchars($data['description']) : null,
                'milestone_type' => $data['milestone_type'],
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status_id' => filter_var($data['status_id'], FILTER_VALIDATE_INT),
                'project_id' => filter_var($data['project_id'], FILTER_VALIDATE_INT),
                'epic_id' => isset($data['epic_id']) ?
                    filter_var($data['epic_id'], FILTER_VALIDATE_INT) : null,
            ];

            // Update completion date if status is completed
            if ($data['status_id'] == 3) { // Assuming 3 is 'completed' status
                $milestoneData['complete_date'] = date('Y-m-d');
            }

            $this->milestoneModel->update($id, $milestoneData);

            $_SESSION['success'] = 'Milestone updated successfully.';
            header('Location: /milestones/view/' . $id);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header("Location: /milestones/edit/{$id}");
            exit;
        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::update: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating the milestone.';
            header("Location: /milestones/edit/{$id}");
            exit;
        }
    }

    /**
     * Delete milestone (soft delete)
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/milestones', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_milestones');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid milestone ID');
            }

            $milestone = $this->milestoneModel->find($id);
            if (!$milestone || $milestone->is_deleted) {
                throw new InvalidArgumentException('Milestone not found');
            }

            // Check if milestone is an epic with active milestones
            if ($milestone->milestone_type === 'epic') {
                $activeMilestones = $this->milestoneModel->getEpicMilestones($id);
                if (!empty($activeMilestones)) {
                    throw new InvalidArgumentException('Cannot delete epic with active milestones');
                }
            }

            $this->milestoneModel->update($id, ['is_deleted' => true]);

            $this->redirectWithSuccess('/milestones', 'Milestone deleted successfully.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/milestones', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::delete: " . $e->getMessage());
            $this->redirectWithError('/milestones', 'An error occurred while deleting the milestone.');
        }
    }

    /**
     * API endpoint to get epics for a specific project
     * @param string $requestMethod
     * @param array $data
     */
    public function getProjectEpicsApi(string $requestMethod, array $data): void
    {
        try {
            // Set JSON content type
            header('Content-Type: application/json');

            // Check permissions
            $this->requirePermission('view_milestones');

            $projectId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$projectId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid project ID']);

                return;
            }

            // Get epics for the project
            $epics = $this->milestoneModel->getProjectEpics($projectId);

            // Return JSON response
            echo json_encode($epics);

        } catch (\Exception $e) {
            error_log("Exception in MilestoneController::getProjectEpicsApi: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load epics']);
        }
    }
}
