<?php
// file: Views/Sprints/create.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Services\SettingsService;

?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sprint - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>

        <!-- Tips Box -->
        <div id="tips-box" class="bg-indigo-50 dark:bg-indigo-900 rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-indigo-800 dark:text-indigo-300">Tips for effective sprints</h3>
                        <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-200">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Set clear, achievable sprint goals</li>
                                <li>Include all necessary SCRUM ceremonies</li>
                                <li>Assign tasks that fit team capacity</li>
                                <li>Plan for 2-4 week sprint durations</li>
                                <li>Review and adjust based on team velocity</li>
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
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Sprint</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Create a new sprint for <?= htmlspecialchars($project->name ?? 'this project') ?> with tasks and goals.
                </p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="/sprints/project/<?= htmlspecialchars((string)($project->id ?? '')) ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="createSprintForm"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Sprint
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Form -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <form id="createSprintForm" action="/sprints/create" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars((string)($project->id ?? '')) ?>">

                <!-- Sprint Template Selection -->
                <div>
                    <label for="sprint_template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sprint Template (Optional)</label>
                    <select
                        id="sprint_template"
                        name="sprint_template"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Create from scratch</option>
                        <!-- Sprint templates will be loaded via JavaScript -->
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose a template to pre-fill sprint settings and configuration</p>
                </div>

                <!-- Sprint Type Selection -->
                <div>
                    <label for="sprint_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sprint Type <span class="text-red-600">*</span></label>
                    <select
                        id="sprint_type"
                        name="sprint_type"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                        required
                    >
                        <option value="project" <?= ($_SESSION['form_data']['sprint_type'] ?? 'project') === 'project' ? 'selected' : '' ?>>
                            Project-based Sprint
                        </option>
                        <option value="milestone" <?= ($_SESSION['form_data']['sprint_type'] ?? '') === 'milestone' ? 'selected' : '' ?>>
                            Milestone/Epic-based Sprint
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose whether this sprint is based on project tasks or specific milestones/epics</p>
                </div>

                <!-- Milestone Selection (shown when milestone type is selected) -->
                <div id="milestone-selection" class="hidden">
                    <label for="milestone_ids" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Milestones/Epics</label>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md shadow-inner">
                        <div class="mb-3">
                            <input type="text" id="milestone-search" placeholder="Search milestones..." class="w-full md:w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-600 dark:text-white">
                        </div>
                        <div id="milestone-list" class="max-h-64 overflow-y-auto space-y-2 pr-2">
                            <!-- Milestones will be loaded via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Sprint Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sprint Name <span class="text-red-600">*</span></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($_SESSION['form_data']['name'] ?? '') ?>"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                        placeholder="e.g., Sprint 1"
                        required
                    >
                </div>

                <!-- Templates Row -->
                <?php
                $settingsService = SettingsService::getInstance();
$templateSettings = $settingsService->getTemplateSettings();
$sprintTemplateSettings = $templateSettings['sprint'] ?? [];
$showQuickTemplates = $sprintTemplateSettings['show_quick_templates'] ?? true;
$showCustomTemplates = $sprintTemplateSettings['show_custom_templates'] ?? true;

