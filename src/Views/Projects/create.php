<?php
//file: Views/Projects/create.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Services\SettingsService;

// Include form components
require_once BASE_PATH . '/../src/Views/Layouts/FormComponents.php';

// Load form data from session if available (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Initialize errors array if not set
$errors = $errors ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Project - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 overflow-y-auto">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>

        <!-- Tips Box (above page title) -->
        <div id="tips-box" class="bg-indigo-50 dark:bg-indigo-900 rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-indigo-800 dark:text-indigo-300">Tips for effective projects</h3>
                        <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-200">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Use clear, descriptive project names</li>
                                <li>Set realistic start and end dates</li>
                                <li>Define clear project scope and objectives</li>
                                <li>Assign appropriate project owners</li>
                                <li>Break down projects into manageable tasks and milestones</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <button type="button" id="close-tips" class="text-indigo-400 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Page Header -->
        <div class="pb-6 flex justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Create New Project</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Create a new project to organize your tasks and track progress.</p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="/projects" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="createProjectForm"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Project
                </button>
            </div>
        </div>

        <form id="createProjectForm" method="POST" action="/projects/create">
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Form -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 space-y-6">
                        <!-- CSRF Token -->
                        <?= renderCSRFToken() ?>

                        <!-- Project Name -->
                        <?= renderTextInput([
                            'name' => 'name',
                            'label' => 'Project Name',
                            'value' => $formData['name'] ?? '',
                            'required' => true,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />',
                            'error' => $errors['name'] ?? '',
                        ]) ?>
                        <!-- Templates Row -->
                        <?php
                        $settingsService = SettingsService::getInstance();
$templateSettings = $settingsService->getTemplateSettings();
$projectTemplateSettings = $templateSettings['project'] ?? [];
$showQuickTemplates = $projectTemplateSettings['show_quick_templates'] ?? true;
$showCustomTemplates = $projectTemplateSettings['show_custom_templates'] ?? true;

