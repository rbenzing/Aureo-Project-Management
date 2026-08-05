<?php
// file: Views/Sprints/board.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

$pageTitle = 'Sprint Board - ' . htmlspecialchars($sprint->name ?? 'Sprint');
$currentPage = 'sprints';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
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
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>
        <!-- Breadcrumb and Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div class="flex-1">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="/dashboard" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/sprints" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 dark:text-gray-400 dark:hover:text-white">Sprints</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/sprints/view/<?= $sprint->id ?>" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 dark:text-gray-400 dark:hover:text-white"><?= htmlspecialchars($sprint->name) ?></a>
                            </div>
                        </li>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">Board</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>
            <div class="flex space-x-3">
                <a href="/sprints/view/<?= $sprint->id ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Sprint Details
                </a>
                <a href="/tasks/create?sprint_id=<?= $sprint->id ?>&project_id=<?= $project->id ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Task
                </a>
            </div>
        </div>


        <!-- Sprint Header -->
        <div class="mb-8">
            <div class="flex items-center">
                <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h2a2 2 0 002-2z"></path>
                </svg>
                <div>
                    <div class="flex items-center">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($sprint->name) ?></h1>
                        <?php
                        $statusInfo = getSprintStatusInfo($sprint->status_id);
echo '<span class="ml-3">' . renderStatusPill($statusInfo['label'], $statusInfo['color'], 'md') . '</span>';
?>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Project: <a href="/projects/view/<?= $project->id ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= htmlspecialchars($project->name) ?></a>
                    </p>
                </div>
            </div>
            <?php if (!empty($sprint->sprint_goal)): ?>
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-400 rounded-r-md">
                    <p class="text-blue-800 dark:text-blue-200 font-medium">
                        <span class="text-sm font-semibold">Sprint Goal:</span> <?= htmlspecialchars($sprint->sprint_goal) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sprint Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Tasks</dt>
                                <dd class="text-lg font-medium text-gray-900 dark:text-white"><?= $sprintStats['total_tasks'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Completed</dt>
                                <dd class="text-lg font-medium text-gray-900 dark:text-white"><?= $sprintStats['completed_tasks'] ?> (<?= $sprintStats['completion_percentage'] ?>%)</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Story Points</dt>
                                <dd class="text-lg font-medium text-gray-900 dark:text-white"><?= $sprintStats['completed_story_points'] ?>/<?= $sprintStats['total_story_points'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Hours</dt>
                                <dd class="text-lg font-medium text-gray-900 dark:text-white"><?= round($sprintStats['completed_estimated_hours'], 1) ?>/<?= round($sprintStats['total_estimated_hours'], 1) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanban Board -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Sprint Board</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Drag tasks between columns to update their status</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <!-- Column Filter Dropdown -->
                        <div class="relative">
                            <button type="button" id="column-filter-toggle" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7V4z"></path>
                                </svg>
                                <span id="filter-button-text">Filter Columns</span>
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="column-filter-menu" class="hidden absolute right-0 z-10 mt-2 w-72 bg-white dark:bg-gray-700 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <div class="p-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white mb-3">Visible Columns</div>
                                    <div class="space-y-3">
                                        <?php foreach ($tasksByStatus as $statusId => $statusData): ?>
                                            <label class="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 p-2 rounded">
                                                <input type="checkbox"
                                                       class="column-filter-checkbox h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 focus:ring-2"
                                                       data-status-id="<?= $statusId ?>"
                                                       checked>
                                                <span class="ml-3 text-sm text-gray-700 dark:text-gray-200 flex-1">
                                                    <?= htmlspecialchars($statusData['status']->name) ?>
                                                </span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-600 px-2 py-1 rounded-full">
                                                    <?= count($statusData['tasks']) ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600 flex space-x-2">
                                        <button type="button" id="show-all-columns" class="flex-1 px-3 py-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900 rounded border border-indigo-200 dark:border-indigo-600">Show All</button>
                                        <button type="button" id="hide-empty-columns" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-600 rounded border border-gray-200 dark:border-gray-600">Hide Empty</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="flex gap-6 overflow-x-auto min-h-96" id="kanban-board">
                    <?php foreach ($tasksByStatus as $statusId => $statusData): ?>
                        <?php if (!empty($statusData['tasks']) || in_array($statusId, [1, 2, 3, 4, 6])): // Show main statuses even if empty?>
                            <div class="kanban-column bg-gray-50 dark:bg-gray-700 rounded-lg p-4 min-h-96 flex-shrink-0 w-80" data-status-id="<?= $statusId ?>">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php
                $statusInfo = getTaskStatusInfo($statusId);
                            echo htmlspecialchars($statusInfo['label']);
                            ?>
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                        <?= count($statusData['tasks']) ?>
                                    </span>
                                </div>
                                
                                <div class="space-y-3 kanban-tasks" data-status-id="<?= $statusId ?>">
                                    <?php foreach ($statusData['tasks'] as $task): ?>
                                        <div class="kanban-task bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600 cursor-move" 
                                             data-task-id="<?= $task->id ?>" 
                                             data-current-status="<?= $task->status_id ?>"
                                             draggable="true">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                                                        <a href="/tasks/view/<?= $task->id ?>" class="hover:text-blue-600 dark:hover:text-blue-400">
                                                            <?= htmlspecialchars($task->title) ?>
                                                        </a>
                                                    </h4>
                                                    
                                                    <?php if (!empty($task->description)): ?>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2">
                                                            <?= htmlspecialchars(substr($task->description, 0, 80)) ?><?= strlen($task->description) > 80 ? '...' : '' ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="flex items-center space-x-2 mb-2">
                                                        <?php if ($task->story_points): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                <?= $task->story_points ?> SP
                                                            </span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($task->estimated_time): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                <?= round($task->estimated_time / 3600, 1) ?>h
                                                            </span>
                                                        <?php endif; ?>
                                                        
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                            <?php
                                                $priority = $task->priority ?? 'none';
                                        switch (strtolower($priority)) {
                                            case 'high': echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

                                                break;
                                            case 'medium': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';

                                                break;
                                            case 'low': echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

                                                break;
                                            default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                        }
                                        ?>">
                                                            <?php
                                        switch (strtolower($priority)) {
                                            case 'high': echo 'High';

                                                break;
                                            case 'medium': echo 'Medium';

                                                break;
                                            case 'low': echo 'Low';

                                                break;
                                            default: echo 'Normal';
                                        }
                                        ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <?php if (!empty($task->first_name) && !empty($task->last_name)): ?>
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-500 flex items-center justify-center">
                                                                <span class="text-xs font-medium text-white">
                                                                    <?= htmlspecialchars(substr($task->first_name, 0, 1) . substr($task->last_name, 0, 1)) ?>
                                                                </span>
                                                            </div>
                                                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                                                <?= htmlspecialchars($task->first_name . ' ' . $task->last_name) ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="ml-2">
                                                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Drag to move">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kanbanBoard = document.getElementById('kanban-board');
    const kanbanColumns = document.querySelectorAll('.kanban-column');
    const kanbanTasks = document.querySelectorAll('.kanban-task');

    let draggedTask = null;
    let draggedFromColumn = null;

    // Column Filter Functionality
    const filterToggle = document.getElementById('column-filter-toggle');
    const filterMenu = document.getElementById('column-filter-menu');
    const filterCheckboxes = document.querySelectorAll('.column-filter-checkbox');
    const showAllBtn = document.getElementById('show-all-columns');
    const hideEmptyBtn = document.getElementById('hide-empty-columns');

    // Toggle filter dropdown
    if (filterToggle && filterMenu) {
        filterToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            filterMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!filterToggle.contains(e.target) && !filterMenu.contains(e.target)) {
                filterMenu.classList.add('hidden');
            }
        });

        // Prevent dropdown from closing when clicking inside
        filterMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Handle individual column filter checkboxes
    filterCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function(e) {
            e.stopPropagation();
            const statusId = this.dataset.statusId;
            const column = document.querySelector(`.kanban-column[data-status-id="${statusId}"]`);

            if (column) {
                if (this.checked) {
                    column.classList.remove('hidden');
                    column.style.display = '';
                } else {
                    column.classList.add('hidden');
                }
            }

            // Update filter button text to show active filters
            updateFilterButtonText();
        });
    });

    // Show all columns
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            filterCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
                const statusId = checkbox.dataset.statusId;
                const column = document.querySelector(`.kanban-column[data-status-id="${statusId}"]`);
                if (column) {
                    column.classList.remove('hidden');
                    column.style.display = '';
                }
            });

            updateFilterButtonText();
        });
    }

    // Hide empty columns
    if (hideEmptyBtn) {
        hideEmptyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            filterCheckboxes.forEach(checkbox => {
                const statusId = checkbox.dataset.statusId;
                const column = document.querySelector(`.kanban-column[data-status-id="${statusId}"]`);
                const tasksContainer = column?.querySelector('.kanban-tasks');
                const taskCount = tasksContainer?.children.length || 0;

                if (taskCount === 0) {
                    checkbox.checked = false;
                    if (column) {
                        column.classList.add('hidden');
                    }
                } else {
                    checkbox.checked = true;
                    if (column) {
                        column.classList.remove('hidden');
                        column.style.display = '';
                    }
                }
            });

            updateFilterButtonText();
        });
    }

    // Update filter button text based on active filters
    function updateFilterButtonText() {
        const totalColumns = filterCheckboxes.length;
        const visibleColumns = Array.from(filterCheckboxes).filter(cb => cb.checked).length;
        const buttonTextElement = document.getElementById('filter-button-text');

        if (buttonTextElement) {
            if (visibleColumns === totalColumns) {
                buttonTextElement.textContent = 'Filter Columns';
            } else {
                buttonTextElement.textContent = `Filter Columns (${visibleColumns}/${totalColumns})`;
            }
        }
    }

    // Make tasks draggable
    kanbanTasks.forEach(task => {
        task.addEventListener('dragstart', handleDragStart);
        task.addEventListener('dragend', handleDragEnd);
    });

    // Make columns drop zones
    kanbanColumns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
    });

    function handleDragStart(e) {
        draggedTask = e.target;
        draggedFromColumn = e.target.closest('.kanban-column');

        e.target.style.opacity = '0.5';
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', e.target.outerHTML);
    }

    function handleDragEnd(e) {
        e.target.style.opacity = '1';

        // Clean up any visual indicators
        kanbanColumns.forEach(column => {
            column.classList.remove('bg-blue-100', 'dark:bg-blue-800', 'border-blue-300', 'dark:border-blue-600');
        });

        draggedTask = null;
        draggedFromColumn = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function handleDragEnter(e) {
        e.preventDefault();
        const column = e.target.closest('.kanban-column');
        if (column && column !== draggedFromColumn) {
            column.classList.add('bg-blue-100', 'dark:bg-blue-800', 'border-2', 'border-blue-300', 'dark:border-blue-600');
        }
    }

    function handleDragLeave(e) {
        const column = e.target.closest('.kanban-column');
        if (column && !column.contains(e.relatedTarget)) {
            column.classList.remove('bg-blue-100', 'dark:bg-blue-800', 'border-2', 'border-blue-300', 'dark:border-blue-600');
        }
    }

    function handleDrop(e) {
        e.preventDefault();

        const targetColumn = e.target.closest('.kanban-column');
        const targetTasksContainer = targetColumn.querySelector('.kanban-tasks');

        if (!targetColumn || !draggedTask) return;

        // Remove visual indicators
        targetColumn.classList.remove('bg-blue-100', 'dark:bg-blue-800', 'border-2', 'border-blue-300', 'dark:border-blue-600');

        const newStatusId = targetColumn.dataset.statusId;
        const taskId = draggedTask.dataset.taskId;
        const currentStatusId = draggedTask.dataset.currentStatus;

        // Don't do anything if dropped in the same column
        if (newStatusId === currentStatusId) {
            return;
        }

        // Show loading state
        draggedTask.style.opacity = '0.7';
        draggedTask.style.pointerEvents = 'none';

        // Update task status via API
        updateTaskStatus(taskId, newStatusId)
            .then(response => {
                if (response.success) {
                    // Move the task to the new column
                    targetTasksContainer.appendChild(draggedTask);
                    draggedTask.dataset.currentStatus = newStatusId;

                    // Update column counters
                    updateColumnCounters();

                    // Show success feedback
                    showNotification('Task status updated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to update task status');
                }
            })
            .catch(error => {
                console.error('Error updating task status:', error);
                showNotification('Failed to update task status: ' + error.message, 'error');
            })
            .finally(() => {
                // Reset task appearance
                draggedTask.style.opacity = '1';
                draggedTask.style.pointerEvents = 'auto';
            });
    }

    function updateTaskStatus(taskId, newStatusId) {
        return fetch('/tasks/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({
                task_id: taskId,
                status_id: newStatusId
            })
        })
        .then(response => response.json());
    }

    function updateColumnCounters() {
        kanbanColumns.forEach(column => {
            const tasksContainer = column.querySelector('.kanban-tasks');
            const counter = column.querySelector('span');
            const taskCount = tasksContainer.children.length;

            if (counter) {
                counter.textContent = taskCount;
            }
        });
    }

    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg transition-all duration-300 transform translate-x-full ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
});
</script>

</body>
</html>