// Filter templates to only show sprint templates
$sprintTemplates = [];
if (!empty($templates)) {
    foreach ($templates as $template) {
        if ($template->template_type === 'sprint') {
            $sprintTemplates[] = $template;
        }
    }
}
?>
                <?php if ($showQuickTemplates || $showCustomTemplates): ?>
                <div class="grid grid-cols-1 <?= ($showQuickTemplates && $showCustomTemplates) ? 'md:grid-cols-2' : '' ?> gap-4">
                    <?php if ($showQuickTemplates): ?>
                    <!-- Quick Templates -->
                    <div>
                        <label for="quick_template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quick Templates</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <select id="quick_template" name="quick_template" class="pl-10 pr-3 py-2 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                                <option value="">Select a quick template...</option>
                                <?php
                $quickTemplates = Config::get('QUICK_SPRINT_TEMPLATES', []);
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
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Quick pre-built templates</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($showCustomTemplates): ?>
                    <!-- Custom Templates -->
                    <div>
                        <label for="custom_template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Templates</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <select id="custom_template" name="custom_template" class="pl-10 pr-3 py-2 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                                <option value="">Select a custom template...</option>
                                <?php if (!empty($sprintTemplates)): ?>
                                    <?php foreach ($sprintTemplates as $template): ?>
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
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your saved templates</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea
                                id="description"
                                name="description"
                                rows="8"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                placeholder="Provide a description for this sprint"
                            ><?= htmlspecialchars($_SESSION['form_data']['description'] ?? '') ?></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column - Sprint Details -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Sprint Details</h3>

                    <!-- These fields are part of the main form -->
                    <div class="space-y-6">

                        <!-- Sprint Dates -->
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date <span class="text-red-600">*</span></label>
                            <input
                                type="date"
                                id="start_date"
                                name="start_date"
                                value="<?= htmlspecialchars($_SESSION['form_data']['start_date'] ?? date('Y-m-d')) ?>"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                required
                                form="createSprintForm"
                            >
                        </div>

                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date <span class="text-red-600">*</span></label>
                            <input
                                type="date"
                                id="end_date"
                                name="end_date"
                                value="<?= htmlspecialchars($_SESSION['form_data']['end_date'] ?? date('Y-m-d', strtotime('+2 weeks'))) ?>"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                required
                                form="createSprintForm"
                            >
                        </div>

                        <!-- Sprint Status -->
                        <div>
                            <label for="status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-red-600">*</span></label>
                            <select
                                id="status_id"
                                name="status_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                required
                                form="createSprintForm"
                            >
                                <?php foreach ($sprint_statuses ?? [] as $status): ?>
                                    <?php $statusInfo = getSprintStatusInfo($status->id); ?>
                                    <option
                                        value="<?= (string)$status->id ?>"
                                        <?= isset($_SESSION['form_data']['status_id']) && $_SESSION['form_data']['status_id'] == $status->id ? 'selected' : '' ?>
                                        <?= (!isset($_SESSION['form_data']['status_id']) && $status->id == 1) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($statusInfo['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <?php
    // Include sprint helpers for status functions
    include_once BASE_PATH . '/../src/Views/Sprints/inc/helpers.php';
?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tips box close functionality
            const closeTipsBtn = document.getElementById('close-tips');
            const tipsBox = document.getElementById('tips-box');

            if (closeTipsBtn && tipsBox) {
                closeTipsBtn.addEventListener('click', function() {
                    tipsBox.style.display = 'none';
                });
            }
            // Sprint Type Selection
            const sprintTypeSelect = document.getElementById('sprint_type');
            const milestoneSelection = document.getElementById('milestone-selection');
            const projectTasksSection = document.querySelector('.task-item')?.closest('div').closest('div');

            function toggleSprintType() {
                const sprintType = sprintTypeSelect.value;

                if (sprintType === 'milestone') {
                    milestoneSelection.classList.remove('hidden');
                    if (projectTasksSection) {
                        projectTasksSection.style.display = 'none';
                    }
                    loadMilestones();
                } else {
                    milestoneSelection.classList.add('hidden');
                    if (projectTasksSection) {
                        projectTasksSection.style.display = 'block';
                    }
                }
            }

            sprintTypeSelect.addEventListener('change', toggleSprintType);

            // Load milestones for the current project
            function loadMilestones() {
                const projectId = document.querySelector('input[name="project_id"]').value;
                if (!projectId) return;

                fetch(`/api/sprints/milestones/${projectId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderMilestones(data.milestones);
                        } else {
                            console.error('Failed to load milestones:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading milestones:', error);
                    });
            }

            function renderMilestones(milestones) {
                const milestoneList = document.getElementById('milestone-list');
                milestoneList.innerHTML = '';

                if (milestones.length === 0) {
                    milestoneList.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-sm">No milestones available for this project.</p>';
                    return;
                }

                milestones.forEach(milestone => {
                    const milestoneItem = document.createElement('div');
                    milestoneItem.className = 'milestone-item flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-md';

                    milestoneItem.innerHTML = `
                        <input
                            type="checkbox"
                            id="milestone-${milestone.id}"
                            name="milestone_ids[]"
                            value="${milestone.id}"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        >
                        <label for="milestone-${milestone.id}" class="ml-2 flex-1 cursor-pointer">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                ${milestone.title}
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ml-2 ${milestone.milestone_type === 'epic' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'}">
                                    ${milestone.milestone_type}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                <span class="px-1.5 py-0.5 text-xs rounded-full mr-2 bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                    ${milestone.status_name || 'Open'}
                                </span>
                                ${milestone.ready_task_count || 0} ready tasks
                                ${milestone.due_date ? `• Due: ${new Date(milestone.due_date).toLocaleDateString()}` : ''}
                            </div>
                        </label>
                    `;

                    milestoneList.appendChild(milestoneItem);
                });
            }

            // Milestone search functionality
            const milestoneSearchInput = document.getElementById('milestone-search');
            if (milestoneSearchInput) {
                milestoneSearchInput.addEventListener('input', function() {
                    const searchText = this.value.toLowerCase();
                    const milestoneItems = document.querySelectorAll('.milestone-item');

                    milestoneItems.forEach(item => {
                        const milestoneTitle = item.querySelector('label div:first-child').textContent.toLowerCase();
                        if (milestoneTitle.includes(searchText)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Load sprint templates for the current project
            function loadSprintTemplates() {
                const projectId = document.querySelector('input[name="project_id"]').value;
                if (!projectId) return;

                fetch(`/api/sprint-templates?project_id=${projectId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderSprintTemplates(data.templates);
                        } else {
                            console.error('Failed to load sprint templates:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading sprint templates:', error);
                    });
            }

            function renderSprintTemplates(templates) {
                const templateSelect = document.getElementById('sprint_template');

                // Clear existing options except the first one
                while (templateSelect.children.length > 1) {
                    templateSelect.removeChild(templateSelect.lastChild);
                }

                templates.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = template.name;
                    option.setAttribute('data-config', JSON.stringify(template.config || {}));
                    templateSelect.appendChild(option);
                });
            }

            function applySprintTemplate(templateId) {
                const templateSelect = document.getElementById('sprint_template');
                const selectedOption = templateSelect.querySelector(`option[value="${templateId}"]`);

                if (!selectedOption) return;

                const config = JSON.parse(selectedOption.getAttribute('data-config') || '{}');

                // Apply template configuration to form fields
                if (config.sprint_length) {
                    const sprintLengthField = document.querySelector('input[name="sprint_length"]');
                    if (sprintLengthField) sprintLengthField.value = config.sprint_length;
                }

                if (config.estimation_method) {
                    const estimationField = document.querySelector('select[name="estimation_method"]');
                    if (estimationField) estimationField.value = config.estimation_method;
                }

                if (config.default_capacity) {
                    const capacityField = document.querySelector('input[name="default_capacity"]');
                    if (capacityField) capacityField.value = config.default_capacity;
                }

                if (config.include_weekends !== undefined) {
                    const weekendsField = document.querySelector('input[name="include_weekends"]');
                    if (weekendsField) weekendsField.checked = config.include_weekends;
                }

                if (config.auto_assign_subtasks !== undefined) {
                    const autoAssignField = document.querySelector('input[name="auto_assign_subtasks"]');
                    if (autoAssignField) autoAssignField.checked = config.auto_assign_subtasks;
                }

                // Apply template description if form description is empty
                const descriptionField = document.getElementById('description');
                if (descriptionField && !descriptionField.value.trim()) {
                    fetch(`/sprint-templates/get/${templateId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.template.description) {
                                descriptionField.value = data.template.description;
                            }
                        })
                        .catch(error => {
                            console.error('Error loading template description:', error);
                        });
                }
            }

            // Initialize sprint type on page load
            toggleSprintType();

            // Load sprint templates
            loadSprintTemplates();

            // Handle sprint template selection
            const sprintTemplateSelect = document.getElementById('sprint_template');
            sprintTemplateSelect.addEventListener('change', function() {
                if (this.value) {
                    applySprintTemplate(this.value);
                }
            });

            // Handle Templates Dropdowns
            const quickTemplateSelect = document.getElementById('quick_template');
            const customTemplateSelect = document.getElementById('custom_template');
            const nameInput = document.getElementById('name');
            const descriptionTextarea = document.getElementById('description');

            function applyTemplate(templateSelect) {
                if (!templateSelect.value) return;

                const selectedOption = templateSelect.options[templateSelect.selectedIndex];
                const title = selectedOption.getAttribute('data-title');
                const description = selectedOption.getAttribute('data-description');

                if (descriptionTextarea.value.trim() !== '' &&
                    !confirm('This will replace your current description. Continue?')) {
                    templateSelect.value = ''; // Reset dropdown
                    return;
                }

                if (description) {
                    // Convert literal \n characters to actual line breaks
                    descriptionTextarea.value = description.replace(/\\n/g, '\n');
                }

                // Reset both dropdowns after applying template
                if (quickTemplateSelect) quickTemplateSelect.value = '';
                if (customTemplateSelect) customTemplateSelect.value = '';
            }

            if (quickTemplateSelect) {
                quickTemplateSelect.addEventListener('change', function() {
                    applyTemplate(this);
                });
            }

            if (customTemplateSelect) {
                customTemplateSelect.addEventListener('change', function() {
                    applyTemplate(this);
                });
            }

            // Task search functionality
            const searchInput = document.getElementById('task-search');
            const taskItems = document.querySelectorAll('.task-item');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchText = this.value.toLowerCase();
                    
                    taskItems.forEach(item => {
                        const taskTitle = item.querySelector('label div:first-child').textContent.toLowerCase();
                        if (taskTitle.includes(searchText)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            // Select all tasks
            const selectAllBtn = document.getElementById('select-all-tasks');
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    const visibleTaskCheckboxes = Array.from(taskItems)
                        .filter(item => item.style.display !== 'none')
                        .map(item => item.querySelector('input[type="checkbox"]'));
                    
                    visibleTaskCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                });
            }
            
            // Deselect all tasks
            const deselectAllBtn = document.getElementById('deselect-all-tasks');
            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', function() {
                    const visibleTaskCheckboxes = Array.from(taskItems)
                        .filter(item => item.style.display !== 'none')
                        .map(item => item.querySelector('input[type="checkbox"]'));
                    
                    visibleTaskCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                });
            }
            
            // Date validation
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && endDateInput) {
                // Ensure end date is not before start date
                startDateInput.addEventListener('change', function() {
                    if (endDateInput.value && this.value > endDateInput.value) {
                        endDateInput.value = this.value;
                    }
                });
                
                endDateInput.addEventListener('change', function() {
                    if (startDateInput.value && this.value < startDateInput.value) {
                        alert('End date cannot be earlier than start date.');
                        this.value = startDateInput.value;
                    }
                });
            }
        });
    </script>
</body>
</html>