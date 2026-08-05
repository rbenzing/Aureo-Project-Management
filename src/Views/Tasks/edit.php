<?php
//file: Views/Tasks/edit.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Services\SettingsService;
use App\Utils\Time;

// Include form components
require_once BASE_PATH . '/../src/Views/Layouts/FormComponents.php';

// Include view helpers for permission functions
require_once BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Load form data from session if available (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Determine if we are marking the task as complete
$markComplete = isset($_GET['mark_complete']) && $_GET['mark_complete'] == 1;

// Format time for display using settings
function formatTimeInput($seconds)
{
    if (!$seconds) {
        return '';
    }

    // Convert seconds to the configured time unit for display
    $settingsService = \App\Services\SettingsService::getInstance();

    return \App\Utils\Time::convertFromSeconds($seconds);
}

// Status functions are now available through centralized view_helpers.php

$pageTitle = $markComplete ? 'Complete Task' : 'Edit Task';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($task->title) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <?php echo \App\Utils\Breadcrumb::renderTaskBreadcrumb($task, 'tasks/edit'); ?>

        <!-- Page Header -->
        <div class="pb-6 flex justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    <?php if ($markComplete): ?>
                        Mark Task Complete
                    <?php else: ?>
                        Edit Task
                    <?php endif; ?>
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    <?php if ($markComplete): ?>
                        Mark this task as complete and add any final notes.
                    <?php else: ?>
                        Update task details and track progress.
                    <?php endif; ?>
                </p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="/tasks/view/<?= $task->id ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="task-form" class="px-4 py-2 text-sm font-medium text-white <?= $markComplete ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500' ?> border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2">
                    <?= $markComplete ? 'Mark Task Complete' : 'Update Task' ?>
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Form -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <form id="task-form" method="POST" action="/tasks/update" class="space-y-6">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo $task->id; ?>">

                        <?php if ($markComplete): ?>
                            <input type="hidden" name="status_id" value="6"> <!-- Set to Completed -->
                            <input type="hidden" name="complete_date" value="<?= date('Y-m-d') ?>">
                            <!-- Include required fields as hidden when marking complete -->
                            <input type="hidden" name="title" value="<?= htmlspecialchars($task->title) ?>">
                            <input type="hidden" name="priority" value="<?= htmlspecialchars($task->priority ?? 'none') ?>">
                            <input type="hidden" name="task_type" value="<?= htmlspecialchars($task->task_type ?? 'task') ?>">
                            <input type="hidden" name="project_id" value="<?= htmlspecialchars((string)($task->project_id ?? '')) ?>">
                            <!-- Include boolean fields that validation expects -->
                            <input type="hidden" name="is_hourly" value="<?= $task->is_hourly ? '1' : '0' ?>">
                            <input type="hidden" name="is_ready_for_sprint" value="<?= $task->is_ready_for_sprint ? '1' : '0' ?>">
                        <?php endif; ?>

                        <?php if ($markComplete): ?>
                        <!-- Completion Notice -->
                        <div class="bg-green-50 dark:bg-green-900 border-l-4 border-green-400 dark:border-green-600 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700 dark:text-green-300">
                                        You are marking this task as complete. Add any final notes below before confirming.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Title -->
                        <?= renderTextInput([
                            'name' => 'title',
                            'label' => 'Task Title',
                            'value' => $formData['title'] ?? $task->title,
                            'required' => true,
                            'disabled' => $markComplete,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h7a2 2 0 012 2v12a2 2 0 01-2 2H9a2 2 0 01-2-2V7a2 2 0 012-2z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />',
                            'error' => $errors['title'] ?? '',
                        ]) ?>

                        <?php if (!$markComplete): ?>
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
                                <label for="quick_template_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quick Templates</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <select id="quick_template_edit" name="quick_template_edit" class="w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
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
                                <label for="custom_template_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Templates</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <select id="custom_template_edit" name="custom_template_edit" class="w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
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
                        <?php endif; ?>

                        <!-- Description -->
                        <?= renderTextarea([
                            'name' => 'description',
                            'label' => 'Description',
                            'value' => $formData['description'] ?? $task->description ?? '',
                            'rows' => 8,
                            'disabled' => $markComplete,
                            'help_text' => 'Add any additional details or context for this task.',
                            'error' => $errors['description'] ?? '',
                        ]) ?>

                        <!-- Acceptance Criteria -->
                        <?= renderTextarea([
                            'name' => 'acceptance_criteria',
                            'label' => 'Acceptance Criteria',
                            'value' => $formData['acceptance_criteria'] ?? $task->acceptance_criteria ?? '',
                            'rows' => 6,
                            'disabled' => $markComplete,
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
                        <div>
                            <?php
                            // Prepare assignee options
                            $assigneeOptions = ['' => 'Unassigned'];
foreach ($users as $user) {
    $userId = $user->id ?? null;
    $firstName = $user->first_name ?? '';
    $lastName = $user->last_name ?? '';
    if ($userId) {
        $assigneeOptions[$userId] = trim($firstName . ' ' . $lastName);
    }
}
?>
                            <?= renderSelect([
    'name' => 'assigned_to',
    'label' => 'Assign To',
    'value' => $formData['assigned_to'] ?? $task->assigned_to,
    'options' => $assigneeOptions,
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
    'error' => $errors['assigned_to'] ?? '',
]) ?>
                        </div>

                        <!-- Status -->
                        <div>
                            <?php
// Prepare status options with proper labels
$statusOptions = [];
foreach ($statuses as $status) {
    $statusInfo = getTaskStatusInfo($status->id);
    $statusOptions[$status->id] = $statusInfo['label'];
}
?>
                            <?= renderSelect([
    'name' => 'status_id',
    'label' => 'Status',
    'value' => $formData['status_id'] ?? $task->status_id,
    'options' => $statusOptions,
    'required' => true,
    'disabled' => $markComplete,
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
    'value' => $formData['due_date'] ?? $task->due_date ?? '',
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'error' => $errors['due_date'] ?? '',
]) ?>
                        </div>

                        <!-- Task Type -->
                        <div>
                            <?php
// Determine the task type value - if it's a subtask, show 'subtask' instead of 'task'
$taskTypeValue = $formData['task_type'] ?? $task->task_type ?? 'task';
if ($task->is_subtask && $taskTypeValue === 'task') {
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
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
    'error' => $errors['task_type'] ?? '',
]) ?>
                        </div>

                        <!-- Priority -->
                        <div>
                            <?= renderSelect([
    'name' => 'priority',
    'label' => 'Priority',
    'value' => $formData['priority'] ?? $task->priority ?? 'none',
    'options' => [
        'none' => 'None',
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />',
    'error' => $errors['priority'] ?? '',
]) ?>
                        </div>

                        <!-- Story Points -->
                        <div>
                            <?= renderSelect([
    'name' => 'story_points',
    'label' => 'Story Points',
    'value' => $formData['story_points'] ?? $task->story_points ?? '',
    'options' => [
        '' => 'None',
        '1' => '1',
        '2' => '2',
        '3' => '3',
        '5' => '5',
        '8' => '8',
        '13' => '13',
    ],
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />',
    'help_text' => 'Fibonacci sequence for agile estimation (1, 2, 3, 5, 8, 13)',
    'error' => $errors['story_points'] ?? '',
]) ?>
                        </div>

                        <!-- Backlog Priority (Hidden - controlled by drag & drop) -->
                        <div class="hidden">
                            <input type="hidden" name="backlog_priority" value="<?= htmlspecialchars((string)($formData['backlog_priority'] ?? $task->backlog_priority ?? '')) ?>">
                        </div>

                        <!-- Project Selection -->
                        <div>
                            <?php
// Prepare project options
$projectOptions = [];
foreach ($projects['records'] as $p) {
    $projectOptions[$p->id] = $p->name;
}
?>
                            <?= renderSelect([
    'name' => 'project_id',
    'label' => 'Project',
    'value' => $formData['project_id'] ?? $task->project_id,
    'options' => $projectOptions,
    'empty_option' => 'Select a project',
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />',
    'error' => $errors['project_id'] ?? '',
]) ?>
                        </div>

                        <!-- Parent Task (for subtasks) -->
                        <div>
                            <?php
// Prepare parent task options from controller data
$parentTaskOptions = [];
if (isset($availableParentTasks)) {
    foreach ($availableParentTasks as $availableTask) {
        $parentTaskOptions[$availableTask->id] = $availableTask->title;
    }
}
?>
                            <?= renderSelect([
    'name' => 'parent_task_id',
    'label' => 'Parent Task <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>',
    'value' => $formData['parent_task_id'] ?? $task->parent_task_id ?? '',
    'options' => $parentTaskOptions,
    'empty_option' => 'None (Main task)',
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
    'help_text' => 'Select a parent task to make this a subtask',
    'error' => $errors['parent_task_id'] ?? '',
]) ?>
                            <input type="hidden" name="is_subtask" id="is_subtask" value="<?= $task->is_subtask ? '1' : '0' ?>">
                        </div>

                        <!-- Start Date -->
                        <div>
                            <?= renderTextInput([
    'name' => 'start_date',
    'type' => 'date',
    'label' => 'Start Date',
    'value' => $formData['start_date'] ?? $task->start_date ?? '',
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'error' => $errors['start_date'] ?? '',
]) ?>
                        </div>

                        <!-- Complete Date (visible when task is completed) -->
                        <?php if ($task->status_id == 6 || $task->complete_date): ?>
                        <div>
                            <?= renderTextInput([
    'name' => 'complete_date',
    'type' => 'date',
    'label' => 'Complete Date',
    'value' => $formData['complete_date'] ?? ($task->complete_date ? date('Y-m-d', strtotime($task->complete_date)) : ''),
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'help_text' => 'Date when this task was completed',
    'error' => $errors['complete_date'] ?? '',
]) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Estimated Time -->
                        <?php if (hasUserPermission('view_time_tracking') || hasUserPermission('edit_time_tracking')): ?>
                        <div>
                            <?= renderTextInput([
    'name' => 'estimated_time',
    'type' => 'number',
    'label' => 'Estimated Time (minutes)',
    'value' => $formData['estimated_time'] ?? formatTimeInput($task->estimated_time) ?? '',
    'min' => '0',
    'step' => '15',
    'disabled' => $markComplete,
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
    'checked' => ($formData['is_ready_for_sprint'] ?? $task->is_ready_for_sprint) ? true : false,
    'disabled' => $markComplete,
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
    'value' => $formData['time_spent'] ?? formatTimeInput($task->time_spent) ?? '',
    'min' => '0',
    'step' => '15',
    'disabled' => $markComplete,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'error' => $errors['time_spent'] ?? '',
]) ?>
                        </div>

                        <!-- Billable Toggle -->
                        <div>
                            <?= renderCheckbox([
    'name' => 'is_hourly',
    'label' => 'Mark as billable',
    'checked' => ($formData['is_hourly'] ?? $task->is_hourly) ? true : false,
    'disabled' => $markComplete,
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
                                            value="<?= htmlspecialchars($formData['hourly_rate'] ?? $task->hourly_rate ?? '') ?>"
                                            <?= $markComplete ? 'disabled' : '' ?>
                                            class="pl-7 pr-3 py-2 w-full border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm border-gray-300 dark:border-gray-600">
                                    </div>
                                </div>
                                <div>
                                    <?= renderTextInput([
            'name' => 'billable_time',
            'type' => 'number',
            'label' => 'Billable Time (minutes)',
            'value' => $formData['billable_time'] ?? formatTimeInput($task->billable_time) ?? '',
            'min' => '0',
            'step' => '15',
            'disabled' => $markComplete,
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

        <?php if (!$markComplete): ?>
        <!-- Danger Zone -->
        <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-red-600 dark:text-red-400">Danger Zone</h3>
            <div class="mt-4 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">Delete this task</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Once deleted, this task and all its data will be permanently removed.</p>
                </div>
                <form method="POST" action="/tasks/delete/<?= $task->id ?>" onsubmit="return confirm('Are you sure you want to delete this task? This action cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Delete Task
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Billable task toggle
            const isHourlyCheckbox = document.getElementById('is_hourly');
            const billingFieldsContainer = document.getElementById('billing-fields');

            if (isHourlyCheckbox && billingFieldsContainer) {
                function updateBillingFields() {
                    if (isHourlyCheckbox.checked) {
                        billingFieldsContainer.classList.remove('hidden');
                    } else {
                        billingFieldsContainer.classList.add('hidden');
                    }
                }

                // Initial state
                updateBillingFields();

                // Handle checkbox change
                isHourlyCheckbox.addEventListener('change', updateBillingFields);
            }

            // Handle Templates Dropdowns
            const quickTemplateSelect = document.getElementById('quick_template_edit');
            const customTemplateSelect = document.getElementById('custom_template_edit');
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
        });
    </script>
</body>
</html>