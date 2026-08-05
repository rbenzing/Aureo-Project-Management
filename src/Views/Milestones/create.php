<?php
// file: Views/Milestones/create.php
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
    <title>Create Milestone - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Tips Box (movable above page title) -->
        <div id="tips-box" class="bg-indigo-50 dark:bg-indigo-900 rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-indigo-800 dark:text-indigo-300">Tips for effective milestones and epics</h3>
                        <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-200">
                            <ul class="list-disc pl-5 space-y-1">
                                <li><strong>Milestones:</strong> Define specific deliverables with clear success criteria and measurable outcomes</li>
                                <li><strong>Epics:</strong> Break down large initiatives into smaller, manageable milestones and tasks</li>
                                <li>Set realistic timelines and consider dependencies between milestones</li>
                                <li>Use descriptive titles that clearly communicate the goal or deliverable</li>
                                <li>Include acceptance criteria and definition of done in descriptions</li>
                                <li>Link related milestones to parent epics for better organization</li>
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
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Create New Milestone</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Add a new milestone or epic to track progress on your project.</p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="/milestones" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="createMilestoneForm"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Milestone
                </button>
            </div>
        </div>
        <!-- Create Milestone Form -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg p-6">
            <form id="createMilestoneForm" action="/milestones/create" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                <!-- Project Selection (Full Width) -->
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Project <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="project_id"
                        name="project_id"
                        class="form-select block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                        required
                        data-epic-url="/api/projects/{id}/epics">
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= htmlspecialchars((string)$project->id) ?>"
                                <?= ($selectedProjectId && $project->id == $selectedProjectId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Select the project this milestone belongs to
                    </p>
                </div>

                <!-- Type and Epic Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Milestone Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Type <span class="text-red-600">*</span>
                        </label>
                        <div class="space-y-3">
                            <label class="flex items-start">
                                <input type="radio" name="milestone_type" value="milestone" class="form-radio h-4 w-4 text-indigo-600 mt-0.5" checked>
                                <div class="ml-3">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Milestone</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">A specific deliverable or goal within a project</p>
                                </div>
                            </label>
                            <label class="flex items-start">
                                <input type="radio" name="milestone_type" value="epic" class="form-radio h-4 w-4 text-indigo-600 mt-0.5">
                                <div class="ml-3">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Epic</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Large body of work that contains multiple milestones</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Epic Selection (for milestones only) -->
                    <div id="epic-selection">
                        <label for="epic_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <span id="epic-label">Parent Epic</span> <span class="text-gray-400">(Optional)</span>
                        </label>
                        <select
                            id="epic_id"
                            name="epic_id"
                            class="form-select block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                            disabled>
                            <option value="">Please select a project first</option>
                            <?php if (!empty($epics)): ?>
                                <?php foreach ($epics as $epic): ?>
                                    <option value="<?= htmlspecialchars((string)$epic->id) ?>"
                                        <?= ($selectedEpicId && $epic->id == $selectedEpicId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($epic->title) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span id="epic-help-text">Select a project above to see available epics</span>
                        </p>
                    </div>
                </div>

                <!-- Title and Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Title <span class="text-red-600">*</span>
                        </label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                            required>
                    </div>

                    <div>
                        <label for="status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Status <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="status_id"
                            name="status_id"
                            class="form-select block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                            required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= htmlspecialchars((string)$status->id) ?>"><?= htmlspecialchars($status->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Templates Row -->
                <?php
                $settingsService = SettingsService::getInstance();
$templateSettings = $settingsService->getTemplateSettings();
$milestoneTemplateSettings = $templateSettings['milestone'] ?? [];
$showQuickTemplates = $milestoneTemplateSettings['show_quick_templates'] ?? true;
$showCustomTemplates = $milestoneTemplateSettings['show_custom_templates'] ?? true;

// Filter templates to only show milestone templates
$milestoneTemplates = [];
if (!empty($templates)) {
    foreach ($templates as $template) {
        if ($template->template_type === 'milestone') {
            $milestoneTemplates[] = $template;
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
                $quickTemplates = Config::get('QUICK_MILESTONE_TEMPLATES', []);
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
                                <?php if (!empty($milestoneTemplates)): ?>
                                    <?php foreach ($milestoneTemplates as $template): ?>
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
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="form-textarea block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"></textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Optional: Provide a detailed description of the milestone
                    </p>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Date
                        </label>
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3">
                    </div>

                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Due Date
                        </label>
                        <input
                            type="date"
                            id="due_date"
                            name="due_date"
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3">
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- JavaScript for Form Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle Tips Box Close
            const tipsBox = document.getElementById('tips-box');
            const closeTipsButton = document.getElementById('close-tips');

            // Tips box is always visible on page load - no sessionStorage check
            // User can close it during the current page session, but it will reappear on refresh

            if (closeTipsButton && tipsBox) {
                closeTipsButton.addEventListener('click', function() {
                    tipsBox.style.display = 'none';
                    // Removed sessionStorage to ensure tips always show on page refresh
                });
            }
            // Handle Templates Dropdowns
            const quickTemplateSelect = document.getElementById('quick_template');
            const customTemplateSelect = document.getElementById('custom_template');
            const titleInput = document.getElementById('title');
            const descriptionTextarea = document.getElementById('description');

            function applyTemplate(templateSelect) {
                if (!templateSelect || !templateSelect.value) return;
                if (!descriptionTextarea) return;

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

            const milestoneTypeRadios = document.querySelectorAll('input[name="milestone_type"]');
            const epicSelection = document.getElementById('epic-selection');
            const projectSelect = document.getElementById('project_id');
            const epicSelect = document.getElementById('epic_id');

            // Check if all required elements exist
            if (!projectSelect || !epicSelect || !epicSelection || milestoneTypeRadios.length === 0) {
                console.error('Required form elements not found');
                return;
            }

            // Toggle Epic Selection visibility based on milestone type
            function toggleEpicSelection() {
                const selectedTypeElement = document.querySelector('input[name="milestone_type"]:checked');
                const epicHelpText = document.getElementById('epic-help-text');
                const epicLabel = document.getElementById('epic-label');
                if (!selectedTypeElement || !epicSelection || !epicSelect) return;

                const selectedType = selectedTypeElement.value;
                console.log('Toggling epic selection for type:', selectedType);

                // Update label based on type
                if (epicLabel) {
                    if (selectedType === 'epic') {
                        epicLabel.textContent = 'Parent Epic';
                    } else {
                        epicLabel.textContent = 'Parent Epic';
                    }
                }

                // Always show epic selection for both milestones and epics (for sub-epics)
                epicSelection.classList.remove('hidden');
                epicSelection.style.opacity = '1';
                console.log('Epic selection shown for type:', selectedType);

                // Only enable if project is selected, otherwise keep disabled with helpful message
                if (projectSelect && projectSelect.value) {
                    console.log('Loading epics for project:', projectSelect.value);
                    loadEpics();
                } else {
                    epicSelect.disabled = true;
                    epicSelect.innerHTML = '<option value="">Please select a project first</option>';
                    if (epicHelpText) {
                        if (selectedType === 'epic') {
                            epicHelpText.textContent = 'Select a project above to see available parent epics';
                        } else {
                            epicHelpText.textContent = 'Select a project above to see available epics';
                        }
                    }
                }
            }

            // Load epics when project changes
            async function loadEpics() {
                if (!projectSelect || !epicSelect) return;

                const projectId = projectSelect.value;
                const selectedTypeElement = document.querySelector('input[name="milestone_type"]:checked');
                const epicHelpText = document.getElementById('epic-help-text');
                if (!selectedTypeElement) return;

                const selectedType = selectedTypeElement.value;

                // Load epics for both milestones and epics (for sub-epics)
                console.log('Loading epics for type:', selectedType);

                if (!projectId) {
                    epicSelect.innerHTML = '<option value="">Please select a project first</option>';
                    epicSelect.disabled = true;
                    if (epicHelpText) {
                        if (selectedType === 'epic') {
                            epicHelpText.textContent = 'Select a project above to see available parent epics';
                        } else {
                            epicHelpText.textContent = 'Select a project above to see available epics';
                        }
                    }
                    return;
                }

                // Show loading state
                epicSelect.innerHTML = '<option value="">Loading epics...</option>';
                epicSelect.disabled = true;

                try {
                    const url = projectSelect.dataset.epicUrl.replace('{id}', projectId);
                    console.log('Loading epics from URL:', url);
                    const response = await fetch(url);
                    console.log('Response status:', response.status);
                    if (!response.ok) throw new Error('Failed to load epics');

                    const epics = await response.json();
                    console.log('Loaded epics:', epics);

                    epicSelect.innerHTML = '<option value="">None (Top-level milestone)</option>';

                    if (epics.length === 0) {
                        // Add "None" option first
                        const noneOption = document.createElement('option');
                        noneOption.value = '';
                        if (selectedType === 'epic') {
                            noneOption.textContent = 'None (Top-level epic)';
                        } else {
                            noneOption.textContent = 'None (Top-level milestone)';
                        }
                        epicSelect.appendChild(noneOption);

                        // Add informational option
                        const noEpicsOption = document.createElement('option');
                        noEpicsOption.value = '';
                        if (selectedType === 'epic') {
                            noEpicsOption.textContent = 'No parent epics available in this project';
                        } else {
                            noEpicsOption.textContent = 'No epics available in this project';
                        }
                        noEpicsOption.disabled = true;
                        noEpicsOption.style.fontStyle = 'italic';
                        epicSelect.appendChild(noEpicsOption);
                    } else {
                        // Get the pre-selected epic ID from URL parameter
                        const urlParams = new URLSearchParams(window.location.search);
                        const preSelectedEpicId = urlParams.get('epic_id');

                        epics.forEach(epic => {
                            const option = document.createElement('option');
                            option.value = epic.id;
                            option.textContent = epic.title;

                            // Pre-select if this epic was passed as URL parameter
                            if (preSelectedEpicId && epic.id == preSelectedEpicId) {
                                option.selected = true;
                            }

                            epicSelect.appendChild(option);
                        });
                    }

                    epicSelect.disabled = false;

                    // Update help text
                    if (epicHelpText) {
                        if (epics.length === 0) {
                            if (selectedType === 'epic') {
                                epicHelpText.textContent = 'No parent epics found - this will be a top-level epic';
                            } else {
                                epicHelpText.textContent = 'No epics found in this project - milestone will be top-level';
                            }
                        } else {
                            if (selectedType === 'epic') {
                                epicHelpText.textContent = `Found ${epics.length} potential parent epic${epics.length > 1 ? 's' : ''} in this project`;
                            } else {
                                epicHelpText.textContent = `Found ${epics.length} epic${epics.length > 1 ? 's' : ''} in this project`;
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error loading epics:', error);
                    epicSelect.innerHTML = '<option value="">Error loading epics - please try again</option>';
                    epicSelect.disabled = true;

                    // Update help text
                    if (epicHelpText) {
                        epicHelpText.textContent = 'Error loading epics - please refresh and try again';
                    }
                }
            }

            // Add event listeners with null checks
            if (milestoneTypeRadios.length > 0) {
                milestoneTypeRadios.forEach(radio => {
                    if (radio) {
                        radio.addEventListener('change', toggleEpicSelection);
                    }
                });
            }

            if (projectSelect) {
                projectSelect.addEventListener('change', loadEpics);
            }

            // Initial state
            toggleEpicSelection();

            // Load epics if project is pre-selected
            if (projectSelect && projectSelect.value) {
                loadEpics();
            }
        });
    </script>
</body>
</html>