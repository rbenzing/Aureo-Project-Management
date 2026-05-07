<?php
//file: Views/Projects/inc/table_sprints.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
?>

<!-- Sprint View -->
<div class="p-6">
    <?php foreach ($project->sprints as $sprint): ?>
        <?php
        // Get sprint tasks if not already loaded
        if (!isset($sprint->tasks) || empty($sprint->tasks)) {
            $sprintTasks = (new \App\Models\Sprint())->getSprintTasks($sprint->id);
        } else {
            $sprintTasks = $sprint->tasks;
        }

        // Calculate sprint progress
        $totalTasks = count($sprintTasks);
        $completedTasks = 0;
        foreach ($sprintTasks as $task) {
            if (isset($task->status_id) && $task->status_id == 6) { // Completed status is 6
                $completedTasks++;
            }
        }
        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Set sprint status class
        $statusClass = '';
        $statusId = $sprint->status_id ?? 1;
        switch ($statusId) {
            case 1:
                $statusClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';

                break; // Planning
            case 2:
                $statusClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';

                break; // Active
            case 3:
                $statusClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

                break; // Completed
            case 4:
                $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';

                break; // Cancelled
            case 5:
                $statusClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

                break; // Delayed
            default:
                $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }
        ?>
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <div class="p-4 flex justify-between items-start border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h4 class="text-base font-medium text-gray-900 dark:text-white flex items-center">
                        <a href="/sprints/view/<?= $sprint->id ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                            <?= htmlspecialchars($sprint->name) ?>
                        </a>
                        <span class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?= $statusClass ?>">
                            <?= isset($sprint->status_name) ? htmlspecialchars($sprint->status_name) : 'Unknown' ?>
                        </span>
                    </h4>
                    <?php if (!empty($sprint->description)): ?>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <?= nl2br(htmlspecialchars(substr($sprint->description, 0, 150))) ?>
                            <?= strlen($sprint->description) > 150 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col items-end space-y-2">
                    <div class="flex text-md text-gray-500 dark:text-gray-400">
                        <?php if (isset($sprint->start_date) && !empty($sprint->start_date)): ?><span><?= date('M j, Y', strtotime($sprint->start_date)) ?></span><?php endif; ?><?php if (isset($sprint->end_date) && !empty($sprint->end_date)): ?><span><?= "&nbsp;-&nbsp;" . date('M j, Y', strtotime($sprint->end_date)) ?></span><?php endif; ?>
                    </div>
                    <div class="w-48">
                        <div class="flex items-center">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300 mr-2"><?= $progressPercentage ?>%</span>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $progressPercentage ?>%"></div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 text-right mt-1">
                            <?= $completedTasks ?>/<?= $totalTasks ?> tasks completed
                        </div>
                    </div>
                </div>
            </div>

            <!-- Display tasks associated with this sprint -->
            <?php if (!empty($sprintTasks)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Assignee</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($sprintTasks as $task): ?>
                                <?php
                                $priorityLevel = isset($task->priority) ? $task->priority : 'none';
                                $priorityClasses = [
                                    'high' => 'text-red-600 dark:text-red-400',
                                    'medium' => 'text-yellow-600 dark:text-yellow-400',
                                    'low' => 'text-blue-600 dark:text-blue-400',
                                    'none' => 'text-gray-600 dark:text-gray-400',
                                ];
                                $priorityClass = $priorityClasses[$priorityLevel] ?? 'text-gray-600 dark:text-gray-400';

                                $taskStatusMap = [
                                    1 => ['label' => 'Open', 'class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'],
                                    2 => ['label' => 'In Progress', 'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'],
                                    3 => ['label' => 'On Hold', 'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'],
                                    4 => ['label' => 'In Review', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200'],
                                    5 => ['label' => 'Closed', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'],
                                    6 => ['label' => 'Completed', 'class' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
                                ];
                                $taskStatus = $taskStatusMap[$task->status_id] ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'];
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
                                        <span class="text-sm font-medium <?= $priorityClass ?>">
                                            <?= ucfirst($priorityLevel) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $taskStatus['class'] ?>">
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-sm text-gray-500 dark:text-gray-400 italic">
                    No tasks associated with this sprint.
                </div>
            <?php endif; ?>

            <!-- Sprint Actions (only shown for active sprints) -->
            <?php if ($statusId == 2): // Active sprint
                ?>
                <div class="p-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex justify-end space-x-3">
                    <a href="/sprints/view/<?= $sprint->id ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                        View Details
                    </a>
                    <?php if (isset($currentUser['permissions']) && in_array('edit_sprints', $currentUser['permissions'])): ?>
                        <a href="/sprints/edit/<?= $sprint->id ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent shadow-sm text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Manage Tasks
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>