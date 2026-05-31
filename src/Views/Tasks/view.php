<?php
//file: Views/Tasks/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Services\SettingsService;
use App\Utils\Time;

// Include view helpers for permission functions and time formatting
require_once BASE_PATH . '/../src/views/Layouts/ViewHelpers.php';

// Helper functions for formatting and styling
function formatTimeTracking($seconds)
{
    return formatTimeWithSettings($seconds);
}

function formatDate($date)
{
    if (!$date) {
        return 'Not set';
    }
    $settingsService = SettingsService::getInstance();

    return $settingsService->formatDate($date);
}

// Remove local function - now using centralized helper

// Subtasks/timer are not passed as standalone view vars; read what the
// controller actually provides ($task->subtasks via findWithDetails).
$subtasks = $task->subtasks ?? [];
$activeTimer = $activeTimer ?? null;

// Calculate progress
$totalSubtasks = !empty($subtasks) ? count($subtasks) : 0;
$completedSubtasks = 0;

if ($totalSubtasks > 0) {
    foreach ($subtasks as $subtask) {
        if ($subtask->status_id == 6) { // Completed status is 6
            $completedSubtasks++;
        }
    }
}
$progress = $totalSubtasks > 0 ? ($completedSubtasks / $totalSubtasks) * 100 : 0;

// Determine if time tracking is currently active
$isTimerActive = isset($activeTimer) && $activeTimer['task_id'] == $task->id;
$timerDuration = $isTimerActive ? time() - $activeTimer['start_time'] : 0;
$formattedDuration = gmdate('H:i:s', $timerDuration);

$pageTitle = htmlspecialchars($task->title) . ' - Task Details';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <?php echo \App\Utils\Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view'); ?>

        <!-- Task Header -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mr-3"><?= htmlspecialchars($task->title) ?></h1>
                    <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors mr-3"
                            data-type="task"
                            data-item-id="<?= $task->id ?>"
                            data-title="<?= htmlspecialchars($task->title) ?>"
                            data-icon="✅"
                            title="Add to favorites">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </button>
                    <?php
                    $statusInfo = getTaskStatusInfo($task->status_id);
echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'md');
?>
                    <span class="ml-2 px-3 py-1 text-sm rounded-full <?= getPriorityClass($task->priority) ?>">
                        <?= ucfirst(htmlspecialchars($task->priority)) ?> Priority
                    </span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    <?php if (isset($project->name)): ?>
                        <span class="inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                            </svg>
                            <a href="/projects/view/<?= $project->id ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                <?= htmlspecialchars($project->name) ?>
                            </a>
                        </span>
                        •
                    <?php endif; ?>
                    Created <?= date('M j, Y', strtotime($task->created_at)) ?>
                </p>
            </div>
            <div class="flex space-x-3">
                <?php if (hasUserPermission('view_time_tracking') || hasUserPermission('create_time_tracking')): ?>
                    <?php if ($isTimerActive): ?>
                        <form method="POST" action="/tasks/stop-timer/<?= $activeTimer['task_id'] ?>" class="inline-flex">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span id="timer"><?= $formattedDuration ?></span>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/tasks/start-timer/<?= $task->id ?>" class="inline-flex">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Start Timer
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Mark Complete Button -->
                <?php if ($task->status_id != 6 && $task->status_id != 5): // Not completed or closed?>
                    <a href="/tasks/edit/<?= $task->id ?>?mark_complete=1" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Mark Complete
                    </a>
                <?php endif; ?>

                <!-- Add Subtask Button -->
                <button type="button" onclick="openAddSubtaskModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Add Subtask
                </button>

                <?php if (isset($currentUser['permissions']) && in_array('edit_tasks', $currentUser['permissions'])): ?>
                    <a href="/tasks/edit/<?= $task->id ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        Edit Task
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Task Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Task Overview & Details Column -->
            <div class="md:col-span-1">
                <!-- Task Progress Card -->
                <?php if (hasUserPermission('view_time_tracking') && !empty($task->estimated_time) && !empty($task->time_spent)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Task Progress</h3>
                    </div>
                    <div class="p-4">
                        <?php
    $timeProgress = min(100, ($task->time_spent / $task->estimated_time) * 100);
                    $progressColor = $timeProgress > 100 ? 'bg-red-600' : 'bg-blue-600';
                    ?>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-500 dark:text-gray-400">Progress</span>
                            <span class="text-gray-500 dark:text-gray-400"><?= round($timeProgress) ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                            <div class="<?= $progressColor ?> h-2 rounded-full" style="width: <?= $timeProgress ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Task Details Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Task Details</h3>
                        <button type="button" class="section-toggle" data-target="task-details">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="task-details" class="p-4 space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Task Type:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?php
                            $taskTypeLabels = [
                                'task' => 'Task',
                                'story' => 'User Story',
                                'bug' => 'Bug',
                                'epic' => 'Epic',
                            ];

// If it's a subtask, show "Subtask" instead of "Task"
$displayType = $task->task_type ?? 'task';
if ($task->is_subtask && $displayType === 'task') {
    echo 'Subtask';
} else {
    echo htmlspecialchars($taskTypeLabels[$displayType] ?? 'Task');
}
?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Assignee:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= !empty($task->first_name) ? htmlspecialchars($task->first_name . ' ' . $task->last_name) : 'Unassigned' ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Status:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= htmlspecialchars($task->status_name) ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Priority:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= ucfirst(htmlspecialchars($task->priority)) ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Backlog Priority:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?php if (!empty($task->backlog_priority)): ?>
                                    #<?= htmlspecialchars((string)$task->backlog_priority) ?>
                                <?php else: ?>
                                    <span class="text-gray-500 dark:text-gray-400 italic">None</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Story Points:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?php if (!empty($task->story_points)): ?>
                                    <?= htmlspecialchars((string)$task->story_points) ?>
                                <?php else: ?>
                                    <span class="text-gray-500 dark:text-gray-400 italic">Not estimated</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Sprint Ready:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?php if ($task->is_ready_for_sprint ?? false): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Ready
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                        Not Ready
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($task->is_subtask && $task->parent_task_id): ?>
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Parent Task:</div>
                            <div class="text-gray-900 dark:text-white">
                                <a href="/tasks/view/<?= $task->parent_task_id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    Task #<?= $task->parent_task_id ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Start Date:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= isset($task->start_date) && !empty($task->start_date) ? formatDate($task->start_date) : 'Not set' ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Due Date:</div>
                            <div class="text-gray-900 dark:text-white <?= (!empty($task->due_date) && strtotime($task->due_date) < time() && $task->status_id != 6) ? 'text-red-600 dark:text-red-400' : '' ?>">
                                <?= isset($task->due_date) && !empty($task->due_date) ? formatDate($task->due_date) : 'Not set' ?>
                            </div>
                            <?php if (!empty($task->complete_date)): ?>
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Completed:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= formatDate($task->complete_date) ?>
                            </div>
                            <?php endif; ?>

                            <?php if (hasUserPermission('view_time_tracking')): ?>
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Estimated:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= formatTimeTracking($task->estimated_time) ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Time spent:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= formatTimeTracking($task->time_spent) ?>
                            </div>

                            <?php if ($task->is_hourly): ?>
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Billable:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= formatTimeTracking($task->billable_time) ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Hourly rate:</div>
                            <div class="text-gray-900 dark:text-white">
                                $<?= number_format($task->hourly_rate ?? 0, 2) ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Billable amount:</div>
                            <div class="text-green-600 dark:text-green-400 font-medium">
                                <?php
$billableSeconds = $task->billable_time ?? 0;
                                $billableHours = $billableSeconds / 3600;
                                $billableAmount = $billableHours * ($task->hourly_rate ?? 0);
                                echo '$' . number_format($billableAmount, 2);
                                ?>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Milestones Card (if task is associated with milestones) -->
                <?php if (!empty($task->milestones)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Milestones</h3>
                        <button type="button" class="section-toggle" data-target="task-milestones">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="task-milestones" class="p-4">
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($task->milestones as $milestone): ?>
                                <?php
                                $milestoneStatusClass = '';
                                switch ($milestone->status_id) {
                                    case 1: $milestoneStatusClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';

                                        break;
                                    case 2: $milestoneStatusClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';

                                        break;
                                    case 3: $milestoneStatusClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

                                        break;
                                    default: $milestoneStatusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                }
                                ?>
                                <li class="py-3">
                                    <a href="/milestones/view/<?= $milestone->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        <?= htmlspecialchars($milestone->title) ?>
                                    </a>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= !empty($milestone->due_date) ? formatDate($milestone->due_date) : 'No due date' ?>
                                        </span>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $milestoneStatusClass ?>">
                                            <?= htmlspecialchars($milestone->status_name) ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Task Description Column -->
            <div class="md:col-span-2">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Description</h3>
                        <button type="button" class="section-toggle" data-target="task-description">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="task-description" class="p-4">
                        <?php if (!empty($task->description)): ?>
                            <div class="prose dark:prose-invert max-w-none">
                                <?= nl2br(htmlspecialchars($task->description)) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 italic">No description provided</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acceptance Criteria -->
                <?php if (!empty($task->acceptance_criteria)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Acceptance Criteria</h3>
                        <button type="button" class="section-toggle" data-target="task-acceptance-criteria">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="task-acceptance-criteria" class="p-4">
                        <div class="prose dark:prose-invert max-w-none">
                            <?= nl2br(htmlspecialchars($task->acceptance_criteria)) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Subtasks List -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Subtasks</h3>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-500 dark:text-gray-400 mr-2"><?= $completedSubtasks ?>/<span class="font-medium"><?= $totalSubtasks ?></span></span>
                            <button type="button" class="section-toggle" data-target="task-subtasks-main">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div id="task-subtasks-main" class="p-4 max-h-96 overflow-y-auto">
                        <?php if ($totalSubtasks > 0): ?>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($subtasks as $subtask): ?>
                                    <li class="py-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <?php if ($subtask->status_id == 6): // Completed?>
                                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-3 flex-1 min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                        <a href="/tasks/view/<?= $subtask->id ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                            <?= htmlspecialchars($subtask->title) ?>
                                                        </a>
                                                    </p>
                                                    <?php
                                                    $subtaskStatusInfo = getTaskStatusInfo($subtask->status_id);
                                    echo renderStatusPill($subtaskStatusInfo['label'], $subtaskStatusInfo['color'], 'sm');
                                    ?>
                                                </div>
                                                <?php if (hasUserPermission('view_time_tracking') && !empty($subtask->estimated_time)): ?>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <?= formatTimeTracking($subtask->estimated_time) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($subtask->description)): ?>
                                        <div class="mt-2 ml-8 text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                                            <?= nl2br(htmlspecialchars($subtask->description)) ?>
                                        </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No subtasks yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Task Comments -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Comments</h3>
                    </div>
                    <div class="p-4 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (!empty($task->comments)): ?>
                            <div class="mb-4 max-h-96 overflow-y-auto space-y-4">
                                <?php foreach ($task->comments as $comment): ?>
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-semibold text-xs">
                                            <?= substr($comment->first_name ?? 'U', 0, 1) . substr($comment->last_name ?? 'U', 0, 1) ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?= htmlspecialchars(($comment->first_name ?? '') . ' ' . ($comment->last_name ?? '')) ?>
                                                </span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= date('M j, Y g:i A', strtotime($comment->created_at)) ?>
                                                </span>
                                            </div>
                                            <div class="mt-1 text-sm text-gray-800 dark:text-gray-200 text-left leading-relaxed">
                                                <?= nl2br(htmlspecialchars($comment->content)) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.959 8.959 0 01-4.906-1.476L3 21l2.524-5.094A8.959 8.959 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No comments yet</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Be the first to add a comment!</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Comment Form -->
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-600">
                            <form action="/tasks/add-comment/<?= $task->id ?>" method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Add a comment</label>
                                    <textarea
                                        id="content"
                                        name="content"
                                        rows="3"
                                        required
                                        placeholder="Write your comment here..."
                                        class="block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm resize-none"></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                        Post Comment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity History Column -->
            <div class="md:col-span-1">
                <!-- Activity History Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Activity History</h3>
                        <button type="button" class="section-toggle" data-target="task-history">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="task-history" class="p-4 max-h-96 overflow-y-auto">
                        <?php if (!empty($task->history)): ?>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($task->history as $historyItem): ?>
                                    <li class="py-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                                <span class="text-xs font-medium text-white">
                                                    <?= htmlspecialchars(substr($historyItem->first_name ?? 'U', 0, 1) . substr($historyItem->last_name ?? 'U', 0, 1)) ?>
                                                </span>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <div class="text-sm text-gray-900 dark:text-gray-100">
                                                    <span class="font-medium">
                                                        <?= htmlspecialchars(($historyItem->first_name ?? '') . ' ' . ($historyItem->last_name ?? 'Unknown User')) ?>
                                                    </span>
                                                    <span class="ml-1">
                                                        <?= htmlspecialchars($historyItem->action) ?>
                                                    </span>
                                                    <?php if ($historyItem->field_changed): ?>
                                                        <span class="ml-1">
                                                            changed <strong><?= htmlspecialchars($historyItem->field_changed) ?></strong>
                                                            from "<?= htmlspecialchars($historyItem->old_value ?? '(empty)') ?>"
                                                            to "<?= htmlspecialchars($historyItem->new_value ?? '(empty)') ?>"
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <?= date('M j, Y g:i A', strtotime($historyItem->created_at)) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 italic">No activity history available</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
                

    </main>

    <!-- Add Subtask Modal -->
    <div id="addSubtaskModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Subtask</h3>
                    <button type="button" onclick="closeAddSubtaskModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="/tasks/create" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="parent_task_id" value="<?= $task->id ?>">
                    <input type="hidden" name="project_id" value="<?= $task->project_id ?>">
                    <input type="hidden" name="is_subtask" value="1">

                    <!-- Title -->
                    <div>
                        <label for="modal_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title <span class="text-red-500">*</span></label>
                        <input type="text" id="modal_title" name="title" required
                            class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>

                    <!-- Row with Priority and Status -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Priority -->
                        <div>
                            <label for="modal_priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                            <select id="modal_priority" name="priority"
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="none">None</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="modal_status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select id="modal_status_id" name="status_id" required
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="1">Open</option>
                                <option value="2">In Progress</option>
                                <option value="3">On Hold</option>
                                <option value="6">Completed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="modal_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea id="modal_description" name="description" rows="3"
                            class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                    </div>

                    <!-- Estimated Time -->
                    <div>
                        <label for="modal_estimated_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estimated Time (minutes)</label>
                        <input type="number" id="modal_estimated_time" name="estimated_time" min="0"
                            class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeAddSubtaskModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add Subtask
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        // Section toggles
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('.section-toggle');
            
            toggles.forEach(toggle => {
                const targetId = toggle.getAttribute('data-target');
                const targetElement = document.getElementById(targetId);
                
                // Store initial state
                if (!sessionStorage.getItem(targetId)) {
                    sessionStorage.setItem(targetId, 'open');
                }
                
                // Apply initial state
                const initialState = sessionStorage.getItem(targetId);
                if (initialState === 'closed') {
                    targetElement.classList.add('hidden');
                    toggle.querySelector('svg').classList.add('rotate-180');
                }
                
                toggle.addEventListener('click', function() {
                    // Toggle visibility
                    targetElement.classList.toggle('hidden');
                    
                    // Toggle rotation of arrow icon
                    const icon = toggle.querySelector('svg');
                    icon.classList.toggle('rotate-180');
                    
                    // Store state
                    const newState = targetElement.classList.contains('hidden') ? 'closed' : 'open';
                    sessionStorage.setItem(targetId, newState);
                });
            });
        });

        // Timer functionality for active timers
        <?php if ($isTimerActive): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const timerEl = document.getElementById('timer');
            let seconds = <?= $timerDuration ?>;
            
            setInterval(function() {
                seconds++;
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                
                timerEl.textContent = 
                    (hours < 10 ? '0' + hours : hours) + ':' +
                    (minutes < 10 ? '0' + minutes : minutes) + ':' +
                    (secs < 10 ? '0' + secs : secs);
            }, 1000);
        });
        <?php endif; ?>

        // Modal functions
        function openAddSubtaskModal() {
            document.getElementById('addSubtaskModal').classList.remove('hidden');
            document.getElementById('modal_title').focus();
        }

        function closeAddSubtaskModal() {
            document.getElementById('addSubtaskModal').classList.add('hidden');
            // Reset form
            document.getElementById('modal_title').value = '';
            document.getElementById('modal_description').value = '';
            document.getElementById('modal_estimated_time').value = '';
            document.getElementById('modal_priority').value = 'none';
            document.getElementById('modal_status_id').value = '1';
        }

        // Close modal when clicking outside
        document.getElementById('addSubtaskModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddSubtaskModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddSubtaskModal();
            }
        });
    </script>
</body>
</html>