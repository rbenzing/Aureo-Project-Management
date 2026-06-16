<?php
//file: Views/Tasks/sprint-planning.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include helper functions
include BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard'],
    ['name' => 'Sprint Planning', 'url' => '/tasks/sprint-planning'],
];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprint Planning - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

            <!-- Main Content -->
            <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow overflow-y-auto">
                <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

                <?php if (isset($viewType) && $viewType === 'sprint_planning_selection'): ?>
                    <!-- Page Header with Breadcrumb and Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <!-- Breadcrumb Section -->
                        <div class="flex-1">
                            <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
                        </div>

                        <!-- Action Buttons Section -->
                        <div class="flex-shrink-0 flex space-x-3">
                            <a href="/projects/create" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                New Project
                            </a>
                        </div>
                    </div>

                    <!-- Page Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-2">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sprint Planning</h1>
                            <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Project Selection -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Select a Project</h2>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">
                                Choose a project to start sprint planning
                            </p>
                        </div>

                        <div class="p-6">
                            <?php if (!empty($projects)): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($projects as $project): ?>
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-6 hover:shadow-lg transition-shadow">
                                            <div class="flex items-start justify-between mb-3">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    <?= htmlspecialchars($project->name) ?>
                                                </h3>
                                                <?php
                                                // Use consistent project status styling and formatting
                                                $projectStatusMap = [
                                                    1 => ['label' => 'Ready', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'],
                                                    2 => ['label' => 'In Progress', 'class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'],
                                                    3 => ['label' => 'Completed', 'class' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
                                                    4 => ['label' => 'On Hold', 'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'],
                                                    6 => ['label' => 'Delayed', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'],
                                                    7 => ['label' => 'Cancelled', 'class' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'],
                                                ];
                                        $projectStatus = $projectStatusMap[$project->status_id ?? 1] ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'];
                                        ?>
                                                <span class="px-2 py-1 text-xs rounded-full font-medium <?= $projectStatus['class'] ?>">
                                                    <?= $projectStatus['label'] ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($project->description)): ?>
                                                <div class="text-gray-600 dark:text-gray-400 mb-4 text-sm">
                                                    <?= nl2br(htmlspecialchars(substr($project->description, 0, 100))) ?><?= strlen($project->description) > 100 ? '...' : '' ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex justify-end">
                                                <a href="/tasks/sprint-planning?project_id=<?= $project->id ?>"
                                                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                    </svg>
                                                    Plan Sprint
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Projects Found</h3>
                                    <p class="text-gray-600 dark:text-gray-400 mb-4">Create a project first to start sprint planning.</p>
                                    <a href="/projects/create" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Create Project
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <?php $hasSprints = !empty($sprints); ?>
                    <!-- Page Header with Breadcrumb and Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <!-- Breadcrumb Section -->
                        <div class="flex-1">
                            <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
                        </div>

                        <!-- Action Buttons Section -->
                        <div class="flex-shrink-0 flex space-x-3">
                            <a href="/tasks/backlog?project_id=<?= (int) $project->id ?>"
                               class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                View Backlog
                            </a>
                            <a href="/sprints/create/<?= (int) $project->id ?>"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Create Sprint
                            </a>
                        </div>
                    </div>

                    <!-- Page Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-2">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sprint Planning</h1>
                            <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors"
                                    data-type="page"
                                    data-title="<?= htmlspecialchars($project->name ?? 'Project') ?> - Sprint Planning"
                                    data-url="/tasks/sprint-planning?project_id=<?= (int) $project->id ?>"
                                    data-icon="📋"
                                    title="Add to favorites">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Project: <span class="font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($project->name ?? 'Unknown') ?></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Available Tasks (Backlog) -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Available Tasks</h2>
                                <?php if ($hasSprints): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Drag tasks to sprints to assign them</p>
                                <?php else: ?>
                                    <p class="text-sm text-amber-600 dark:text-amber-400">Create a sprint to start assigning tasks</p>
                                <?php endif; ?>
                            </div>
                            <div class="p-6" id="available-tasks-container">
                                <?php if (!empty($availableTasks)): ?>
                                    <div class="space-y-3 max-h-96 overflow-y-auto">
                                        <?php foreach ($availableTasks as $task): ?>
                                            <div class="task-card border border-gray-200 dark:border-gray-600 rounded-lg p-4 transition-colors <?= $hasSprints ? 'hover:bg-gray-50 dark:hover:bg-gray-700 cursor-move' : 'bg-gray-50 dark:bg-gray-800 cursor-not-allowed' ?>"
                                                 data-task-id="<?= $task->id ?>"
                                                 <?php if ($hasSprints): ?>draggable="true"<?php endif; ?>>
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <h4 class="font-medium text-gray-900 dark:text-white">
                                                            <?= htmlspecialchars($task->title) ?>
                                                        </h4>
                                                        <?php if (!empty($task->description)): ?>
                                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                                <?= htmlspecialchars(substr($task->description, 0, 80)) ?><?= strlen($task->description) > 80 ? '...' : '' ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <div class="flex items-center space-x-2 mt-2">
                                                            <span class="px-2 py-1 text-xs rounded-full 
                                                                <?php
                                                        switch ($task->priority) {
                                                            case 'high': echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

                                                                break;
                                                            case 'medium': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';

                                                                break;
                                                            case 'low': echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

                                                                break;
                                                            default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';

                                                                break;
                                                        }
                                            ?>">
                                                                <?= ucfirst($task->priority) ?>
                                                            </span>
                                                            <?php if (!empty($task->story_points)): ?>
                                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                    <?= $task->story_points ?> SP
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <a href="/tasks/view/<?= $task->id ?>" 
                                                           class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                                                            View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <p class="text-gray-600 dark:text-gray-400">No tasks ready for sprint planning</p>
                                        <a href="/tasks/create?project_id=<?= (int) $project->id ?>"
                                           class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                                            Create a task
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Sprints -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sprints</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Drop zones for task assignment</p>
                            </div>
                            <div class="p-6">
                                <?php if (!empty($sprints)): ?>
                                    <div class="space-y-4">
                                        <?php foreach ($sprints as $sprint): ?>
                                            <div class="sprint-drop-zone border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-lg p-4 min-h-[100px] transition-colors"
                                                 data-sprint-id="<?= $sprint->id ?>">
                                                <div class="flex justify-between items-start mb-3">
                                                    <div>
                                                        <h4 class="font-medium text-gray-900 dark:text-white">
                                                            <?= htmlspecialchars($sprint->name) ?>
                                                        </h4>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                            <?= date('M j', strtotime($sprint->start_date)) ?> - <?= date('M j, Y', strtotime($sprint->end_date)) ?>
                                                        </p>
                                                        <div class="flex items-center space-x-2 mt-2">
                                                            <?php
                                                            // Use consistent sprint status formatting
                                                            $sprintStatusMap = [
                                            1 => ['label' => 'Planning', 'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'],
                                            2 => ['label' => 'Active', 'class' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
                                            3 => ['label' => 'Delayed', 'class' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'],
                                            4 => ['label' => 'Completed', 'class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'],
                                            5 => ['label' => 'Cancelled', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'],
                                                            ];
                                            $sprintStatus = $sprintStatusMap[$sprint->status_id ?? 1] ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'];
                                            ?>
                                                            <span class="px-2 py-1 text-xs rounded-full font-medium <?= $sprintStatus['class'] ?>">
                                                                <?= $sprintStatus['label'] ?>
                                                            </span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                <?= $sprint->task_count ?? 0 ?> tasks
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <a href="/sprints/view/<?= $sprint->id ?>" 
                                                           class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                                                            View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-10 px-4 border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-lg">
                                        <svg class="w-10 h-10 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <h4 class="text-base font-medium text-gray-900 dark:text-white mb-1">No sprints yet</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Create a sprint before you can assign tasks.</p>
                                        <a href="/sprints/create/<?= (int) $project->id ?>"
                                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            Create Sprint
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg">
                        <div class="px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <a href="/tasks/create?project_id=<?= (int) $project->id ?>"
                                   class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">Add Task</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Create a new task for this project</p>
                                    </div>
                                </a>
                                <a href="/sprints/create/<?= (int) $project->id ?>"
                                   class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">Create Sprint</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Start a new sprint for this project</p>
                                    </div>
                                </a>
                                <a href="/tasks/backlog?project_id=<?= (int) $project->id ?>"
                                   class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">Manage Backlog</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Prioritize and organize backlog items</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>

            <!-- Footer -->
            <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>
        </div>
    </div>

    <script>
        // Sprint Planning Drag and Drop
        let draggedTask = null;

        document.addEventListener('DOMContentLoaded', function() {
            initializeSprintDragAndDrop();
        });

        function initializeSprintDragAndDrop() {
            // Make task cards draggable
            const taskCards = document.querySelectorAll('.task-card');
            taskCards.forEach(card => {
                card.addEventListener('dragstart', handleTaskDragStart);
                card.addEventListener('dragend', handleTaskDragEnd);
            });

            // Make sprint zones droppable
            const sprintZones = document.querySelectorAll('.sprint-drop-zone');
            sprintZones.forEach(zone => {
                zone.addEventListener('dragover', handleSprintDragOver);
                zone.addEventListener('drop', handleSprintDrop);
                zone.addEventListener('dragenter', handleSprintDragEnter);
                zone.addEventListener('dragleave', handleSprintDragLeave);
            });
        }

        function handleTaskDragStart(e) {
            draggedTask = this;
            this.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.outerHTML);
        }

        function handleTaskDragEnd(e) {
            this.style.opacity = '';
            draggedTask = null;
        }

        function handleSprintDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleSprintDragEnter(e) {
            this.classList.add('border-indigo-400', 'bg-indigo-50', 'dark:bg-indigo-900');
        }

        function handleSprintDragLeave(e) {
            // Only remove highlight if we're actually leaving the drop zone
            if (!this.contains(e.relatedTarget)) {
                this.classList.remove('border-indigo-400', 'bg-indigo-50', 'dark:bg-indigo-900');
            }
        }

        function handleSprintDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            // Remove highlight
            this.classList.remove('border-indigo-400', 'bg-indigo-50', 'dark:bg-indigo-900');

            if (draggedTask) {
                const taskId = draggedTask.getAttribute('data-task-id');
                const sprintId = this.getAttribute('data-sprint-id');

                // Add task to sprint via AJAX
                assignTaskToSprint(taskId, sprintId);

                // Move the task card to the sprint zone
                this.appendChild(draggedTask);

                // Update the task card styling to show it's assigned
                draggedTask.classList.add('bg-green-50', 'dark:bg-green-900', 'border-green-200', 'dark:border-green-700');
                draggedTask.setAttribute('draggable', 'false');
                draggedTask.style.cursor = 'default';
            }

            return false;
        }

        function assignTaskToSprint(taskId, sprintId) {
            fetch('/api/sprints/assign-task', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.csrfToken || ''
                },
                body: JSON.stringify({
                    task_id: taskId,
                    sprint_id: sprintId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Task assigned to sprint successfully');
                    // Optionally show a success message
                } else {
                    console.error('Failed to assign task to sprint:', data.message);
                    // Optionally revert the UI change
                    alert('Failed to assign task to sprint: ' + data.message);
                    location.reload(); // Reload to reset state
                }
            })
            .catch(error => {
                console.error('Error assigning task to sprint:', error);
                alert('Error assigning task to sprint');
                location.reload(); // Reload to reset state
            });
        }
    </script>
</body>
</html>
