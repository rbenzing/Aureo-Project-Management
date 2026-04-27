<?php

// file: Controllers/CompanyController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Middleware\AuthMiddleware;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Services\SecurityService;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class CompanyController extends BaseController
{
    private Company $companyModel;
    private Project $projectModel;
    private User $userModel;

    public function __construct(
        ?Company $companyModel = null,
        ?Project $projectModel = null,
        ?User $userModel = null
    ) {
        parent::__construct();
        $this->requirePermission('view_companies');

        $this->companyModel = $companyModel ?? new Company();
        $this->projectModel = $projectModel ?? new Project();
        $this->userModel = $userModel ?? new User();
    }

    /**
     * Display paginated list of companies
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function index(string $requestMethod, array $data): void
    {
        try {
            $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
            $limit = 10;

            // Build filters based on search query
            $filters = ['is_deleted' => 0];
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $filters['search'] = trim($_GET['search']);
            }

            // Get paginated companies
            $result = $this->companyModel->getAll($filters, $page, $limit);
            $companies = $result['records'];
            $totalCompanies = $result['total'];
            $totalPages = ceil($totalCompanies / $limit);

            // Get summary counts for the dashboard cards
            $totalUsers = $this->userModel->count(['is_deleted' => 0]);
            $activeProjects = $this->projectModel->count(['status_id' => 2, 'is_deleted' => 0]); // Assuming status_id 2 is "in_progress"

            $this->render('Companies/index', compact('activeProjects', 'totalUsers', 'totalPages', 'totalCompanies', 'companies', 'result'));
        } catch (\Exception $e) {
            $securityService = SecurityService::getInstance();
            $this->redirectWithError('/dashboard', $securityService->handleError($e, 'CompanyController::index', 'An error occurred while fetching companies.'));
        }
    }

    /**
     * View company details
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function view(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('view_companies');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid company ID');
            }

            $company = $this->companyModel->find($id);
            if (!$company || $company->is_deleted) {
                throw new InvalidArgumentException('Company not found');
            }

            // Get company related data
            $this->companyModel->id = $id; // Set ID on model instance
            $projects = $this->companyModel->getProjects();
            $users = $this->companyModel->getUsers($id);

            $this->render('Companies/view', compact('users', 'projects'));
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/companies', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in CompanyController::view: " . $e->getMessage());
            $this->redirectWithError('/companies', 'An error occurred while fetching company details.');
        }
    }

    /**
     * Display company creation form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function createForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('create_companies');
            $this->render('Companies/create', compact('users', 'projects'));
        } catch (\Exception $e) {
            error_log("Exception in CompanyController::createForm: " . $e->getMessage());
            $this->redirectWithError('/companies', 'An error occurred while loading the creation form.');
        }
    }

    /**
     * Create new company
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
            $this->requirePermission('create_companies');

            $validator = new Validator($data, [
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|max:25|regex:/^[+]?[0-9()-\s]{10,}$/',
                'email' => 'required|email|unique:companies,email',
                'website' => 'nullable|url|max:255',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $companyData = [
                'name' => htmlspecialchars($data['name']),
                'address' => isset($data['address']) ?
                    htmlspecialchars($data['address']) : null,
                'phone' => isset($data['phone']) ?
                    htmlspecialchars($data['phone']) : null,
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'website' => isset($data['website']) ?
                    filter_var($data['website'], FILTER_SANITIZE_URL) : null,
                'user_id' => $_SESSION['user']['id'] ?? null,
            ];

            $companyId = $this->companyModel->create($companyData);

            $_SESSION['success'] = 'Company created successfully.';
            header('Location: /companies/view/' . $companyId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = Config::getErrorMessage(
                $e,
                'CompanyController::create (validation)',
                $e->getMessage()
            );
            $_SESSION['form_data'] = $data;
            $this->redirect('/companies/create');
        } catch (\Exception $e) {
            $this->redirectWithError('/companies/create', Config::getErrorMessage(
                $e,
                'CompanyController::create',
                'An error occurred while creating the company.'
            ));
        }
    }

    /**
     * Display company edit form
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function editForm(string $requestMethod, array $data): void
    {
        try {
            $this->requirePermission('edit_companies');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid company ID');
            }

            $company = $this->companyModel->find($id);
            if (!$company || $company->is_deleted) {
                throw new InvalidArgumentException('Company not found');
            }

            $this->render('Companies/edit');
        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/companies', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error in CompanyController::editForm: " . $e->getMessage());
            $this->redirectWithError('/companies', 'An error occurred while loading the edit form.');
        }
    }

    /**
     * Update existing company
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
            $this->requirePermission('edit_companies');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid company ID');
            }

            $validator = new Validator($data, [
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|max:25|regex:/^[+]?[0-9()-\s]{10,}$/',
                'email' => "required|email|unique:companies,email,{$id}",
                'website' => 'nullable|url|max:255',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $companyData = [
                'name' => htmlspecialchars($data['name']),
                'address' => isset($data['address']) ?
                    htmlspecialchars($data['address']) : null,
                'phone' => isset($data['phone']) ?
                    htmlspecialchars($data['phone']) : null,
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'website' => isset($data['website']) ?
                    filter_var($data['website'], FILTER_SANITIZE_URL) : null,
            ];

            $this->companyModel->update($id, $companyData);

            $_SESSION['success'] = 'Company updated successfully.';
            header('Location: /companies/view/' . $id);
            exit;

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            header("Location: /companies/edit/{$id}");
            exit;
        } catch (\Exception $e) {
            error_log("Exception in CompanyController::update: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating the company.';
            header("Location: /companies/edit/{$id}");
            exit;
        }
    }

    /**
     * Delete company (soft delete)
     * @param string $requestMethod
     * @param array $data
     * @throws RuntimeException
     */
    public function delete(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->redirectWithError('/companies', 'Invalid request method.');
        }

        try {
            $this->requirePermission('delete_companies');

            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid company ID');
            }

            $company = $this->companyModel->find($id);
            if (!$company || $company->is_deleted) {
                throw new InvalidArgumentException('Company not found');
            }

            // Check if company has active projects or users
            if ($this->projectModel->count(['company_id' => $id, 'is_deleted' => 0]) > 0) {
                throw new InvalidArgumentException('Cannot delete company with active projects');
            }

            if ($this->userModel->count(['company_id' => $id, 'is_deleted' => 0]) > 0) {
                throw new InvalidArgumentException('Cannot delete company with active users');
            }

            $this->companyModel->update($id, ['is_deleted' => true]);

            $this->redirectWithSuccess('/companies', 'Company deleted successfully.');

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/companies', $e->getMessage());
        } catch (\Exception $e) {
            error_log("Exception in CompanyController::delete: " . $e->getMessage());
            $this->redirectWithError('/companies', 'An error occurred while deleting the company.');
        }
    }
}
