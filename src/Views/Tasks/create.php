<?php
//file: Views/Tasks/create.php
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

// Include view helpers for permission functions
require_once BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Load form data from session if available (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Check if we are duplicating a task
$duplicateTaskId = $_GET['duplicate'] ?? null;
$duplicateTask = null;
if ($duplicateTaskId) {
    $duplicateTask = (new \App\Models\Task())->find($duplicateTaskId);
}

// Check if we're assigning to a specific user
$assignToUserId = $_GET['assign_to'] ?? null;
$preSelectedProject = $_GET['project_id'] ?? ($duplicateTask->project_id ?? null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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
                        <h3 class="text-sm font-medium text-indigo-800 dark:text-indigo-300">Tips for effective tasks</h3>
                        <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-200">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Use clear, descriptive titles</li>
                                <li>Set realistic due dates</li>
                                <li>Break down complex tasks into subtasks</li>
                                <li>Assign tasks to specific team members</li>
                                <li>Include necessary context in the description</li>
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
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    <?= $duplicateTask ? 'Duplicate Task' : 'Create New Task' ?>
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    <?= $duplicateTask ? 'Create a new task based on an existing one.' : 'Add a new task to track your work and progress.' ?>
                </p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/tasks' ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="createTaskForm"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <?= $duplicateTask ? 'Duplicate Task' : 'Create Task' ?>
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Form -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <form id="createTaskForm" method="POST" action="/tasks/create" class="space-y-6">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <?php if ($duplicateTask): ?>
                            <input type="hidden" name="duplicate_from" value="<?= $duplicateTask->id ?>">
                        <?php endif; ?>
                        <?php if ($assignToUserId): ?>
                            <input type="hidden" name="assigned_to" value="<?= $assignToUserId ?>">
                        <?php endif; ?>

                        <!-- Title -->
                        <?= renderTextInput([
                            'name' => 'title',
                            'label' => 'Task Title',
                            'value' => $formData['title'] ?? ($duplicateTask ? $duplicateTask->title . ' (Copy)' : ''),
                            'required' => true,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h7a2 2 0 012 2v12a2 2 0 01-2 2H9a2 2 0 01-2-2V7a2 2 0 012-2z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />',
                            'error' => $errors['title'] ?? '',
                        ]) ?>

                        <!-- Templates Row -->
                        <?php
                        $settingsService = SettingsService::getInstance();
$templateSettings = $settingsService->getTemplateSettings();
$taskTemplateSettings = $templateSettings['task'] ?? [];
$showQuickTemplates = $taskTemplateSettings['show_quick_templates'] ?? true;
$showCustomTemplates = $taskTemplateSettings['show_custom_templates'] ?? true;

// Filter templates to only show task templates
$taskTemplates = [];
if (!empty($templates)) {
    foreach ($templates as $template) {
        if ($template->template_type === 'task') {
            $taskTemplates[] = $template;
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
                $quickTemplates = Config::get('QUICK_TASK_TEMPLATES', []);
                                foreach ($quickTemplates as $name => $content):
                                    ?>
                                            <option value="<?= htmlspecialchars(strtolower(str_replace(' ', '_', $name))) ?>"
                                                    data-title="<?= htmlspecialchars($name) ?>"
                                                    data-priority="medium"
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
                                <label for="custom_template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Templates</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <select id="custom_template" name="custom_template" class="w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
                                        <option value="">Select a custom template...</option>
                                        <?php if (!empty($taskTemplates)): ?>
                                            <?php foreach ($taskTemplates as $template): ?>
                                                <option value="<?= htmlspecialchars($template->id) ?>"
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
                            'value' => $formData['description'] ?? ($duplicateTask ? $duplicateTask->description : ''),
                            'rows' => 8,
                            'help_text' => 'Add any additional details or context for this task.',
                            'error' => $errors['description'] ?? '',
                        ]) ?>

                        <!-- Acceptance Criteria -->
                        <?= renderTextarea([
                            'name' => 'acceptance_criteria',
                            'label' => 'Acceptance Criteria',
                            'value' => $formData['acceptance_criteria'] ?? ($duplicateTask ? $duplicateTask->acceptance_criteria : ''),
                            'rows' => 6,
                            'placeholder' => 'Define the criteria that must be met for this task to be considered complete...',
                            'help_text' => 'Clear criteria that define when this task is complete.',
                            'error' => $errors['acceptance_criteria'] ?? '',
                        ]) ?>
                </div>
            </div>

            <!-- Right Column - Task Details -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Task Details</h3>

                    <!-- These fields are part of the main form -->
                    <div class="space-y-6">
                        <!-- Assign To -->
                        <?php if (!$assignToUserId): ?>
                        <div>
                            <?php
                            // Prepare assignee options
                            $assigneeOptions = ['' => 'Unassigned'];
                            $currentUserId = $currentUser['profile']['id'] ?? '';
                            $currentUserName = $currentUser['profile']['first_name'] ?? 'Me';

                            // Add current user as default selection
                            if ($currentUserId) {
                                $assigneeOptions[$currentUserId] = "Assign to me ($currentUserName)";
                            }

                            // Add other users
                            foreach ($users as $user) {
                                // Handle both object and array formats
                                $userId = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
                                $firstName = is_object($user) ? ($user->first_name ?? '') : ($user['first_name'] ?? '');
                                $lastName = is_object($user) ? ($user->last_name ?? '') : ($user['last_name'] ?? '');

                                if ($userId && $userId != $currentUserId) {
                                    $assigneeOptions[$userId] = trim($firstName . ' ' . $lastName);
                                }
                            }

                            $selectedAssignee = $formData['assigned_to'] ?? ($duplicateTask ? $duplicateTask->assigned_to : $currentUserId);
                            ?>
                            <?= renderSelect([
                                'name' => 'assigned_to',
                                'label' => 'Assign To',
                                'value' => $selectedAssignee,
                                'options' => $assigneeOptions,
                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
                                'error' => $errors['assigned_to'] ?? '',
                            ]) ?>
                        </div>
                        <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assigning To</label>
                            <div class="mt-1 flex items-center">
                                <?php
                                $assignedUser = null;
                            foreach ($users as $user) {
                                $userId = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
                                if ($userId == $assignToUserId) {
                                    $assignedUser = $user;

                                    break;
                                }
                            }
                            ?>
                                <?php if ($assignedUser): ?>
                                <?php
                            $firstName = is_object($assignedUser) ? ($assignedUser->first_name ?? '') : ($assignedUser['first_name'] ?? '');
                                    $lastName = is_object($assignedUser) ? ($assignedUser->last_name ?? '') : ($assignedUser['last_name'] ?? '');
                                    $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
                                    $fullName = trim($firstName . ' ' . $lastName);
                                    ?>
                                <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-md px-3 py-2">
                                    <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                        <span class="text-xs font-medium text-white">
                                            <?= htmlspecialchars($initials) ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            <?= htmlspecialchars($fullName) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    User ID: <?= $assignToUserId ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Status -->
                        <div>
                            <?php
                            // Prepare status options with proper labels
                            $statusOptions = [];
$selectedStatusId = $formData['status_id'] ?? ($duplicateTask && $duplicateTask->status_id == 1 ? $duplicateTask->status_id : '');
foreach ($statuses as $status) {
    $statusInfo = getTaskStatusInfo($status->id);
    $statusOptions[$status->id] = $statusInfo['label'];
}
?>
                            <?= renderSelect([
    'name' => 'status_id',
    'label' => 'Status',
    'value' => $selectedStatusId,
    'options' => $statusOptions,
    'required' => true,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'error' => $errors['status_id'] ?? '',
]) ?>
                        </div>

                        <!-- Due Date -->
                        <div>
                            <?= renderTextInput([
    'name' => 'due_date',
    'type' => 'date',
    'label' => 'Due Date',
    'value' => $formData['due_date'] ?? ($duplicateTask ? $duplicateTask->due_date : ''),
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'error' => $errors['due_date'] ?? '',
]) ?>
                        </div>

                        <!-- Task Type -->
                        <div>
                            <?php
// Determine the task type value - if duplicating a subtask, show 'subtask' instead of 'task'
$taskTypeValue = $formData['task_type'] ?? ($duplicateTask ? $duplicateTask->task_type : ($projectSettings['default_task_type'] ?? 'task'));
if ($duplicateTask && $duplicateTask->is_subtask && $taskTypeValue === 'task') {
    $taskTypeValue = 'subtask';
}
?>
                            <?= renderSelect([
    'name' => 'task_type',
    'label' => 'Task Type',
    'value' => $taskTypeValue,
    'options' => [
        'task' => 'Task',
        'subtask' => 'Subtask',
        'story' => 'User Story',
        'bug' => 'Bug',
        'epic' => 'Epic',
    ],
    'required' => true,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
    'error' => $errors['task_type'] ?? '',
]) ?>
                        </div>

                        <!-- Priority -->
                        <div>
                            <?= renderSelect([
    'name' => 'priority',
    'label' => 'Priority',
    'value' => $formData['priority'] ?? ($duplicateTask ? $duplicateTask->priority : ($taskSettings['default_priority'] ?? 'none')),
    'options' => [
        'none' => 'None',
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />',
    'error' => $errors['priority'] ?? '',
]) ?>
                        </div>

                        <!-- Backlog Priority (Hidden - controlled by drag & drop) -->
                        <div class="hidden">
                            <input type="hidden" name="backlog_priority" value="<?= htmlspecialchars($formData['backlog_priority'] ?? ($duplicateTask ? $duplicateTask->backlog_priority : '')) ?>">
                        </div>

                        <!-- Project Selection -->
                        <div>
                            <?php
$projectRequired = $projectSettings['require_project_for_tasks'] ?? false;
$projectLabel = $projectRequired ? 'Project <span class="text-red-500">*</span>' : 'Project';
?>
                            <?= renderSelect([
    'name' => 'project_id',
    'label' => $projectLabel,
    'value' => $preSelectedProject ?? '',
    'options' => array_column(is_array($projects ?? null) ? $projects : [], 'name', 'id'),
    'empty_option' => 'Select a project',
    'required' => $projectRequired,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />',
    'error' => $errors['project_id'] ?? '',
]) ?>
                        </div>

                        <!-- Parent Task (for subtasks) -->
                        <div>
                            <?= renderSelect([
    'name' => 'parent_task_id',
    'label' => 'Parent Task <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>',
    'value' => $formData['parent_task_id'] ?? '',
    'options' => [], // Will be populated via JavaScript
    'empty_option' => 'None (Main task)',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
    'help_text' => 'Select a parent task to make this a subtask',
    'error' => $errors['parent_task_id'] ?? '',
]) ?>
                            <input type="hidden" name="is_subtask" id="is_subtask" value="0">
                        </div>

                        <!-- Start Date -->
                        <div>
                            <?= renderTextInput([
    'name' => 'start_date',
    'type' => 'date',
    'label' => 'Start Date',
    'value' => $formData['start_date'] ?? ($duplicateTask ? $duplicateTask->start_date : ''),
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'error' => $errors['start_date'] ?? '',
]) ?>
                        </div>

                        <!-- Story Points -->
                        <div>
                            <?= renderSelect([
    'name' => 'story_points',
    'label' => 'Story Points',
    'value' => $formData['story_points'] ?? ($duplicateTask ? $duplicateTask->story_points : ''),
    'options' => [
        '' => 'None',
        '1' => '1',
        '2' => '2',
        '3' => '3',
        '5' => '5',
        '8' => '8',
        '13' => '13',
    ],
    'help_text' => 'Fibonacci sequence for agile estimation (1, 2, 3, 5, 8, 13)',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />',
    'error' => $errors['story_points'] ?? '',
]) ?>
                        </div>

                        <!-- Estimated Time -->
                        <?php if (hasUserPermission('view_time_tracking') || hasUserPermission('create_time_tracking')): ?>
                        <div>
                            <?php
$timeUnit = $timeSettings['time_unit'] ?? 'minutes';
                            $timeStep = $timeSettings['time_precision'] ?? 15;
                            $timeLabel = ucfirst($timeUnit);

                            // Convert existing value to display unit if needed
                            $estimatedTimeValue = '';
                            if (!empty($formData['estimated_time'])) {
                                $estimatedTimeValue = $formData['estimated_time'];
                            } elseif ($duplicateTask && $duplicateTask->estimated_time) {
                                // Convert from seconds to display unit
                                $estimatedTimeValue = \App\Utils\Time::convertFromSeconds($duplicateTask->estimated_time, $timeUnit);
                            }
?>
                            <?= renderTextInput([
    'name' => 'estimated_time',
    'type' => 'number',
    'label' => "Estimated Time ({$timeLabel})",
    'value' => $estimatedTimeValue,
    'min' => '0',
    'step' => (string)$timeStep,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'error' => $errors['estimated_time'] ?? '',
]) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Sprint Planning -->
                        <div>
                            <?= renderCheckbox([
    'name' => 'is_ready_for_sprint',
    'label' => 'Ready for Sprint Planning',
    'checked' => (isset($formData['is_ready_for_sprint']) && $formData['is_ready_for_sprint']) || ($duplicateTask && $duplicateTask->is_ready_for_sprint),
    'help_text' => 'Mark this task as ready to be included in sprint planning',
    'error' => $errors['is_ready_for_sprint'] ?? '',
]) ?>
                        </div>

                        <!-- Time Spent -->
                        <?php if (hasUserPermission('view_time_tracking') || hasUserPermission('edit_time_tracking')): ?>
                        <div>
                            <?= renderTextInput([
    'name' => 'time_spent',
    'type' => 'number',
    'label' => 'Time Spent (minutes)',
    'value' => $formData['time_spent'] ?? ($duplicateTask ? formatTimeInput($duplicateTask->time_spent) : ''),
    'min' => '0',
    'step' => '15',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'error' => $errors['time_spent'] ?? '',
]) ?>
                        </div>

                        <!-- Billable Toggle -->
                        <div>
                            <?= renderCheckbox([
    'name' => 'is_hourly',
    'label' => 'Mark as billable',
    'checked' => (isset($formData['is_hourly']) && $formData['is_hourly']) || ($duplicateTask && $duplicateTask->is_hourly),
    'help_text' => 'Track billable hours for client invoicing',
    'error' => $errors['is_hourly'] ?? '',
]) ?>
                        </div>

                        <!-- Hourly Rate (conditional on billable) -->
                        <div id="billing-fields" class="hidden">
                            <div class="space-y-4">
                                <div>
                                    <label for="hourly_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hourly Rate ($)</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" id="hourly_rate" name="hourly_rate" min="0" step="0.01"
                                            value="<?php echo htmlspecialchars($formData['hourly_rate'] ?? ($duplicateTask ? $duplicateTask->hourly_rate : '')); ?>"
                                            class="pl-7 pr-3 py-2 w-full border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
                                    </div>
                                </div>
                                <div>
                                    <?= renderTextInput([
            'name' => 'billable_time',
            'type' => 'number',
            'label' => 'Billable Time (minutes)',
            'value' => $formData['billable_time'] ?? ($duplicateTask ? formatTimeInput($duplicateTask->billable_time) : ''),
            'min' => '0',
            'step' => '15',
            'error' => $errors['billable_time'] ?? '',
        ]) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        </form>
    </main>



    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
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

            // Date validation
            const startDateInput = document.getElementById('start_date');
            const dueDateInput = document.getElementById('due_date');

            if (startDateInput && dueDateInput) {
                dueDateInput.addEventListener('change', function() {
                    if (startDateInput.value && dueDateInput.value) {
                        if (new Date(dueDateInput.value) < new Date(startDateInput.value)) {
                            alert('Due date cannot be earlier than start date');
                            dueDateInput.value = '';
                        }
                    }
                });
            }

            // Handle Billable Toggle
            const billableCheckbox = document.getElementById('is_hourly');
            const billingFieldsContainer = document.getElementById('billing-fields');

            function updateBillableVisibility() {
                if (billableCheckbox.checked) {
                    billingFieldsContainer.classList.remove('hidden');
                } else {
                    billingFieldsContainer.classList.add('hidden');
                }
            }

            // Set initial state
            updateBillableVisibility();

            // Listen for changes
            billableCheckbox.addEventListener('change', updateBillableVisibility);

            // Handle Project Change for Parent Task dropdown
            const projectSelect = document.getElementById('project_id');
            const parentTaskSelect = document.getElementById('parent_task_id');
            const isSubtaskInput = document.getElementById('is_subtask');

            async function fetchProjectTasks(projectId) {
                try {
                    const response = await fetch(`/api/projects/${projectId}/tasks`);
                    if (!response.ok) {
                        throw new Error('Failed to fetch tasks');
                    }
                    return await response.json();
                } catch (error) {
                    console.error('Error fetching tasks:', error);
                    return [];
                }
            }

            async function updateParentTaskOptions() {
                const projectId = projectSelect.value;
                parentTaskSelect.innerHTML = '<option value="">None (Create as main task)</option>';

                if (!projectId) return;

                try {
                    const tasks = await fetchProjectTasks(projectId);
                    if (tasks && tasks.length > 0) {
                        tasks.forEach(task => {
                            if (!task.is_subtask) { // Only main tasks can be parents
                                const option = document.createElement('option');
                                option.value = task.id;
                                option.textContent = task.title;
                                parentTaskSelect.appendChild(option);
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error updating parent task options:', error);
                }
            }

            // Update parent task select on project change
            projectSelect.addEventListener('change', updateParentTaskOptions);

            // Update is_subtask value when parent task changes
            parentTaskSelect.addEventListener('change', function() {
                isSubtaskInput.value = this.value ? '1' : '0';
            });

            // Handle Templates Dropdowns
            const quickTemplateSelect = document.getElementById('quick_template');
            const customTemplateSelect = document.getElementById('custom_template');
            const titleInput = document.getElementById('title');
            const prioritySelect = document.getElementById('priority');
            const descriptionTextarea = document.getElementById('description');

            function applyTemplate(templateSelect) {
                if (!templateSelect.value) return;

                const selectedOption = templateSelect.options[templateSelect.selectedIndex];
                const title = selectedOption.getAttribute('data-title');
                const priority = selectedOption.getAttribute('data-priority');
                const description = selectedOption.getAttribute('data-description');

                if (titleInput.value && !confirm('This will replace your current task information. Continue?')) {
                    templateSelect.value = ''; // Reset dropdown
                    return;
                }

                if (title) titleInput.value = title;
                if (priority) prioritySelect.value = priority;
                if (description) {
                    // Convert literal \n characters to actual line breaks
                    descriptionTextarea.value = description.replace(/\\n/g, '\n');
                }

                // Reset both dropdowns after applying template
                quickTemplateSelect.value = '';
                customTemplateSelect.value = '';
            }

            quickTemplateSelect.addEventListener('change', function() {
                applyTemplate(this);
            });

            customTemplateSelect.addEventListener('change', function() {
                applyTemplate(this);
            });

            // Initialize parent task options if project is already selected
            if (projectSelect.value) {
                updateParentTaskOptions();
            }
        });
    </script>
</body>
</html>