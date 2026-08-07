<?php
// file: Views/Sprints/inc/sprint_list.php
// Sprint listing table component
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
?>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <div class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Sprint
                    </div>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <div class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Duration
                    </div>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <div class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Status
                    </div>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <div class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Tasks
                    </div>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <div class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Velocity
                    </div>
                </th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <span class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Actions
                    </span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700" id="sprints-list">
            <?php if (!empty($sprints)): ?>
                <?php foreach ($sprints as $sprint):
                    // Safe access to properties with fallbacks
                    $totalTasks = isset($sprint->task_count) ? $sprint->task_count : 0;
                    $completedTasks = isset($sprint->completed_tasks) ? $sprint->completed_tasks : 0;
                    $taskPercentage = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

                    // Calculate days from start to end
                    $sprintDays = 0;
                    $daysLeft = 0;

                    if (isset($sprint->start_date) && isset($sprint->end_date)) {
                        $startDate = new DateTime($sprint->start_date);
                        $endDate = new DateTime($sprint->end_date);
                        $interval = $startDate->diff($endDate);
                        $sprintDays = $interval->days + 1; // +1 to include both start and end dates

                        // Check if sprint is ongoing
                        $now = new DateTime();
                        if (isset($sprint->status_id) && $sprint->status_id == 2 && $now >= $startDate && $now <= $endDate) {
                            $daysLeft = $now->diff($endDate)->days;
                        }
                    }
                    ?>
                    <tr class="sprint-row hover:bg-gray-50 dark:hover:bg-gray-700" 
                        data-status="<?= $sprint->status_id ?? 0 ?>"
                        data-name="<?= htmlspecialchars($sprint->name ?? '') ?>">
                        <td class="px-6 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    <a href="/sprints/view/<?= $sprint->id ?? 0 ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        <?= htmlspecialchars($sprint->name ?? 'Sprint') ?>
                                    </a>
                                </div>
                                <?php if (!empty($sprint->description)): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                        <?= nl2br(htmlspecialchars(substr($sprint->description, 0, 60))) . (strlen($sprint->description) > 60 ? '...' : '') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (isset($sprint->start_date) && isset($sprint->end_date)): ?>
                                <div class="text-sm text-gray-900 dark:text-gray-200">
                                    <?= date('M j', strtotime($sprint->start_date)) ?> - <?= date('M j, Y', strtotime($sprint->end_date)) ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?= $sprintDays ?> days
                                    <?php if ($daysLeft > 0): ?>
                                        <span class='ml-2 font-medium text-blue-600 dark:text-blue-400'><?= $daysLeft ?> days left</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Date not set</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                                $statusInfo = getSprintStatusInfo($sprint->status_id ?? 0);
                    echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
                    ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-full max-w-[120px]">
                                    <div class="text-xs mb-1 flex justify-between">
                                        <span class="font-medium text-gray-900 dark:text-gray-200"><?= $completedTasks ?>/<?= $totalTasks ?></span>
                                        <span class="text-gray-500 dark:text-gray-400"><?= round($taskPercentage) ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5">
                                        <div class="bg-green-500 h-1.5 rounded-full" style="width: <?= $taskPercentage ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (isset($sprint->status_id) && $sprint->status_id == 4): // Completed?>
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    <?= isset($sprint->velocity_percentage) ? round($sprint->velocity_percentage) . '%' : 'N/A' ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-500 dark:text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end space-x-3">
                                <a
                                    href="/sprints/view/<?= $sprint->id ?? 0 ?>"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    title="View Sprint"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                <a
                                    href="/sprints/edit/<?= $sprint->id ?? 0 ?>"
                                    class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                    title="Edit Sprint"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <form
                                    action="/sprints/delete/<?= $sprint->id ?? 0 ?>"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this sprint?');"
                                    class="inline"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                    <button
                                        type="submit"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                        title="Delete Sprint"
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
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                        No sprints found for this project. <a href="/sprints/create/<?= $project->id ?? 0 ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Create your first sprint</a>.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>