<?php
// file: src/Views/Milestones/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Services\SettingsService;

// Sprint status functions are now available through view_helpers.php

// Helper functions for formatting and styling
function formatDate($date)
{
    if (!$date) {
        return 'Not set';
    }
    $settingsService = SettingsService::getInstance();

    return $settingsService->formatDate($date);
}

// Remove local function - now using centralized helper

// getMilestoneStatusClass function is now in view_helpers.php

$pageTitle = htmlspecialchars($milestone->title) . ' - Milestone Details';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Milestone Header -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-2">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <?= htmlspecialchars($milestone->title) ?>
                            </h1>
                            <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors"
                                    data-type="milestone"
                                    data-item-id="<?= $milestone->id ?>"
                                    data-title="<?= htmlspecialchars($milestone->title) ?>"
                                    data-icon="🎯"
                                    title="Add to favorites">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </button>
                            <?php
                            $statusInfo = getMilestoneStatusInfo($milestone->status_id);
echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
?>
                        </div>
                        <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <?= htmlspecialchars($milestone->milestone_type === 'epic' ? 'Epic' : 'Milestone') ?>
                            </span>
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <a href="/projects/view/<?= $project->id ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                    <?= htmlspecialchars($project->name) ?>
                                </a>
                            </span>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <?php if ($milestone->milestone_type === 'epic'): ?>
                            <a href="/milestones/create?epic_id=<?= $milestone->id ?>"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Milestone
                            </a>
                        <?php endif; ?>
                        <a href="/milestones/edit/<?= $milestone->id ?>"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md shadow-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Milestone
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Milestone Details Grid -->
        <?php
        // Check if we have related milestones to show in additional column
        $hasRelatedMilestones = ($milestone->milestone_type === 'epic' && !empty($relatedMilestones));
$hasRelatedSprints = !empty($relatedSprints ?? []);

// Use CSS Grid with specific column widths: 30% for details, remaining space for tasks/milestones
if ($hasRelatedMilestones) {
    // 30% details + 40% tasks + 30% related milestones
    $gridStyle = 'style="display: grid; grid-template-columns: 30% 1fr 30%; gap: 1.5rem;"';
    $detailsClass = '';
    $tasksClass = '';
    $milestonesClass = '';
} else {
    // 30% details + 70% tasks
    $gridStyle = 'style="display: grid; grid-template-columns: 30% 1fr; gap: 1.5rem;"';
    $detailsClass = '';
    $tasksClass = '';
    $milestonesClass = '';
}
?>
        <div class="grid grid-cols-1 md:grid" <?= $gridStyle ?>>
            <!-- Milestone Details Column (30% width) -->
            <div class="<?= $detailsClass ?>">
                <!-- Milestone Description -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Description</h3>
                        <button type="button" class="section-toggle" data-target="milestone-description">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="milestone-description" class="p-4">
                        <?php if (!empty($milestone->description)): ?>
                            <div class="prose dark:prose-invert max-w-none">
                                <?= nl2br(htmlspecialchars($milestone->description)) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 italic">No description provided</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Milestone Details Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Milestone Details</h3>
                        <button type="button" class="section-toggle" data-target="milestone-details">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="milestone-details" class="p-4 space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Type:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= htmlspecialchars(ucfirst($milestone->milestone_type)) ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Status:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?php
                        $statusInfo = getMilestoneStatusInfo($milestone->status_id);
echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Project:</div>
                            <div class="text-gray-900 dark:text-white">
                                <a href="/projects/view/<?= $project->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <?= htmlspecialchars($project->name) ?>
                                </a>
                            </div>

                            <?php if ($milestone->epic_id && isset($epic)): ?>
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Parent Epic:</div>
                            <div class="text-gray-900 dark:text-white">
                                <a href="/milestones/view/<?= $epic->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <?= htmlspecialchars($epic->title) ?>
                                </a>
                            </div>
                            <?php endif; ?>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Start Date:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= $milestone->start_date ? formatDate($milestone->start_date) : 'Not set' ?>
                            </div>

                            <div class="text-gray-500 dark:text-gray-400 font-medium">Due Date:</div>
                            <div class="text-gray-900 dark:text-white <?= (!empty($milestone->due_date) && strtotime($milestone->due_date) < time() && $milestone->status_id != 3) ? 'text-red-600 dark:text-red-400' : '' ?>">
                                <?= $milestone->due_date ? formatDate($milestone->due_date) : 'Not set' ?>
                            </div>

                            <?php if ($milestone->complete_date): ?>
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Completed:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= formatDate($milestone->complete_date) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Related Sprints (in same column as details) -->
                <?php if ($hasRelatedSprints): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Related Sprints</h3>
                        <button type="button" class="section-toggle" data-target="related-sprints">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="related-sprints" class="p-4">
                        <?php if (!empty($relatedSprints ?? [])): ?>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($relatedSprints as $sprint): ?>
                                <li class="py-3">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <a href="/sprints/view/<?= $sprint->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                                <?= htmlspecialchars($sprint->name) ?>
                                            </a>
                                            <div class="mt-1">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= formatDate($sprint->start_date) ?> - <?= formatDate($sprint->end_date) ?>
                                                </span>
                                                <?php if ($sprint->shared_tasks > 0): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        <?= $sprint->shared_tasks ?> shared task<?= $sprint->shared_tasks > 1 ? 's' : '' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end space-y-1">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= getGlobalSprintStatusClass($sprint->status_id) ?>">
                                                <?= htmlspecialchars($sprint->status_name) ?>
                                            </span>
                                            <?php if ($sprint->total_sprint_tasks > 0): ?>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= $sprint->completed_tasks ?>/<?= $sprint->total_sprint_tasks ?> tasks
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No related sprints found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Associated Tasks Column -->
            <div class="<?= $tasksClass ?>">

                <!-- Associated Tasks -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Associated Tasks</h3>
                        <button type="button" class="section-toggle" data-target="milestone-tasks">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="milestone-tasks" class="overflow-hidden">
                        <?php if (!empty($milestone->tasks)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Assignee</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($milestone->tasks as $task): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <a href="/tasks/view/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                                <?= htmlspecialchars($task->title) ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                    $statusInfo = getTaskStatusInfo($task->status_id ?? 1);
                                            echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
                                            ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    <?= $task->first_name && $task->last_name
                                                ? htmlspecialchars("{$task->first_name} {$task->last_name}")
                                                : 'Unassigned' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    <?= $task->due_date ? formatDate($task->due_date) : 'Not set' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No tasks associated with this milestone</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Milestones Column -->
            <?php if ($hasRelatedMilestones): ?>
            <div class="<?= $milestonesClass ?>">
                <!-- Related Milestones for Epic -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Related Milestones</h3>
                        <button type="button" class="section-toggle" data-target="related-milestones">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="related-milestones" class="p-4">
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($relatedMilestones as $related): ?>
                                <li class="py-3">
                                    <a href="/milestones/view/<?= $related->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        <?= htmlspecialchars($related->title) ?>
                                    </a>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= !empty($related->due_date) ? formatDate($related->due_date) : 'No due date' ?>
                                        </span>
                                        <?php
                                        $statusInfo = getMilestoneStatusInfo($related->status_id);
                                echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
                                ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>


        </div>

            <!-- Delete Milestone Modal (Hidden by default) -->
            <div id="deleteMilestoneModal" class="modal hidden">
                <div class="modal-content">
                    <h2 class="text-xl font-bold mb-4">Confirm Deletion</h2>
                    <p>Are you sure you want to delete this milestone? This action cannot be undone.</p>
                    <div class="flex justify-end space-x-2 mt-4">
                        <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
                        <form action="/milestones/delete/<?= $milestone->id ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?? '' ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- JavaScript for collapsible sections -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle section toggles
            const toggleButtons = document.querySelectorAll('.section-toggle');

            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetElement = document.getElementById(targetId);
                    const icon = this.querySelector('svg');

                    if (targetElement) {
                        targetElement.classList.toggle('hidden');
                        icon.classList.toggle('rotate-180');
                    }
                });
            });

            // Handle delete modal
            const deleteModal = document.getElementById('deleteMilestoneModal');
            const cancelDelete = document.getElementById('cancelDelete');

            if (cancelDelete) {
                cancelDelete.addEventListener('click', function() {
                    deleteModal.classList.add('hidden');
                });
            }
        });
    </script>
</body>
</html>