<?php
// file: Views/Sprints/edit.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include form components
require_once BASE_PATH . '/../src/Views/Layouts/FormComponents.php';

// Include view helpers for permission functions
require_once BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Load form data from session if available (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sprint - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>

        <!-- Page Header -->
        <div class="pb-6 flex justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Sprint</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update sprint details, dates, and manage tasks</p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="/sprints/view/<?= htmlspecialchars((string)($sprint->id ?? '')) ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="sprint-form" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Sprint
                </button>
            </div>
        </div>

        <!-- Tip Box -->
        <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-400 dark:border-blue-600 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>Sprint Management:</strong> Update sprint details, manage dates, and add or remove tasks.
                        Changes to active sprints may affect team workflow and task scheduling.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Form -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <form id="sprint-form" action="/sprints/update/<?= htmlspecialchars((string)($sprint->id ?? '')) ?>" method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string)($sprint->id ?? '')) ?>">

                        <!-- Sprint Name -->
                        <?= renderTextInput([
                            'name' => 'name',
                            'label' => 'Sprint Name',
                            'value' => $formData['name'] ?? $sprint->name ?? '',
                            'required' => true,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
                            'placeholder' => 'Enter sprint name...',
                        ]) ?>

                        <!-- Description -->
                        <?= renderTextarea([
                            'name' => 'description',
                            'label' => 'Description',
                            'value' => $formData['description'] ?? $sprint->description ?? '',
                            'rows' => 4,
                            'placeholder' => 'Describe the sprint goals and objectives...',
                            'help_text' => 'Provide a clear description of what this sprint aims to accomplish.',
                        ]) ?>

                        <!-- Sprint Dates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Start Date -->
                            <?= renderTextInput([
                                'name' => 'start_date',
                                'type' => 'date',
                                'label' => 'Start Date',
                                'value' => $formData['start_date'] ?? $sprint->start_date ?? '',
                                'required' => true,
                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                            ]) ?>

                            <!-- End Date -->
                            <?= renderTextInput([
                                'name' => 'end_date',
                                'type' => 'date',
                                'label' => 'End Date',
                                'value' => $formData['end_date'] ?? $sprint->end_date ?? '',
                                'required' => true,
                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                            ]) ?>
                        </div>



                <!-- Sprint Tasks Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sprint Tasks</label>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md shadow-inner">
                        <div class="mb-3 flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0">
                            <input type="text" id="task-search" placeholder="Search tasks..." class="w-full md:w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-600 dark:text-white">
                            <div class="flex md:ml-auto">
                                <button type="button" id="select-all-tasks" class="mr-2 px-3 py-2 text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Select All</button>
                                <button type="button" id="deselect-all-tasks" class="px-3 py-2 text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">Deselect All</button>
                            </div>
                        </div>

                        <div class="max-h-64 overflow-y-auto space-y-2 pr-2">
                            <!-- Sprint Tasks -->
                            <?php if (!empty($sprintTasks)): ?>
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Currently in Sprint:</h3>
                                <?php foreach ($sprintTasks as $task): ?>
                                    <?php
                                    // Convert array to object if needed
                                    $taskObj = is_array($task) ? (object)$task : $task;
                                    ?>
                                    <div class="task-item flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-md">
                                        <input
                                            type="checkbox"
                                            id="task-<?= htmlspecialchars((string)($taskObj->id ?? '0')) ?>"
                                            name="tasks[]"
                                            value="<?= htmlspecialchars((string)($taskObj->id ?? '0')) ?>"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            checked
                                        >
                                        <label for="task-<?= htmlspecialchars((string)($taskObj->id ?? '0')) ?>" class="ml-2 flex-1 cursor-pointer">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($taskObj->title ?? 'Untitled Task') ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                                <?php
                                                $taskStatusInfo = getTaskStatusInfo($taskObj->status_id ?? 1);
                                    echo renderStatusPill($taskStatusInfo['label'], $taskStatusInfo['color'], 'sm');
                                    ?>
                                                <?php if (!empty($taskObj->due_date)): ?>
                                                    <span>Due: <?= date('M j, Y', strtotime($taskObj->due_date)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Available Project Tasks (not in sprint) -->
                            <?php if (!empty($projectTasks)): ?>
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-4 mb-2">Available Project Tasks:</h3>
                                <?php
                                // Skip tasks already in sprint
                                $sprintTaskIds = array_map(function ($task) {
                                    $taskObj = is_array($task) ? (object)$task : $task;

                                    return $taskObj->id ?? 0;
                                }, $sprintTasks ?? []);

                                foreach ($projectTasks as $task):
                                    // Convert array to object if needed
                                    $taskObj = is_array($task) ? (object)$task : $task;

                                    // Skip if task is already in sprint
                                    if (in_array($taskObj->id ?? 0, $sprintTaskIds)) {
                                        continue;
                                    }
                                    ?>
                                    <div class="task-item flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-md">
                                        <input
                                            type="checkbox"
                                            id="task-<?= htmlspecialchars((string)($taskObj->id ?? '0')) ?>"
                                            name="tasks[]"
                                            value="<?= htmlspecialchars((string)($taskObj->id ?? '0')) ?>"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        >
                                        <label for="task-<?= htmlspecialchars((string)($taskObj->id ?? '0')) ?>" class="ml-2 flex-1 cursor-pointer">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($taskObj->title ?? 'Untitled Task') ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                                <?php
                                                    $taskStatusInfo = getTaskStatusInfo($taskObj->status_id ?? 1);
                                    echo renderStatusPill($taskStatusInfo['label'], $taskStatusInfo['color'], 'sm');
                                    ?>
                                                <span class="ml-2"></span>
                                                <?php if (!empty($taskObj->due_date)): ?>
                                                    <span>Due: <?= date('M j, Y', strtotime($taskObj->due_date)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (empty($sprintTasks) && empty($projectTasks)): ?>
                                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                                    No tasks available for this sprint.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                    </form>
                </div>
            </div>

            <!-- Right Column - Sprint Details -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Sprint Details</h3>

                    <!-- Sprint Status Field -->
                    <div class="mb-6">
                        <?php
                        // Prepare status options with proper labels
                        $statusOptions = [];
foreach ($statuses ?? [] as $status) {
    $statusInfo = getSprintStatusInfo($status->id);
    $statusOptions[$status->id] = $statusInfo['label'];
}
?>
                        <?= renderSelect([
    'name' => 'status_id',
    'label' => 'Status',
    'value' => $formData['status_id'] ?? $sprint->status_id ?? '',
    'options' => $statusOptions,
    'required' => true,
    'form' => 'sprint-form',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'help_text' => isset($sprint->status_id) && $sprint->status_id == 2 ? 'Changing status from Active may affect task scheduling.' : '',
]) ?>
                    </div>

                    <!-- Sprint Info -->
                    <div class="space-y-4">
                        <!-- Current Sprint Info -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center mb-2">
                                <div class="h-8 w-8 rounded-md bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center mr-3">
                                    <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        <?= htmlspecialchars($sprint->name ?? 'Sprint') ?>
                                    </h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        Project: <a href="/projects/view/<?= $project->id ?? 0 ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            <?= htmlspecialchars($project->name ?? 'Project') ?>
                                        </a>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <?php
        $statusInfo = getSprintStatusInfo($sprint->status_id ?? 1);
echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-200">Quick Actions</h4>
                            <div class="space-y-2">
                                <a href="/sprints/view/<?= htmlspecialchars((string)($sprint->id ?? '')) ?>" class="w-full flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    View Sprint
                                </a>
                                <a href="/sprints/project/<?= htmlspecialchars((string)($project->id ?? '')) ?>" class="w-full flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    All Sprints
                                </a>
                            </div>
                        </div>

                        <!-- Sprint Statistics -->
                        <?php if (!empty($sprintTasks)): ?>
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-200">Sprint Statistics</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-md">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex justify-between">
                                        <span>Total Tasks:</span>
                                        <span class="font-medium"><?= count($sprintTasks) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <?php
    // Include view helpers for centralized status functions
    require_once BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Include sprint helpers for additional functions
include_once BASE_PATH . '/../src/Views/Sprints/inc/helpers.php';
?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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