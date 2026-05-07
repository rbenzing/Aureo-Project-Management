<?php
//file: Views/Projects/inc/table_milestones.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
?>
<!-- Milestone View -->
<div class="p-6">

    <?php foreach ($project->milestones as $milestone): ?>
        <?php
        // Get milestone tasks if not already loaded
        if (!isset($milestone->tasks) || empty($milestone->tasks)) {
            $milestoneTasks = (new \App\Models\Milestone())->getTasks($milestone->id);
        } else {
            $milestoneTasks = $milestone->tasks;
        }

        $statusClass = getMilestoneStatusClass($milestone->status_id);
        ?>
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <div class="p-4 flex justify-between items-start border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h4 class="text-base font-medium text-gray-900 dark:text-white">
                        <a href="/milestones/view/<?= $milestone->id ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                            <?= htmlspecialchars($milestone->title) ?>
                        </a>
                        <span class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?= $statusClass ?>">
                            <?= isset($milestone->status_name) ? htmlspecialchars($milestone->status_name) : 'Unknown' ?>
                        </span>
                    </h4>
                    <?php if (!empty($milestone->description)): ?>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <?= nl2br(htmlspecialchars(substr($milestone->description, 0, 150))) ?>
                            <?= strlen($milestone->description) > 150 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex text-md text-gray-500 dark:text-gray-400">
                    <?php if (isset($milestone->start_date) && !empty($milestone->start_date)): ?>
                        <span><?= date('M j, Y', strtotime($milestone->start_date)) ?></span>
                    <?php endif; ?>
                    <?php if (isset($milestone->due_date) && !empty($milestone->due_date)): ?>
                        <span><?= "&nbsp;-&nbsp;" . date('M j, Y', strtotime($milestone->due_date)) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Display tasks associated with this milestone -->
            <?php if (!empty($milestoneTasks)): ?>
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
                            <?php foreach ($milestoneTasks as $task): ?>
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
                    No tasks associated with this milestone.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>