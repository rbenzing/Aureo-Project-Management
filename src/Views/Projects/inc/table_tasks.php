<?php
//file: Views/Projects/inc/table_tasks.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Utils\Sort;
use App\Utils\Time;

// Include helper functions for consistent styling
include_once BASE_PATH . '/../src/Views/Tasks/inc/helper_functions.php';

// Get current sort parameters
$taskSortField = isset($_GET['task_sort']) ? htmlspecialchars($_GET['task_sort']) : 'priority';
$taskSortDir = isset($_GET['task_dir']) && $_GET['task_dir'] === 'asc' ? 'asc' : 'desc';
?>
<!-- Tasks View -->
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <a href="<?= Sort::getUrl('title', $taskSortField, $taskSortDir, 'task_sort', 'task_dir') ?>" class="group inline-flex items-center">
                        Task
                        <?= Sort::getIndicator('title', $taskSortField, $taskSortDir) ?>
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <a href="<?= Sort::getUrl('assigned_to', $taskSortField, $taskSortDir, 'task_sort', 'task_dir') ?>" class="group inline-flex items-center">
                        Assignee
                        <?= Sort::getIndicator('assigned_to', $taskSortField, $taskSortDir) ?>
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <a href="<?= Sort::getUrl('priority', $taskSortField, $taskSortDir, 'task_sort', 'task_dir') ?>" class="group inline-flex items-center">
                        Priority
                        <?= Sort::getIndicator('priority', $taskSortField, $taskSortDir) ?>
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <a href="<?= Sort::getUrl('status_id', $taskSortField, $taskSortDir, 'task_sort', 'task_dir') ?>" class="group inline-flex items-center">
                        Status
                        <?= Sort::getIndicator('status_id', $taskSortField, $taskSortDir) ?>
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <a href="<?= Sort::getUrl('due_date', $taskSortField, $taskSortDir, 'task_sort', 'task_dir') ?>" class="group inline-flex items-center">
                        Due Date
                        <?= Sort::getIndicator('due_date', $taskSortField, $taskSortDir) ?>
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <a href="<?= Sort::getUrl('time_spent', $taskSortField, $taskSortDir, 'task_sort', 'task_dir') ?>" class="group inline-flex items-center">
                        Time Spent
                        <?= Sort::getIndicator('time_spent', $taskSortField, $taskSortDir) ?>
                    </a>
                </th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (isset($project->tasks) && !empty($project->tasks)): ?>
                <?php foreach ($project->tasks as $task): ?>
                    <?php
                    // Priority level - use helper functions for consistency
                    $priorityLevel = isset($task->priority) ? $task->priority : 'none';

                    // Status mapping to match tasks page style
                    $taskStatusMap = [
                        1 => ['label' => 'OPEN', 'color' => 'bg-blue-600'],
                        2 => ['label' => 'IN PROGRESS', 'color' => 'bg-yellow-500'],
                        3 => ['label' => 'ON HOLD', 'color' => 'bg-purple-500'],
                        4 => ['label' => 'IN REVIEW', 'color' => 'bg-indigo-500'],
                        5 => ['label' => 'CLOSED', 'color' => 'bg-gray-500'],
                        6 => ['label' => 'COMPLETED', 'color' => 'bg-green-500'],
                        7 => ['label' => 'CANCELLED', 'color' => 'bg-red-500'],
                    ];
                    $taskStatus = $taskStatusMap[$task->status_id] ?? ['label' => 'UNKNOWN', 'color' => 'bg-gray-500'];
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="/tasks/view/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                <?= htmlspecialchars($task->title) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (isset($task->assigned_to) && !empty($task->assigned_to)): ?>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <?php if (isset($task->first_name) && isset($task->last_name)): ?>
                                        <?= htmlspecialchars($task->first_name) ?> <?= htmlspecialchars($task->last_name) ?>
                                    <?php else: ?>
                                        <?php
                                        // Fetch user details if not provided with task
                                        $user = (new \App\Models\User())->find($task->assigned_to);
                                        if ($user): ?>
                                            <?= htmlspecialchars($user->first_name) ?> <?= htmlspecialchars($user->last_name) ?>
                                        <?php else: ?>
                                            ID: <?= $task->assigned_to ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getPriorityClasses($priorityLevel) ?>">
                                <?= getPriorityIcon($priorityLevel) ?>
                                <?= ucfirst(htmlspecialchars($priorityLevel)) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 text-xs rounded-full bg-opacity-20 text-white font-medium whitespace-nowrap <?= $taskStatus['color'] ?>">
                                <?= $taskStatus['label'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            <?php if (isset($task->due_date) && !empty($task->due_date)): ?>
                                <?php
                                $dueDate = strtotime($task->due_date);
                                $today = strtotime('today');
                                $isDue = $dueDate < $today && ($task->status_id != 6 && $task->status_id != 5);
                                ?>
                                <span class="<?= $isDue ? 'text-red-600 dark:text-red-400 font-medium' : '' ?> whitespace-nowrap">
                                    <?= date('M j, Y', $dueDate) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            <?= Time::formatSeconds($task->time_spent ?? 0) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        No tasks found for this project
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>

        <!-- Project Summary Row -->
        <?php if (isset($project->tasks) && !empty($project->tasks)): ?>
            <tfoot class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <td colspan="5" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Summary: <?= $completedTasks ?> / <?= $totalTasks ?> Tasks Completed
                    </td>
                    <td class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Total: <?= Time::formatSeconds($totalTime) ?>
                    </td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>