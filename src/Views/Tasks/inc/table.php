<?php
//file: Views/Tasks/inc/table.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Utils\Time;

include_once BASE_PATH . '/../src/Views/Tasks/inc/helper_functions.php';

// Using centralized status helper system
?>

<tbody class="divide-y divide-gray-200 dark:divide-gray-700">
    <?php
    // Handle both flat array and organized by status array
    $flatTasks = [];
if (!empty($tasks)) {
    // Check if tasks are organized by status (project context) or flat array (other contexts)
    if (is_array($tasks) && (isset($tasks['open']) || isset($tasks['in_progress']) || isset($tasks['completed']))) {
        // Tasks are organized by status - flatten them
        foreach ($tasks as $statusGroup) {
            if (is_array($statusGroup)) {
                $flatTasks = array_merge($flatTasks, $statusGroup);
            }
        }
    } else {
        // Tasks are already a flat array
        $flatTasks = $tasks;
    }
}
?>
    <?php if (!empty($flatTasks)): ?>
        <?php foreach ($flatTasks as $task): ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors task-row"
                data-task-id="<?= $task->id ?>"
                data-priority="<?= $task->priority ?>"
                data-status="<?= $task->status_name ?>"
                data-due-date="<?= $task->due_date ?? '' ?>"
                data-assigned="<?= !empty($task->first_name) ? $task->first_name . ' ' . $task->last_name : '' ?>"
                data-is-subtask="<?= $task->is_subtask ? '1' : '0' ?>">
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <?php if ($task->is_subtask): ?>
                            <div class="ml-2 mr-3 text-gray-400 dark:text-gray-500">↳</div>
                        <?php endif; ?>
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                <a href="/tasks/view/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <?= htmlspecialchars($task->title) ?>
                                </a>
                            </div>
                            <?php if (!empty($task->description)): ?>
                                <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                    <?= nl2br(htmlspecialchars(substr($task->description, 0, 60))) . (strlen($task->description) > 60 ? '...' : '') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-indigo-500 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                        <span class="text-sm text-gray-900 dark:text-gray-200">
                            <?= htmlspecialchars($task->project_name ?? 'N/A') ?>
                        </span>
                    </div>
                </td>
                <?php if (!$isMyTasksView): // Only show assignee column in backlog view?>
                <td class="px-6 py-4">
                    <?php if (!empty($task->first_name)): ?>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                <span class="text-xs font-medium text-white">
                                    <?= htmlspecialchars(substr($task->first_name, 0, 1) . substr($task->last_name, 0, 1)) ?>
                                </span>
                            </div>
                            <div class="ml-2">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    <?= htmlspecialchars($task->first_name . ' ' . $task->last_name) ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Unassigned
                        </span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getPriorityClasses($task->priority) ?>">
                        <?= getPriorityIcon($task->priority) ?>
                        <?= ucfirst(htmlspecialchars($task->priority)) ?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php
                $statusId = $task->status_id ?? 1;
            $statusInfo = getTaskStatusInfo($statusId);
            echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
            ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if (!empty($task->due_date)): ?>
                        <?php $dueDateInfo = getDueDateDisplay($task->due_date, $task->status_name); ?>
                        <div class="space-y-1">
                            <!-- Date Row -->
                            <div class="text-sm <?= $dueDateInfo['class'] ?> justify-start whitespace-nowrap">
                                <?= $dueDateInfo['icon'] ?>
                                <?= date('M j, Y', strtotime($task->due_date)) ?>
                            </div>
                            <!-- Status Badge Row -->
                            <?php if (!empty($dueDateInfo['badge'])): ?>
                                <div class="whitespace-nowrap">
                                    <?= $dueDateInfo['badge'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="space-y-1">
                            <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center whitespace-nowrap">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-dasharray="2"></path>
                                </svg>
                                No Due Date
                            </div>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 dark:text-gray-200">
                        <?php if ($task->time_spent > 0): ?>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <?= Time::formatSeconds($task->time_spent) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-gray-500 dark:text-gray-400 flex items-center">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-dasharray="2"></path>
                                </svg>
                                0h 0m
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($task->estimated_time) && hasUserPermission('view_time_tracking')): ?>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Est: <?= Time::formatSeconds($task->estimated_time) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex justify-end space-x-3">
                        <?php
                // Only show timer button if user has time tracking permissions and can access the task
                $canTimeTrack = (hasUserPermission('view_time_tracking') || hasUserPermission('create_time_tracking')) &&
                              ($viewingOwnTasks ||
                               ($task->assigned_to == $currentUserId) ||
                               hasUserPermission('manage_tasks'));

            // Check if this specific task has an active timer
            $isTimerActiveForThisTask = isset($activeTimer) && $activeTimer['task_id'] == $task->id;

            if ($canTimeTrack && $task->status_name !== 'Completed' && $task->status_name !== 'Closed'):
                if ($isTimerActiveForThisTask):
                    ?>
                            <!-- Stop Timer Button -->
                            <form action="/tasks/stop-timer/<?= $task->id ?>" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                <button
                                    type="submit"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    title="Stop Timer"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                    </svg>
                                </button>
                            </form>
                        <?php elseif (!isset($activeTimer)): // Only show start timer if no timer is running at all?>
                            <!-- Start Timer Button -->
                            <form action="/tasks/start-timer/<?= $task->id ?>" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                <button
                                    type="submit"
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                    title="Start Timer"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <a 
                            href="/tasks/view/<?= $task->id ?>" 
                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                            title="View Task"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        <a 
                            href="/tasks/edit/<?= $task->id ?>" 
                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                            title="Edit Task"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <form 
                            action="/tasks/delete/<?= $task->id ?>" 
                            method="POST" 
                            onsubmit="return confirm('Are you sure you want to delete this task?');"
                            class="inline"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                            <button 
                                type="submit" 
                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                title="Delete Task"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="<?= $isMyTasksView ? '7' : '8' ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center py-6">
                    <svg class="w-12 h-12 mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <p class="text-lg font-medium">No tasks found</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Get started by creating your first task
                    </p>
                    <a href="/tasks/create<?= $isMyTasksView && !$viewingOwnTasks ? '?assign_to=' . htmlspecialchars($userId) : '' ?>" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create Task
                    </a>
                </div>
            </td>
        </tr>
    <?php endif; ?>
</tbody>