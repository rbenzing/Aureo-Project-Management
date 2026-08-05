<?php
// file: Views/Milestones/edit.php
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
    <title>Edit Milestone - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Edit Milestone</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Update the milestone details
                    </p>
                </div>
                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3">
                    <a href="/milestones/view/<?= htmlspecialchars((string)$milestone->id) ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </a>
                    <button type="submit" form="editMilestoneForm"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update Milestone
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Milestone Form -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg p-6">
            <form id="editMilestoneForm" action="/milestones/update" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$milestone->id) ?>">
                
                <!-- Milestone Type Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Milestone Type
                        </label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input 
                                    type="radio" 
                                    name="milestone_type" 
                                    value="milestone" 
                                    class="form-radio h-4 w-4 text-indigo-600"
                                    <?= ($milestone->milestone_type === 'milestone') ? 'checked' : '' ?>
                                >
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Milestone</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input 
                                    type="radio" 
                                    name="milestone_type" 
                                    value="epic" 
                                    class="form-radio h-4 w-4 text-indigo-600"
                                    <?= ($milestone->milestone_type === 'epic') ? 'checked' : '' ?>
                                >
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Epic</span>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Epics are large bodies of work that can be broken down into multiple milestones
                        </p>
                    </div>
                    
                    <!-- Project Selection -->
                    <div>
                        <label for="project_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Project <span class="text-red-600">*</span>
                        </label>
                        <select 
                            id="project_id" 
                            name="project_id" 
                            class="form-select block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                            required
                            data-epic-url="/api/projects/{id}/epics"
                        >
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option 
                                    value="<?= htmlspecialchars((string)$project->id) ?>"
                                    <?= ($project->id == $milestone->project_id) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($project->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Epic Selection (conditionally displayed for milestones) -->
                <div id="epic-selection" class="<?= ($milestone->milestone_type === 'epic') ? 'hidden' : '' ?>">
                    <label for="epic_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Parent Epic
                    </label>
                    <select 
                        id="epic_id" 
                        name="epic_id" 
                        class="form-select block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                    >
                        <option value="">None (Top-level milestone)</option>
                        <?php if (!empty($epics)): ?>
                            <?php foreach ($epics as $epic): ?>
                                <?php if ($epic->id != $milestone->id): // Prevent self-reference?>
                                    <option 
                                        value="<?= htmlspecialchars((string)$epic->id) ?>"
                                        <?= ($epic->id == $milestone->epic_id) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($epic->title) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Optional: Associate this milestone with a parent epic
                    </p>
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
                            value="<?= htmlspecialchars($milestone->title) ?>"
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                            required
                        >
                    </div>
                    
                    <div>
                        <label for="status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Status <span class="text-red-600">*</span>
                        </label>
                        <select 
                            id="status_id" 
                            name="status_id" 
                            class="form-select block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                            required
                        >
                            <?php foreach ($statuses as $status): ?>
                                <option
                                    value="<?= htmlspecialchars((string)$status->id) ?>"
                                    <?= ($status->id == $milestone->status_id) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($status->name) ?>
                                </option>
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
                        <label for="quick_template_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quick Templates</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <select id="quick_template_edit" name="quick_template_edit" class="pl-10 pr-3 py-2 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm">
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
                        <label for="custom_template_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Templates</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <select id="custom_template_edit" name="custom_template_edit" class="pl-10 pr-3 py-2 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm">
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
                        class="form-textarea block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                    ><?= htmlspecialchars($milestone->description ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Optional: Provide a detailed description of the milestone
                    </p>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Date
                        </label>
                        <input 
                            type="date" 
                            id="start_date" 
                            name="start_date" 
                            value="<?= htmlspecialchars($milestone->start_date ?? '') ?>"
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                        >
                    </div>
                    
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Due Date
                        </label>
                        <input 
                            type="date" 
                            id="due_date" 
                            name="due_date" 
                            value="<?= htmlspecialchars($milestone->due_date ?? '') ?>"
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                        >
                    </div>
                    
                    <div>
                        <label for="complete_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Completion Date
                        </label>
                        <input 
                            type="date" 
                            id="complete_date" 
                            name="complete_date" 
                            value="<?= htmlspecialchars($milestone->complete_date ?? '') ?>"
                            <?= ($milestone->status_id != 3) ? 'disabled' : '' ?>
                            class="form-input block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3"
                        >
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Only editable when status is Completed
                        </p>
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
            const milestoneTypeRadios = document.querySelectorAll('input[name="milestone_type"]');
            const epicSelection = document.getElementById('epic-selection');
            const projectSelect = document.getElementById('project_id');
            const epicSelect = document.getElementById('epic_id');
            const statusSelect = document.getElementById('status_id');
            const completeDateInput = document.getElementById('complete_date');

            // Template elements
            const quickTemplateSelect = document.getElementById('quick_template_edit');
            const customTemplateSelect = document.getElementById('custom_template_edit');
            const titleInput = document.getElementById('title');
            const descriptionTextarea = document.getElementById('description');
            
            // Toggle Epic Selection visibility based on milestone type
            function toggleEpicSelection() {
                const selectedType = document.querySelector('input[name="milestone_type"]:checked').value;
                if (selectedType === 'milestone') {
                    epicSelection.classList.remove('hidden');
                } else {
                    epicSelection.classList.add('hidden');
                    epicSelect.value = '';
                }
            }
            
            // Load epics when project changes
            async function loadEpics() {
                const projectId = projectSelect.value;
                if (!projectId) {
                    epicSelect.innerHTML = '<option value="">None (Top-level milestone)</option>';
                    return;
                }
                
                try {
                    const url = projectSelect.dataset.epicUrl.replace('{id}', projectId);
                    const response = await fetch(url);
                    if (!response.ok) throw new Error('Failed to load epics');
                    
                    const epics = await response.json();
                    const currentEpicId = '<?= $milestone->epic_id ?>'; // Get current epic ID
                    const milestoneId = '<?= $milestone->id ?>'; // Current milestone ID
                    
                    epicSelect.innerHTML = '<option value="">None (Top-level milestone)</option>';
                    epics.forEach(epic => {
                        // Skip adding itself as a parent option to prevent circular references
                        if (epic.id != milestoneId) {
                            const option = document.createElement('option');
                            option.value = epic.id;
                            option.textContent = epic.title;
                            if (epic.id == currentEpicId) {
                                option.selected = true;
                            }
                            epicSelect.appendChild(option);
                        }
                    });
                } catch (error) {
                    console.error('Error loading epics:', error);
                }
            }
            
            // Toggle completion date field based on status
            function toggleCompletionDate() {
                const statusId = statusSelect.value;
                if (statusId == 3) { // Completed status
                    completeDateInput.disabled = false;
                    if (!completeDateInput.value) {
                        completeDateInput.value = new Date().toISOString().split('T')[0]; // Set to current date
                    }
                } else {
                    completeDateInput.disabled = true;
                }
            }

            // Template application function
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

            // Add event listeners
            milestoneTypeRadios.forEach(radio => {
                radio.addEventListener('change', toggleEpicSelection);
            });

            projectSelect.addEventListener('change', loadEpics);
            statusSelect.addEventListener('change', toggleCompletionDate);

            // Template event listeners
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

            // Initial state
            toggleEpicSelection();
            toggleCompletionDate();
        });
    </script>
</body>
</html>