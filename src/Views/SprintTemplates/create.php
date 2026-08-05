<?php
// file: Views/SprintTemplates/create.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sprint Template - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Breadcrumb -->
        <nav class="flex mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/dashboard" class="text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="/sprint-templates" class="ml-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Sprint Templates</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-gray-500 dark:text-gray-400">Create</span>
                    </div>
                </li>
            </ol>
            <div class="ml-auto flex space-x-3">
                <a href="/sprint-templates" class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-400 dark:hover:bg-gray-600">
                    Cancel
                </a>
                <button type="submit" form="templateForm" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Template
                </button>
            </div>
        </nav>

        <!-- Tip Box -->
        <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-200">
                        <strong>Sprint Templates</strong> allow you to create reusable configurations for sprints with predefined settings, capacity planning, and SCRUM ceremony configurations. Templates can be applied to any project to quickly create new sprints with consistent settings.
                    </p>
                </div>
            </div>
        </div>

        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Sprint Template</h1>
            <p class="text-gray-600 dark:text-gray-400">Define a reusable sprint configuration with settings and ceremony preferences</p>
        </div>

        <!-- Form Container -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Template Details -->
            <div class="lg:col-span-2">
                <form id="templateForm" action="/sprint-templates/create" method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                    <!-- Basic Information -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h2>
                        
                        <div class="space-y-4">
                            <!-- Template Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name <span class="text-red-600">*</span></label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    value="<?= htmlspecialchars($_SESSION['form_data']['name'] ?? '') ?>"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white" 
                                    placeholder="e.g., Standard Development Sprint"
                                    required
                                >
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description <span class="text-red-600">*</span></label>
                                <textarea 
                                    id="description" 
                                    name="description" 
                                    rows="4" 
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                    placeholder="Describe when and how this template should be used..."
                                    required
                                ><?= htmlspecialchars($_SESSION['form_data']['description'] ?? '') ?></textarea>
                            </div>

                            <!-- Company and Project -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
                                    <select 
                                        id="company_id" 
                                        name="company_id" 
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Global Template</option>
                                        <?php foreach ($companies ?? [] as $company): ?>
                                            <option 
                                                value="<?= $company->id ?>" 
                                                <?= ($_SESSION['form_data']['company_id'] ?? '') == $company->id ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($company->name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="project_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Project (Optional)</label>
                                    <select 
                                        id="project_id" 
                                        name="project_id" 
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Any Project</option>
                                        <?php foreach ($projects ?? [] as $project): ?>
                                            <option 
                                                value="<?= $project->id ?>" 
                                                <?= ($_SESSION['form_data']['project_id'] ?? '') == $project->id ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($project->name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Default Template -->
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="is_default" 
                                    name="is_default" 
                                    value="1"
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                    <?= ($_SESSION['form_data']['is_default'] ?? false) ? 'checked' : '' ?>
                                >
                                <label for="is_default" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                    Set as default template
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sprint Configuration -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Sprint Configuration</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Sprint Length -->
                            <div>
                                <label for="sprint_length" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sprint Length (weeks) <span class="text-red-600">*</span></label>
                                <select 
                                    id="sprint_length" 
                                    name="sprint_length" 
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                    required
                                >
                                    <option value="1" <?= ($_SESSION['form_data']['sprint_length'] ?? 2) == 1 ? 'selected' : '' ?>>1 week</option>
                                    <option value="2" <?= ($_SESSION['form_data']['sprint_length'] ?? 2) == 2 ? 'selected' : '' ?>>2 weeks</option>
                                    <option value="3" <?= ($_SESSION['form_data']['sprint_length'] ?? 2) == 3 ? 'selected' : '' ?>>3 weeks</option>
                                    <option value="4" <?= ($_SESSION['form_data']['sprint_length'] ?? 2) == 4 ? 'selected' : '' ?>>4 weeks</option>
                                </select>
                            </div>

                            <!-- Estimation Method -->
                            <div>
                                <label for="estimation_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estimation Method <span class="text-red-600">*</span></label>
                                <select 
                                    id="estimation_method" 
                                    name="estimation_method" 
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                                    required
                                >
                                    <option value="hours" <?= ($_SESSION['form_data']['estimation_method'] ?? 'hours') == 'hours' ? 'selected' : '' ?>>Hours</option>
                                    <option value="story_points" <?= ($_SESSION['form_data']['estimation_method'] ?? 'hours') == 'story_points' ? 'selected' : '' ?>>Story Points</option>
                                    <option value="both" <?= ($_SESSION['form_data']['estimation_method'] ?? 'hours') == 'both' ? 'selected' : '' ?>>Both</option>
                                </select>
                            </div>

                            <!-- Default Capacity -->
                            <div>
                                <label for="default_capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Capacity <span class="text-red-600">*</span></label>
                                <input 
                                    type="number" 
                                    id="default_capacity" 
                                    name="default_capacity" 
                                    value="<?= htmlspecialchars($_SESSION['form_data']['default_capacity'] ?? '40') ?>"
                                    min="1" 
                                    max="200"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white" 
                                    required
                                >
                            </div>

                            <!-- Options -->
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="include_weekends" 
                                        name="include_weekends" 
                                        value="1"
                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        <?= ($_SESSION['form_data']['include_weekends'] ?? false) ? 'checked' : '' ?>
                                    >
                                    <label for="include_weekends" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                        Include weekends in sprint calculations
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        id="auto_assign_subtasks" 
                                        name="auto_assign_subtasks" 
                                        value="1"
                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        <?= ($_SESSION['form_data']['auto_assign_subtasks'] ?? true) ? 'checked' : '' ?>
                                    >
                                    <label for="auto_assign_subtasks" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                        Auto-assign subtasks when parent is assigned
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right Column: Configuration Preview -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 sticky top-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Template Preview</h2>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Sprint Length:</span>
                            <span id="preview-length" class="font-medium text-gray-900 dark:text-white">2 weeks</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Estimation:</span>
                            <span id="preview-estimation" class="font-medium text-gray-900 dark:text-white">Hours</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Capacity:</span>
                            <span id="preview-capacity" class="font-medium text-gray-900 dark:text-white">40</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Weekends:</span>
                            <span id="preview-weekends" class="font-medium text-gray-900 dark:text-white">Excluded</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Auto-assign:</span>
                            <span id="preview-autoassign" class="font-medium text-gray-900 dark:text-white">Yes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        // Update preview when form values change
        document.addEventListener('DOMContentLoaded', function() {
            const sprintLength = document.getElementById('sprint_length');
            const estimationMethod = document.getElementById('estimation_method');
            const defaultCapacity = document.getElementById('default_capacity');
            const includeWeekends = document.getElementById('include_weekends');
            const autoAssignSubtasks = document.getElementById('auto_assign_subtasks');

            function updatePreview() {
                document.getElementById('preview-length').textContent = sprintLength.value + ' weeks';
                document.getElementById('preview-estimation').textContent = estimationMethod.options[estimationMethod.selectedIndex].text;
                document.getElementById('preview-capacity').textContent = defaultCapacity.value;
                document.getElementById('preview-weekends').textContent = includeWeekends.checked ? 'Included' : 'Excluded';
                document.getElementById('preview-autoassign').textContent = autoAssignSubtasks.checked ? 'Yes' : 'No';
            }

            sprintLength.addEventListener('change', updatePreview);
            estimationMethod.addEventListener('change', updatePreview);
            defaultCapacity.addEventListener('input', updatePreview);
            includeWeekends.addEventListener('change', updatePreview);
            autoAssignSubtasks.addEventListener('change', updatePreview);

            // Initial update
            updatePreview();
        });

        // Clear form data session after displaying
        <?php unset($_SESSION['form_data']); ?>
    </script>
</body>
</html>