// Filter templates to only show project templates
$projectTemplates = [];
if (!empty($templates)) {
    foreach ($templates as $template) {
        if ($template->template_type === 'project') {
            $projectTemplates[] = $template;
        }
    }
}
?>
                        <?php if ($showQuickTemplates || $showCustomTemplates): ?>
                        <div class="mb-4 grid grid-cols-1 <?= ($showQuickTemplates && $showCustomTemplates) ? 'md:grid-cols-2' : '' ?> gap-4">
                            <?php if ($showQuickTemplates): ?>
                            <!-- Quick Templates Dropdown -->
                            <div>
                                <label for="quick_template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quick Templates</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <select id="quick_template" name="quick_template" class="w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
                                        <option value="">Select a quick template...</option>
                                        <?php
                $quickTemplates = Config::get('QUICK_PROJECT_TEMPLATES', []);
                                foreach ($quickTemplates as $name => $content):
                                    ?>
                                            <option value="<?= htmlspecialchars(strtolower(str_replace(' ', '_', $name))) ?>"
                                                    data-title="<?= htmlspecialchars($name) ?>"
                                                    data-description="<?= htmlspecialchars($content) ?>">
                                                <?= htmlspecialchars($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Quick pre-built templates</p>
                            </div>
                            <?php endif; ?>

                            <?php if ($showCustomTemplates): ?>
                            <!-- Custom Templates Dropdown -->
                            <div>
                                <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Templates</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <select id="template_id" name="template_id" class="w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
                                        <option value="">Select a custom template...</option>
                                        <?php if (!empty($projectTemplates)): ?>
                                            <?php foreach ($projectTemplates as $template): ?>
                                                <option value="<?= htmlspecialchars((string)$template->id) ?>"
                                                        data-title="<?= htmlspecialchars($template->name) ?>"
                                                        data-description="<?= htmlspecialchars($template->description) ?>">
                                                    <?= htmlspecialchars($template->name) ?>
                                                    <?php if ($template->is_default): ?>
                                                        <span class="text-xs text-gray-500">(Default)</span>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your saved templates</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Description -->
                        <?= renderTextarea([
                            'name' => 'description',
                            'label' => 'Description',
                            'value' => $formData['description'] ?? '',
                            'rows' => 8,
                            'help_text' => 'Briefly describe the project scope and objectives.',
                            'error' => $errors['description'] ?? '',
                        ]) ?>
                </div>
            </div>

            <!-- Right Column - Project Details -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Project Details</h3>

                    <!-- These fields are part of the main form -->
                    <div class="space-y-6">
                        <!-- Owner -->
                        <?php
                        // Prepare owner options
                        $ownerOptions = [];
if (isset($users) && is_array($users)) {
    foreach ($users as $user) {
        $ownerOptions[$user->id] = htmlspecialchars($user->first_name) . ' ' . htmlspecialchars($user->last_name);
    }
}
?>
                        <?= renderSelect([
    'name' => 'owner_id',
    'label' => 'Project Owner',
    'value' => $formData['owner_id'] ?? '',
    'options' => $ownerOptions,
    'empty_option' => 'Select an owner',
    'required' => true,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
    'error' => $errors['owner_id'] ?? '',
]) ?>

                        <!-- Company -->
                        <?php
// Prepare company options
$companyOptions = [];
if (isset($companies) && is_array($companies)) {
    foreach ($companies as $company) {
        $companyOptions[$company->id] = htmlspecialchars($company->name);
    }
}
?>
                        <?= renderSelect([
    'name' => 'company_id',
    'label' => 'Company',
    'value' => $formData['company_id'] ?? '',
    'options' => $companyOptions,
    'empty_option' => 'Select a company',
    'required' => true,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
    'error' => $errors['company_id'] ?? '',
]) ?>

                        <!-- Status -->
                        <?php
// Prepare status options with proper labels
$statusOptions = [];
if (isset($statuses) && is_array($statuses)) {
    foreach ($statuses as $status) {
        $statusInfo = getProjectStatusInfo($status->id);
        $statusOptions[$status->id] = $statusInfo['label'];
    }
}
?>
                        <?= renderSelect([
    'name' => 'status_id',
    'label' => 'Status',
    'value' => $formData['status_id'] ?? '',
    'options' => $statusOptions,
    'empty_option' => 'Select a status',
    'required' => true,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'error' => $errors['status_id'] ?? '',
]) ?>

                        <!-- Start Date -->
                        <?= renderTextInput([
    'name' => 'start_date',
    'type' => 'date',
    'label' => 'Start Date',
    'value' => $formData['start_date'] ?? '',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'error' => $errors['start_date'] ?? '',
]) ?>

                        <!-- End Date -->
                        <?= renderTextInput([
    'name' => 'end_date',
    'type' => 'date',
    'label' => 'Expected End Date',
    'value' => $formData['end_date'] ?? '',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'error' => $errors['end_date'] ?? '',
]) ?>
                    </div>
                </div>
            </div>
        </div>
        </form>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        // Simple date validation
        document.addEventListener('DOMContentLoaded', function() {
            // Handle Tips Box Close
            const tipsBox = document.getElementById('tips-box');
            const closeTipsButton = document.getElementById('close-tips');

            // Tips box is always visible on page load - no sessionStorage check
            // User can close it during the current page session, but it will reappear on refresh

            closeTipsButton.addEventListener('click', function() {
                tipsBox.style.display = 'none';
                // Removed sessionStorage to ensure tips always show on page refresh
            });
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            endDateInput.addEventListener('change', function() {
                if (startDateInput.value && endDateInput.value) {
                    if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                        alert('End date cannot be earlier than start date');
                        endDateInput.value = '';
                    }
                }
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            const customTemplateSelect = document.getElementById('template_id');
            const quickTemplateSelect = document.getElementById('quick_template');
            const descriptionTextarea = document.getElementById('description');

            // Handle custom template selection
            if (customTemplateSelect && descriptionTextarea) {
                customTemplateSelect.addEventListener('change', function() {
                    const templateId = this.value;

                    // If no template selected or description already has content, don't overwrite
                    if (!templateId || (descriptionTextarea.value.trim() !== '' &&
                            !confirm('This will replace your current description. Continue?'))) {
                        return;
                    }

                    // Fetch template content
                    fetch(`/project-templates/get/${templateId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.template) {
                                // Convert literal \n characters to actual line breaks
                                descriptionTextarea.value = data.template.description.replace(/\\n/g, '\n');
                                // Reset quick template dropdown
                                if (quickTemplateSelect) quickTemplateSelect.value = '';
                            } else {
                                console.error('Error loading template:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching template:', error);
                        });
                });
            }

            // Handle quick template selection
            if (quickTemplateSelect && descriptionTextarea) {
                quickTemplateSelect.addEventListener('change', function() {
                    if (!this.value) return;

                    const selectedOption = this.options[this.selectedIndex];
                    const description = selectedOption.getAttribute('data-description');

                    if (descriptionTextarea.value.trim() !== '' &&
                        !confirm('This will replace your current description. Continue?')) {
                        this.value = ''; // Reset dropdown
                        return;
                    }

                    if (description) {
                        // Convert literal \n characters to actual line breaks
                        descriptionTextarea.value = description.replace(/\\n/g, '\n');
                        // Reset custom template dropdown
                        if (customTemplateSelect) customTemplateSelect.value = '';
                    }

                    // Reset dropdown after applying template
                    this.value = '';
                });
            }
        });
    </script>
</body>
</html>