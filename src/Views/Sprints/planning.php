<?php
// file: Views/Sprints/planning.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

$pageTitle = 'Sprint Planning';
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
                        <li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">Sprint Planning</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Sprint Planning</h1>
                </div>
                <div class="flex space-x-3">
                    <a href="/sprints/current" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                        Current Sprints
                    </a>
                    <a href="/sprints" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                        All Sprints
                    </a>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Plan and organize your sprints with capacity planning and backlog prioritization
            </p>
        </div>

        <!-- Project Selection -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Project Selection</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Choose a project to start sprint planning</p>
            </div>
            <div class="px-6 py-4">
                <form method="GET" action="/sprints/planning" class="flex items-center space-x-4">
                    <div class="flex-1">
                        <label for="project_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project</label>
                        <select name="project_id" id="project_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white" onchange="this.form.submit()">
                            <option value="">Select a project...</option>
                            <?php if (isset($userProjects)): ?>
                                <?php foreach ($userProjects as $project): ?>
                                    <option value="<?= $project->id ?>" <?= ($selectedProjectId == $project->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedProject): ?>
            <!-- Sprint Capacity Overview -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Sprint Capacity Planning</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Team capacity and commitment tracking for <?= htmlspecialchars($selectedProject->name) ?></p>
                </div>
                <div class="px-6 py-4">
                    <!-- Capacity Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                <?= $sprintCapacity['estimation_method'] === 'hours' ? $sprintCapacity['hours'] : $sprintCapacity['story_points'] ?>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Team Capacity (<?= ucfirst($sprintCapacity['estimation_method']) ?>)
                            </div>
                        </div>
                        <div class="text-center">
                            <div id="committed-capacity" class="text-2xl font-bold text-green-600 dark:text-green-400">
                                0
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Committed (<?= ucfirst($sprintCapacity['estimation_method']) ?>)
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                <?= count($productBacklog) ?>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Available Tasks
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                <?= count($activeSprints) ?>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Active Sprints
                            </div>
                        </div>
                    </div>

                    <!-- Capacity Progress Bar -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Capacity Utilization</span>
                            <span id="capacity-percentage" class="text-sm text-gray-500 dark:text-gray-400">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                            <div id="capacity-progress-bar" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <span>0</span>
                            <span id="capacity-warning" class="hidden text-yellow-600 dark:text-yellow-400">⚠️ Approaching capacity</span>
                            <span id="capacity-exceeded" class="hidden text-red-600 dark:text-red-400">⚠️ Capacity exceeded</span>
                            <span><?= $sprintCapacity['estimation_method'] === 'hours' ? $sprintCapacity['hours'] : $sprintCapacity['story_points'] ?></span>
                        </div>
                    </div>

                    <!-- Team Availability -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Team Availability</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Team Size:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?= $sprintCapacity['team_size'] ?? 5 ?> members</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Sprint Length:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?= $sprintSettings['default_sprint_length'] ?? 14 ?> days</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Working Days:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white"><?= $sprintSettings['working_days_per_week'] ?? 5 ?>/week</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Capacity Breakdown</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Available:</span>
                                    <span id="available-capacity" class="text-sm font-medium text-green-600 dark:text-green-400">
                                        <?= $sprintCapacity['estimation_method'] === 'hours' ? $sprintCapacity['hours'] : $sprintCapacity['story_points'] ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Committed:</span>
                                    <span id="committed-capacity-detail" class="text-sm font-medium text-blue-600 dark:text-blue-400">0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Remaining:</span>
                                    <span id="remaining-capacity" class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        <?= $sprintCapacity['estimation_method'] === 'hours' ? $sprintCapacity['hours'] : $sprintCapacity['story_points'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sprint Planning Board -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Product Backlog -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Product Backlog</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tasks available for sprint planning</p>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (empty($productBacklog)): ?>
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tasks available</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    All tasks are already assigned to sprints or there are no tasks in this project.
                                </p>
                                <div class="mt-6">
                                    <a href="/tasks/create?project_id=<?= $selectedProject->id ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Create Task
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach ($productBacklog as $task): ?>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow cursor-move" draggable="true" data-task-id="<?= $task->id ?>">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?= htmlspecialchars($task->title) ?>
                                                </h4>
                                                <?php if ($task->description): ?>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                        <?= htmlspecialchars(substr($task->description, 0, 100)) ?><?= strlen($task->description) > 100 ? '...' : '' ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="flex items-center mt-2 space-x-2">
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
                                                        switch ($task->priority) {
                                                            case 1: echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

                                                                break;
                                                            case 2: echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';

                                                                break;
                                                            case 3: echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

                                                                break;
                                                            default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                                        }
                                    ?>">
                                                        <?php
                                    switch ($task->priority) {
                                        case 1: echo 'High';

                                            break;
                                        case 2: echo 'Medium';

                                            break;
                                        case 3: echo 'Low';

                                            break;
                                        default: echo 'Normal';
                                    }
                                    ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Drag to add to sprint">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sprint Planning Area -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Sprint Backlog</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Drag tasks here to plan your sprint</p>
                    </div>
                    <div class="px-6 py-4">
                        <div id="sprint-backlog" class="min-h-96 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Sprint Backlog Empty</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Drag tasks from the product backlog to start planning your sprint.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Sprint Goal Setting -->
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Sprint Goal</h4>
                            <div class="space-y-3">
                                <div>
                                    <label for="sprint-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sprint Name</label>
                                    <input type="text" id="sprint-name" name="sprint-name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Enter sprint name...">
                                </div>
                                <div>
                                    <label for="sprint-goal" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sprint Goal</label>
                                    <textarea id="sprint-goal" name="sprint-goal" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="What is the main objective for this sprint? What value will it deliver?"></textarea>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A clear, concise statement of what the team aims to achieve during this sprint.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sprint Actions -->
                        <div class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <span id="sprint-capacity-used">0</span> / <span id="sprint-capacity-total"><?= $sprintCapacity['estimation_method'] === 'hours' ? $sprintCapacity['hours'] : $sprintCapacity['story_points'] ?></span>
                                <?= $sprintCapacity['estimation_method'] === 'hours' ? 'hours' : 'story points' ?>
                            </div>
                            <button type="button" id="create-sprint-btn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Create Sprint
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- No Project Selected -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Select a project</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Choose a project from the dropdown above to start sprint planning.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sprintBacklog = document.getElementById('sprint-backlog');
    const createSprintBtn = document.getElementById('create-sprint-btn');
    const capacityUsed = document.getElementById('sprint-capacity-used');
    const capacityTotal = document.getElementById('sprint-capacity-total');

    let sprintTasks = [];
    let totalCapacity = parseInt(capacityTotal.textContent);
    let estimationMethod = '<?= $sprintCapacity['estimation_method'] ?? 'hours' ?>';

    // Make product backlog items draggable
    const backlogItems = document.querySelectorAll('[data-task-id]');
    backlogItems.forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragend', handleDragEnd);
    });

    // Make sprint backlog a drop zone
    sprintBacklog.addEventListener('dragover', handleDragOver);
    sprintBacklog.addEventListener('drop', handleDrop);
    sprintBacklog.addEventListener('dragenter', handleDragEnter);
    sprintBacklog.addEventListener('dragleave', handleDragLeave);

    function handleDragStart(e) {
        e.dataTransfer.setData('text/plain', e.target.dataset.taskId);
        e.target.style.opacity = '0.5';
    }

    function handleDragEnd(e) {
        e.target.style.opacity = '1';
    }

    function handleDragOver(e) {
        e.preventDefault();
    }

    function handleDragEnter(e) {
        e.preventDefault();
        sprintBacklog.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900');
    }

    function handleDragLeave(e) {
        if (!sprintBacklog.contains(e.relatedTarget)) {
            sprintBacklog.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        sprintBacklog.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900');

        const taskId = e.dataTransfer.getData('text/plain');
        const taskElement = document.querySelector(`[data-task-id="${taskId}"]`);

        if (taskElement && !sprintTasks.find(t => t.id === taskId)) {
            addTaskToSprint(taskElement);
        }
    }

    function addTaskToSprint(taskElement) {
        const taskId = taskElement.dataset.taskId;
        const taskTitle = taskElement.querySelector('h4').textContent.trim();
        const taskDescription = taskElement.querySelector('p') ? taskElement.querySelector('p').textContent.trim() : '';

        // Extract capacity values
        let storyPoints = 0;
        let estimatedHours = 0;

        const spElement = taskElement.querySelector('.bg-blue-100, .bg-blue-900');
        if (spElement && spElement.textContent.includes('SP')) {
            storyPoints = parseInt(spElement.textContent.replace(' SP', ''));
        }

        const hoursElement = taskElement.querySelector('.bg-green-100, .bg-green-900');
        if (hoursElement && hoursElement.textContent.includes('h')) {
            estimatedHours = parseFloat(hoursElement.textContent.replace('h', ''));
        }

        const task = {
            id: taskId,
            title: taskTitle,
            description: taskDescription,
            storyPoints: storyPoints,
            estimatedHours: estimatedHours
        };

        // Check capacity
        const taskCapacity = estimationMethod === 'story_points' ? storyPoints : estimatedHours;
        const currentUsed = getCurrentCapacityUsed();

        if (currentUsed + taskCapacity > totalCapacity) {
            alert(`Adding this task would exceed sprint capacity (${currentUsed + taskCapacity}/${totalCapacity} ${estimationMethod === 'story_points' ? 'story points' : 'hours'})`);
            return;
        }

        sprintTasks.push(task);
        renderSprintBacklog();
        updateCapacityDisplay();

        // Hide task from product backlog
        taskElement.style.display = 'none';
    }

    function removeTaskFromSprint(taskId) {
        sprintTasks = sprintTasks.filter(t => t.id !== taskId);
        renderSprintBacklog();
        updateCapacityDisplay();

        // Show task back in product backlog
        const taskElement = document.querySelector(`[data-task-id="${taskId}"]`);
        if (taskElement) {
            taskElement.style.display = 'block';
        }
    }

    function renderSprintBacklog() {
        if (sprintTasks.length === 0) {
            sprintBacklog.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Sprint Backlog Empty</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Drag tasks from the product backlog to start planning your sprint.
                    </p>
                </div>
            `;
            return;
        }

        const tasksHtml = sprintTasks.map(task => `
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-3 bg-blue-50 dark:bg-blue-900">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                            ${task.title}
                        </h4>
                        ${task.description ? `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${task.description}</p>` : ''}
                        <div class="flex items-center mt-2 space-x-2">
                            ${task.storyPoints ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">${task.storyPoints} SP</span>` : ''}
                            ${task.estimatedHours ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">${task.estimatedHours}h</span>` : ''}
                        </div>
                    </div>
                    <button type="button" onclick="removeTaskFromSprint('${task.id}')" class="text-red-400 hover:text-red-600 dark:hover:text-red-300" title="Remove from sprint">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');

        sprintBacklog.innerHTML = tasksHtml;
    }

    function getCurrentCapacityUsed() {
        return sprintTasks.reduce((total, task) => {
            return total + (estimationMethod === 'story_points' ? task.storyPoints : task.estimatedHours);
        }, 0);
    }

    function updateCapacityDisplay() {
        const used = getCurrentCapacityUsed();
        const percentage = (used / totalCapacity) * 100;
        const remaining = Math.max(0, totalCapacity - used);

        // Update basic capacity display
        capacityUsed.textContent = used;

        // Update enhanced capacity elements
        const committedCapacity = document.getElementById('committed-capacity');
        const committedCapacityDetail = document.getElementById('committed-capacity-detail');
        const availableCapacity = document.getElementById('available-capacity');
        const remainingCapacity = document.getElementById('remaining-capacity');
        const capacityPercentage = document.getElementById('capacity-percentage');
        const capacityProgressBar = document.getElementById('capacity-progress-bar');
        const capacityWarning = document.getElementById('capacity-warning');
        const capacityExceeded = document.getElementById('capacity-exceeded');

        if (committedCapacity) committedCapacity.textContent = used;
        if (committedCapacityDetail) committedCapacityDetail.textContent = used;
        if (remainingCapacity) remainingCapacity.textContent = remaining;
        if (capacityPercentage) capacityPercentage.textContent = Math.round(percentage) + '%';

        // Update progress bar
        if (capacityProgressBar) {
            capacityProgressBar.style.width = Math.min(100, percentage) + '%';

            // Update progress bar color based on usage
            if (percentage > 100) {
                capacityProgressBar.className = 'bg-red-600 h-3 rounded-full transition-all duration-300';
            } else if (percentage > 80) {
                capacityProgressBar.className = 'bg-yellow-500 h-3 rounded-full transition-all duration-300';
            } else {
                capacityProgressBar.className = 'bg-blue-600 h-3 rounded-full transition-all duration-300';
            }
        }

        // Show/hide warnings
        if (capacityWarning && capacityExceeded) {
            capacityWarning.classList.add('hidden');
            capacityExceeded.classList.add('hidden');

            if (percentage > 100) {
                capacityExceeded.classList.remove('hidden');
            } else if (percentage > 80) {
                capacityWarning.classList.remove('hidden');
            }
        }

        // Update button state
        if (sprintTasks.length > 0) {
            createSprintBtn.disabled = false;
            createSprintBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            createSprintBtn.disabled = true;
            createSprintBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }

        // Update capacity color based on usage
        if (percentage > 100) {
            capacityUsed.className = 'text-red-600 font-bold';
        } else if (percentage > 80) {
            capacityUsed.className = 'text-yellow-600 font-bold';
        } else {
            capacityUsed.className = 'text-green-600 font-bold';
        }

        // Update committed capacity colors
        if (committedCapacity) {
            if (percentage > 100) {
                committedCapacity.className = 'text-2xl font-bold text-red-600 dark:text-red-400';
            } else if (percentage > 80) {
                committedCapacity.className = 'text-2xl font-bold text-yellow-600 dark:text-yellow-400';
            } else {
                committedCapacity.className = 'text-2xl font-bold text-green-600 dark:text-green-400';
            }
        }
    }

    // Make removeTaskFromSprint globally available
    window.removeTaskFromSprint = removeTaskFromSprint;

    // Handle create sprint button
    createSprintBtn.addEventListener('click', function() {
        if (sprintTasks.length === 0) {
            alert('Please add at least one task to the sprint before creating it.');
            return;
        }

        const sprintNameInput = document.getElementById('sprint-name');
        const sprintGoalInput = document.getElementById('sprint-goal');

        const sprintName = sprintNameInput.value.trim();
        const sprintGoal = sprintGoalInput.value.trim();

        if (!sprintName) {
            alert('Please enter a sprint name.');
            sprintNameInput.focus();
            return;
        }

        // Validate sprint goal (optional but recommended)
        if (!sprintGoal) {
            if (!confirm('No sprint goal has been set. A sprint goal helps the team stay focused. Do you want to continue without a goal?')) {
                sprintGoalInput.focus();
                return;
            }
        }

        // Show loading state
        createSprintBtn.disabled = true;
        createSprintBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Creating Sprint...
        `;

        // Create sprint with selected tasks
        const formData = new FormData();
        formData.append('name', sprintName);
        formData.append('goal', sprintGoal);
        formData.append('project_id', '<?= $selectedProject->id ?? '' ?>');
        formData.append('task_ids', JSON.stringify(sprintTasks.map(t => t.id)));

        fetch('/sprints/create-from-planning', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                createSprintBtn.innerHTML = `
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Sprint Created!
                `;
                createSprintBtn.className = 'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600';

                // Redirect after a short delay
                setTimeout(() => {
                    window.location.href = '/sprints/view/' + data.sprint_id;
                }, 1500);
            } else {
                // Reset button state
                createSprintBtn.disabled = false;
                createSprintBtn.innerHTML = `
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Create Sprint
                `;
                createSprintBtn.className = 'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';

                alert('Error creating sprint: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);

            // Reset button state
            createSprintBtn.disabled = false;
            createSprintBtn.innerHTML = `
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Create Sprint
            `;
            createSprintBtn.className = 'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';

            alert('Error creating sprint. Please try again.');
        });
    });
});
</script>

</body>
</html>
