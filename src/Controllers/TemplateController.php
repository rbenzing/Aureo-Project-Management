<?php

// file: Controllers/TemplateController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Company;
use App\Models\Template;
use App\Services\SecurityService;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class TemplateController extends BaseController
{
    private Template $templateModel;
    private Company $companyModel;

    public function __construct(
        ?Template $templateModel = null,
        ?Company $companyModel = null
    ) {
        parent::__construct();
        $this->templateModel = $templateModel ?? new Template();
        $this->companyModel = $companyModel ?? new Company();
    }

    /**
     * Display paginated list of templates
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_templates');

            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $settingsService = \App\Services\SettingsService::getInstance();
            $limit = $settingsService->getResultsPerPage();

            // Get filter parameters
            $templateType = isset($_GET['type']) ? $_GET['type'] : '';
            $filters = [];
            if (!empty($templateType) && array_key_exists($templateType, Template::TEMPLATE_TYPES)) {
                $filters['template_type'] = $templateType;
            }

            // Debug: Test each step individually
            error_log("TemplateController: Starting to fetch templates");

            try {
                $templates = $this->templateModel->getAllTemplates($filters, $limit, $page);
                error_log("TemplateController: Successfully got templates, count: " . count($templates));
            } catch (\Exception $e) {
                error_log("TemplateController: Error getting templates: " . $e->getMessage());

                throw $e;
            }

            try {
                $countFilters = $filters;
                $countFilters['is_deleted'] = 0;
                $totalTemplates = $this->templateModel->count($countFilters);
                error_log("TemplateController: Successfully got count: " . $totalTemplates);
            } catch (\Exception $e) {
                error_log("TemplateController: Error getting count: " . $e->getMessage());

                throw $e;
            }

            $totalPages = ceil($totalTemplates / $limit);

            $this->render('Templates/index', compact('templates', 'totalPages', 'page', 'limit', 'templateType'));
        } catch (\Throwable $e) {
            $securityService = SecurityService::getInstance();
            $this->redirectWithError('/dashboard', $securityService->handleError($e, 'TemplateController::index', 'An error occurred while fetching templates.'));
        }
    }

    /**
     * Display template details
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_templates');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid template ID');
            }

            $template = $this->templateModel->find($id);
            if (!$template || $template->is_deleted) {
                throw new InvalidArgumentException('Template not found');
            }

            $this->render('Templates/view', compact('template'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/templates', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::view');
            $this->redirectWithError('/templates', 'An error occurred while fetching template details.');
        }
    }

    /**
     * Display template creation form
     *
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_templates');

            $companies = $this->companyModel->getAllCompanies();

            $this->render('Templates/create', compact('companies'));
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::createForm');
            $this->redirectWithError('/templates', 'An error occurred while loading the creation form.');
        }
    }

    /**
     * Handle template creation
     *
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function create(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirect('/templates/create');
        }

        try {
            $this->requirePermission('create_templates');

            $validator = new Validator($data, [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'template_type' => 'required|in:project,task,milestone,sprint',
                'company_id' => 'nullable|integer|exists:companies,id',
                'is_default' => 'boolean',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $templateData = [
                'name' => htmlspecialchars($data['name']),
                'description' => $data['description'],
                'template_type' => $data['template_type'],
                'company_id' => !empty($data['company_id']) ?
                    filter_var($data['company_id'], FILTER_VALIDATE_INT) : null,
                'is_default' => isset($data['is_default']) ? true : false,
            ];

            // Begin transaction for setting default template
            $this->templateModel->beginTransaction();

            try {
                $templateId = $this->templateModel->create($templateData);

                // If this is set as default, update other templates of the same type
                if ($templateData['is_default']) {
                    $this->templateModel->setDefaultTemplate($templateId, $templateData['template_type'], $templateData['company_id']);
                }

                $this->templateModel->commit();

                $this->redirectWithSuccess('/templates', 'Template created successfully.');
            } catch (\Exception $e) {
                $this->templateModel->rollBack();

                throw $e;
            }
        } catch (InvalidArgumentException $e) {
            $_SESSION['form_data'] = $data;
            $this->redirectWithError('/templates/create', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::create');
            $_SESSION['form_data'] = $data;
            $this->redirectWithError('/templates/create', 'An error occurred while creating the template.');
        }
    }

    /**
     * Display template edit form
     *
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_templates');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid template ID');
            }

            $template = $this->templateModel->find($id);
            if (!$template || $template->is_deleted) {
                throw new InvalidArgumentException('Template not found');
            }

            $companies = $this->companyModel->getAllCompanies();

            $this->render('Templates/edit', compact('template', 'companies'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/templates', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::editForm');
            $this->redirectWithError('/templates', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Handle template update
     *
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function update(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirect('/templates');
        }

        try {
            $this->requirePermission('edit_templates');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid template ID');
            }

            $validator = new Validator($data, [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'template_type' => 'required|in:project,task,milestone,sprint',
                'company_id' => 'nullable|integer|exists:companies,id',
                'is_default' => 'boolean',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $templateData = [
                'name' => htmlspecialchars($data['name']),
                'description' => $data['description'],
                'template_type' => $data['template_type'],
                'company_id' => !empty($data['company_id']) ?
                    filter_var($data['company_id'], FILTER_VALIDATE_INT) : null,
                'is_default' => isset($data['is_default']) ? true : false,
            ];

            // Begin transaction for setting default template
            $this->templateModel->beginTransaction();

            try {
                $this->templateModel->update($id, $templateData);

                // If this is set as default, update other templates of the same type
                if ($templateData['is_default']) {
                    $this->templateModel->setDefaultTemplate($id, $templateData['template_type'], $templateData['company_id']);
                }

                $this->templateModel->commit();

                $this->redirectWithSuccess('/templates', 'Template updated successfully.');
            } catch (\Exception $e) {
                $this->templateModel->rollBack();

                throw $e;
            }
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError("/templates/edit/{$id}", $e->getMessage());
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::update');
            $this->redirectWithError("/templates/edit/{$id}", 'An error occurred while updating the template.');
        }
    }

    /**
     * Handle template deletion
     *
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirect('/templates');
        }

        try {
            $this->requirePermission('delete_templates');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid template ID');
            }

            $template = $this->templateModel->find($id);
            if (!$template || $template->is_deleted) {
                throw new InvalidArgumentException('Template not found');
            }

            $this->templateModel->update($id, ['is_deleted' => true]);

            $this->redirectWithSuccess('/templates', 'Template deleted successfully.');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/templates', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::delete');
            $this->redirectWithError('/templates', 'An error occurred while deleting the template.');
        }
    }

    /**
     * Return template JSON for AJAX requests
     *
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function getTemplate(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_templates');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid template ID');
            }

            $template = $this->templateModel->find($id);
            if (!$template || $template->is_deleted) {
                throw new InvalidArgumentException('Template not found');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'template' => [
                    'name' => $template->name,
                    'description' => $template->description,
                    'template_type' => $template->template_type,
                ],
            ]);
            exit;
        } catch (InvalidArgumentException $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        } catch (\Throwable $e) {
            $this->logException($e, 'TemplateController::getTemplate');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'An error occurred while fetching the template.',
            ]);
            exit;
        }
    }
}
