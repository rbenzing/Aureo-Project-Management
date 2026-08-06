<?php
// file: Views/Sprints/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include helper functions
include_once BASE_PATH . '/../src/Views/Sprints/inc/helpers.php';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprint Details - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Page Header with Breadcrumb and Navigation -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <!-- Breadcrumb Section -->
            <div class="flex-1">
                <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
            </div>

            <!-- Navigation Section -->
            <div class="flex-shrink-0 flex items-center space-x-3">
                <a href="/sprints/project/<?= $project->id ?? 0 ?>" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                    All Sprints
                </a>

                <!-- Sprint Switcher -->
                <div class="relative min-w-[180px]">
                    <select id="sprintSwitcher" class="h-10 appearance-none w-full px-4 py-2 dark:text-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md pl-10 pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Switch Sprint...</option>
                        <!-- Sprint options will be loaded via JavaScript -->
                    </select>
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sprint Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center mb-4 lg:mb-0">
                    <div class="h-12 w-12 rounded-lg bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center mr-4">
                        <svg class="h-7 w-7 text-indigo-500 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <?= htmlspecialchars($sprint->name ?? 'Sprint') ?>
                            </h1>
                            <?php
                            $statusInfo = getSprintStatusInfo($sprint->status_id ?? 1);
echo '<span class="ml-3">' . renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm') . '</span>';
?>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Project: <a href="/projects/view/<?= $project->id ?? 0 ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                <?= htmlspecialchars($project->name ?? 'Project') ?>
                            </a>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if (isset($sprint->status_id) && $sprint->status_id == 1): // Planning
                        ?>
                        <form action="/sprints/start/<?= $sprint->id ?? 0 ?>" method="POST" class="inline" onsubmit="return confirm('Start this sprint? This will set it as the active sprint for the project.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                Start Sprint
                            </button>
                        </form>
                    <?php elseif (isset($sprint->status_id) && $sprint->status_id == 2): // Active
                        ?>
                        <form action="/sprints/complete/<?= $sprint->id ?? 0 ?>" method="POST" class="inline" onsubmit="return confirm('Complete this sprint? This will update the sprint status to completed.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Complete Sprint
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="/sprints/edit/<?= $sprint->id ?? 0 ?>" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Edit Sprint
                    </a>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="more-dropdown-toggle px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                            </svg>
                            More
                        </button>
                        <div class="more-dropdown-menu absolute hidden right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
                            <div class="py-1">
                                <?php if (isset($sprint->status_id) && $sprint->status_id !== 3): // Not delayed
                                    ?>
                                    <form action="/sprints/delay/<?= $sprint->id ?? 0 ?>" method="POST" class="block">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                        <button
                                            type="submit"
                                            class="w-full text-left px-4 py-2 text-sm text-yellow-600 dark:text-yellow-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            onclick="return confirm('Mark this sprint as delayed?');">
                                            Mark as Delayed
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (isset($sprint->status_id) && $sprint->status_id !== 5): // Not cancelled
                                    ?>
                                    <form action="/sprints/cancel/<?= $sprint->id ?? 0 ?>" method="POST" class="block">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                        <button
                                            type="submit"
                                            class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            onclick="return confirm('Cancel this sprint? This cannot be undone.');">
                                            Cancel Sprint
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form action="/sprints/delete/<?= $sprint->id ?? 0 ?>" method="POST" class="block">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                    <button
                                        type="submit"
                                        class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        onclick="return confirm('Delete this sprint? This cannot be undone.');">
                                        Delete Sprint
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sprint Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">
            <!-- Sprint Info Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Sprint Details</h2>

                <div class="space-y-4">
                    <!-- Sprint Goal -->
                    <?php if (!empty($sprint->sprint_goal)): ?>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Sprint Goal</h3>
                        <div class="mt-1 p-3 bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-400 rounded-r-md">
                            <p class="text-blue-800 dark:text-blue-200 font-medium">
                                <?= nl2br(htmlspecialchars($sprint->sprint_goal)) ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Description -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h3>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">
                            <?= !empty($sprint->description) ? nl2br(htmlspecialchars($sprint->description)) : 'No description provided' ?>
                        </p>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Sprint Duration</h3>
                        <div class="mt-1 flex items-center">
                            <svg class="h-5 w-5 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <?php if (isset($sprint->start_date) && isset($sprint->end_date)): ?>
                                <span class="text-gray-900 dark:text-gray-100">
                                    <?= date('M j, Y', strtotime($sprint->start_date)) ?> - <?= date('M j, Y', strtotime($sprint->end_date)) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-500 dark:text-gray-400">Not specified</span>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Calculate days remaining if sprint is active
                        if (isset($sprint->status_id) && $sprint->status_id == 2 && isset($sprint->end_date)):
                            $endDate = new DateTime($sprint->end_date);
                            $today = new DateTime();
                            $daysRemaining = $today <= $endDate ? $today->diff($endDate)->days : 0;
                            $isOverdue = $today > $endDate;
                            ?>
                            <div class="mt-1 text-sm <?= $isOverdue ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' ?>">
                                <?= $isOverdue ? 'Overdue by ' . abs($daysRemaining) . ' days' : $daysRemaining . ' days remaining' ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sprint Progress -->
                    <?php
                    // Calculate sprint progress percentages
                    $daysElapsed = 0;
$totalDays = 0;
$timeProgress = 0;

if (isset($sprint->start_date) && isset($sprint->end_date)) {
    $startDate = new DateTime($sprint->start_date);
    $endDate = new DateTime($sprint->end_date);
    $today = new DateTime();

    $totalDays = $startDate->diff($endDate)->days + 1; // +1 to include start day

    if ($today < $startDate) {
        // Sprint hasn't started yet
        $daysElapsed = 0;
    } elseif ($today > $endDate) {
        // Sprint has ended
        $daysElapsed = $totalDays;
    } else {
        // Sprint is in progress
        $daysElapsed = $startDate->diff($today)->days + 1; // +1 to include today
    }

    $timeProgress = $totalDays > 0 ? min(100, round(($daysElapsed / $totalDays) * 100)) : 0;
}

// Calculate task completion percentages
$taskCompletion = 0;
$totalTasks = count($tasks ?? []);
$completedTasks = 0;

if ($totalTasks > 0) {
    foreach ($tasks ?? [] as $task) {
        if (isset($task->status_id) && $task->status_id == 6) { // 6 = Completed status
            $completedTasks++;
        }
    }
    $taskCompletion = round(($completedTasks / $totalTasks) * 100);
}
?>

                    <!-- Time Progress -->
                    <div>
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Time Progress</h3>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= $timeProgress ?>%</span>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $timeProgress ?>%"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <?= $daysElapsed ?> of <?= $totalDays ?> days
                        </div>
                    </div>

                    <!-- Task Progress -->
                    <div>
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Task Completion</h3>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= $taskCompletion ?>%</span>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div class="bg-green-600 h-2.5 rounded-full" style="width: <?= $taskCompletion ?>%"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <?= $completedTasks ?> of <?= $totalTasks ?> tasks completed
                        </div>
                    </div>

                    <!-- Status Indicator -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
                        <?php
    // Calculate sprint health
    $statusIndicator = '';
$statusMessage = '';

if (isset($sprint->status_id)) {
    switch ($sprint->status_id) {
        case 1: // Planning
            $statusIndicator = '<span class="text-blue-600 dark:text-blue-400">Planning</span>';
            $statusMessage = 'Sprint is in planning phase.';

            break;
        case 2: // Active
            if ($timeProgress > $taskCompletion + 20) {
                $statusIndicator = '<span class="text-red-600 dark:text-red-400">Behind Schedule</span>';
                $statusMessage = 'Sprint tasks are falling behind schedule.';
            } elseif ($taskCompletion >= $timeProgress) {
                $statusIndicator = '<span class="text-green-600 dark:text-green-400">On Track</span>';
                $statusMessage = 'Sprint is on track or ahead of schedule.';
            } else {
                $statusIndicator = '<span class="text-yellow-600 dark:text-yellow-400">Slightly Behind</span>';
                $statusMessage = 'Sprint slightly behind, but recoverable.';
            }

            break;
        case 3: // Delayed
            $statusIndicator = '<span class="text-yellow-600 dark:text-yellow-400">Delayed</span>';
            $statusMessage = 'Sprint has been marked as delayed.';

            break;
        case 4: // Completed
            $statusIndicator = '<span class="text-green-600 dark:text-green-400">Completed</span>';
            $statusMessage = 'Sprint has been completed.';

            break;
        case 5: // Cancelled
            $statusIndicator = '<span class="text-red-600 dark:text-red-400">Cancelled</span>';
            $statusMessage = 'Sprint has been cancelled.';

            break;
        default:
            $statusIndicator = '<span class="text-gray-600 dark:text-gray-400">Unknown</span>';
            $statusMessage = 'Unknown sprint status.';
    }
}

echo '<div class="mt-1 text-gray-900 dark:text-gray-100">' . $statusIndicator . '</div>';
echo '<div class="mt-1 text-sm text-gray-500 dark:text-gray-400">' . $statusMessage . '</div>';
?>
                    </div>
                </div>
            </div>

            <!-- Sprint Relationships (if milestone-based) -->
            <?php if (!empty($sprint->relationships) && ($sprint->relationships['type'] !== 'project' || !empty($sprint->relationships['milestones']) || !empty($sprint->relationships['epics']))): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Sprint Relationships</h2>

                <div class="space-y-4">
                    <!-- Sprint Type -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Sprint Type</h3>
                        <div class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php
        switch ($sprint->relationships['type']) {
            case 'epic':
                echo 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';

                break;
            case 'milestone':
                echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';

                break;
            default:
                echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
        }
                ?>">
                                <?= ucfirst($sprint->relationships['type']) ?>-based Sprint
                            </span>
                        </div>
                    </div>

                    <!-- Associated Epics -->
                    <?php if (!empty($sprint->relationships['epics'])): ?>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Associated Epics</h3>
                        <div class="mt-2 space-y-2">
                            <?php foreach ($sprint->relationships['epics'] as $epic): ?>
                                <div class="flex items-center p-2 bg-purple-50 dark:bg-purple-900 rounded-md">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-200">
                                            EPIC
                                        </span>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <a href="/milestones/view/<?= $epic->id ?>" class="text-sm font-medium text-purple-900 dark:text-purple-100 hover:underline">
                                            <?= htmlspecialchars($epic->title) ?>
                                        </a>
                                        <?php if (!empty($epic->due_date)): ?>
                                            <div class="text-xs text-purple-700 dark:text-purple-300">
                                                Due: <?= date('M j, Y', strtotime($epic->due_date)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Associated Milestones -->
                    <?php if (!empty($sprint->relationships['milestones'])): ?>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Associated Milestones</h3>
                        <div class="mt-2 space-y-2">
                            <?php foreach ($sprint->relationships['milestones'] as $milestone): ?>
                                <div class="flex items-center p-2 bg-blue-50 dark:bg-blue-900 rounded-md">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                            MILESTONE
                                        </span>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <a href="/milestones/view/<?= $milestone->id ?>" class="text-sm font-medium text-blue-900 dark:text-blue-100 hover:underline">
                                            <?= htmlspecialchars($milestone->title) ?>
                                        </a>
                                        <?php if (!empty($milestone->due_date)): ?>
                                            <div class="text-xs text-blue-700 dark:text-blue-300">
                                                Due: <?= date('M j, Y', strtotime($milestone->due_date)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Project Information -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Project</h3>
                        <div class="mt-1">
                            <a href="/projects/view/<?= $sprint->relationships['project']->id ?? $project->id ?>" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                <?= htmlspecialchars($sprint->relationships['project']->name ?? $project->name) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sprint Burndown Chart (conditional positioning) -->
            <?php if (!empty($sprint->relationships) && ($sprint->relationships['type'] !== 'project' || !empty($sprint->relationships['milestones']) || !empty($sprint->relationships['epics']))): ?>
                <!-- Burndown chart spans full width when relationships are shown -->
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <?php else: ?>
                <!-- Burndown chart stays in the grid when no relationships -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <?php endif; ?>
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Burndown Chart</h2>

                <?php if (!empty($tasks)): ?>
                    <!-- Chart Container -->
                    <div class="h-64" id="burndown-chart"></div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-64 text-gray-400 dark:text-gray-500 text-center">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <p>No tasks available for burndown chart.</p>
                        <p class="text-sm mt-2">Add tasks to this sprint to see the burndown chart.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($sprint->relationships) && ($sprint->relationships['type'] !== 'project' || !empty($sprint->relationships['milestones']) || !empty($sprint->relationships['epics']))): ?>
                <!-- Close the standalone burndown chart div when relationships exist -->
        </div>
            <?php else: ?>
                <!-- Close the grid layout when no relationships -->
        </div>
            <?php endif; ?>

        <!-- Sprint Tasks with Hierarchy -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Sprint Tasks, Epics & Milestones</h2>

                <?php if (isset($sprint->status_id) && in_array($sprint->status_id, [1, 2])): // Planning or Active
                    ?>
                    <a href="/tasks/create?sprint_id=<?= $sprint->id ?? 0 ?>&project_id=<?= $project->id ?? 0 ?>" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Add Task
                    </a>
                <?php endif; ?>
            </div>

            <?php include BASE_PATH . '/../src/Views/Sprints/inc/table_tasks_hierarchical.php'; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- Include Chart.js for burndown chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load sprint switcher options
            loadSprintSwitcher();

            // Handle sprint switcher change
            const sprintSwitcher = document.getElementById('sprintSwitcher');
            if (sprintSwitcher) {
                sprintSwitcher.addEventListener('change', function() {
                    if (this.value) {
                        window.location.href = '/sprints/view/' + this.value;
                    }
                });
            }

            // Load sprint switcher options
            function loadSprintSwitcher() {
                const projectId = <?= isset($project) && $project ? $project->id : 0 ?>;
                const currentSprintId = <?= isset($sprint) && $sprint ? $sprint->id : 0 ?>;

                if (!projectId) {
                    console.warn('No project ID available for sprint switcher');
                    return;
                }

                fetch(`/api/sprints/project/${projectId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.sprints) {
                            populateSprintSwitcher(data.sprints, currentSprintId);
                        } else {
                            console.error('Failed to load sprints:', data.message || 'No sprints data');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading sprints:', error);
                    });
            }

            function populateSprintSwitcher(sprints, currentSprintId) {
                const sprintSwitcher = document.getElementById('sprintSwitcher');
                if (!sprintSwitcher) {
                    console.error('Sprint switcher element not found');
                    return;
                }

                // Clear existing options except the first one
                while (sprintSwitcher.children.length > 1) {
                    sprintSwitcher.removeChild(sprintSwitcher.lastChild);
                }

                if (!sprints || sprints.length === 0) {
                    console.warn('No sprints available for switcher');
                    return;
                }

                // Group sprints by status
                const sprintGroups = {
                    active: [],
                    planning: [],
                    completed: [],
                    other: []
                };

                sprints.forEach(sprint => {
                    if (sprint.id == currentSprintId) return; // Skip current sprint

                    switch (sprint.status_id) {
                        case 2: // Active
                            sprintGroups.active.push(sprint);
                            break;
                        case 1: // Planning
                            sprintGroups.planning.push(sprint);
                            break;
                        case 4: // Completed
                            sprintGroups.completed.push(sprint);
                            break;
                        default:
                            sprintGroups.other.push(sprint);
                    }
                });

                // Add grouped options
                if (sprintGroups.active.length > 0) {
                    const activeGroup = document.createElement('optgroup');
                    activeGroup.label = 'Active Sprints';
                    sprintGroups.active.forEach(sprint => {
                        const option = document.createElement('option');
                        option.value = sprint.id;
                        option.textContent = sprint.name;
                        activeGroup.appendChild(option);
                    });
                    sprintSwitcher.appendChild(activeGroup);
                }

                if (sprintGroups.planning.length > 0) {
                    const planningGroup = document.createElement('optgroup');
                    planningGroup.label = 'Planning Sprints';
                    sprintGroups.planning.forEach(sprint => {
                        const option = document.createElement('option');
                        option.value = sprint.id;
                        option.textContent = sprint.name;
                        planningGroup.appendChild(option);
                    });
                    sprintSwitcher.appendChild(planningGroup);
                }

                if (sprintGroups.completed.length > 0) {
                    const completedGroup = document.createElement('optgroup');
                    completedGroup.label = 'Completed Sprints';
                    sprintGroups.completed.forEach(sprint => {
                        const option = document.createElement('option');
                        option.value = sprint.id;
                        option.textContent = sprint.name;
                        completedGroup.appendChild(option);
                    });
                    sprintSwitcher.appendChild(completedGroup);
                }

                if (sprintGroups.other.length > 0) {
                    const otherGroup = document.createElement('optgroup');
                    otherGroup.label = 'Other Sprints';
                    sprintGroups.other.forEach(sprint => {
                        const option = document.createElement('option');
                        option.value = sprint.id;
                        option.textContent = sprint.name;
                        otherGroup.appendChild(option);
                    });
                    sprintSwitcher.appendChild(otherGroup);
                }
            }

        // Dropdown toggle functionality
        function setupDropdown(toggleClass, menuClass) {
                const toggles = document.querySelectorAll('.' + toggleClass);
                const menus = document.querySelectorAll('.' + menuClass);
                
                toggles.forEach((toggle, index) => {
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Close all other dropdowns
                        menus.forEach((menu, menuIndex) => {
                            if (menuIndex !== index) {
                                menu.classList.add('hidden');
                            }
                        });
                        
                        // Toggle current dropdown
                        menus[index].classList.toggle('hidden');
                    });
                });
            }
            
            // Setup each dropdown type
            setupDropdown('more-dropdown-toggle', 'more-dropdown-menu');

            // Find the burndown chart container
            const burndownContainer = document.getElementById('burndown-chart');
            if (!burndownContainer) return;

            // Check if we have sprint data available
            const sprintData = window.sprintData || {};
            const tasksData = window.tasksData || [];

            // Verify we have the required data to build the chart
            if (!sprintData.start_date || !sprintData.end_date || !tasksData.length) {
                renderEmptyChartMessage(burndownContainer);
                return;
            }

            try {
                // Parse and validate dates
                const startDate = new Date(sprintData.start_date);
                const endDate = new Date(sprintData.end_date);
                const today = new Date();

                // Validate date values
                if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                    throw new Error('Invalid sprint date format');
                }

                // Calculate the total number of tasks
                const totalTasks = parseInt(sprintData.total_tasks || tasksData.length);

                // Create chart data
                const chartData = prepareChartData(startDate, endDate, today, totalTasks, tasksData);

                // Render the burndown chart
                renderBurndownChart(burndownContainer, chartData);
            } catch (error) {
                console.error('Error creating burndown chart:', error);
                renderEmptyChartMessage(burndownContainer, 'Error creating chart: ' + error.message);
            }
        });

        /**
         * Prepare all data required for the burndown chart
         * 
         * @param {Date} startDate - Sprint start date
         * @param {Date} endDate - Sprint end date
         * @param {Date} today - Current date
         * @param {Number} totalTasks - Total number of tasks in sprint
         * @param {Array} tasksData - Array of task objects
         * @return {Object} Chart data object containing labels, ideal and actual burndown
         */
        function prepareChartData(startDate, endDate, today, totalTasks, tasksData) {
            // Generate date range and ideal burndown line
            const {
                dateLabels,
                idealBurndown
            } = generateIdealBurndown(startDate, endDate, totalTasks);

            // Generate actual burndown based on completed tasks
            const actualBurndown = generateActualBurndown(startDate, endDate, today, totalTasks, tasksData, dateLabels.length);

            return {
                labels: dateLabels,
                idealBurndown: idealBurndown,
                actualBurndown: actualBurndown
            };
        }

        /**
         * Generate date labels and ideal burndown line
         * 
         * @param {Date} startDate - Sprint start date
         * @param {Date} endDate - Sprint end date
         * @param {Number} totalTasks - Total number of tasks
         * @return {Object} Object containing date labels and ideal burndown values
         */
        function generateIdealBurndown(startDate, endDate, totalTasks) {
            const dateLabels = [];
            const idealBurndown = [];

            // Calculate total sprint duration and task decrement per day
            const totalDays = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
            const decrementPerDay = totalTasks / totalDays;

            let currentDate = new Date(startDate);
            let tasksRemaining = totalTasks;

            // Generate data points for each day in the sprint
            while (currentDate <= endDate) {
                // Format date as "Mon 01"
                dateLabels.push(formatDate(currentDate));

                // Add current ideal remaining tasks
                idealBurndown.push(Math.max(0, tasksRemaining));

                // Decrease remaining tasks according to ideal rate
                tasksRemaining = Math.max(0, tasksRemaining - decrementPerDay);

                // Move to next day
                currentDate.setDate(currentDate.getDate() + 1);
            }

            return {
                dateLabels,
                idealBurndown
            };
        }

        /**
         * Generate actual burndown line based on completed tasks
         * 
         * @param {Date} startDate - Sprint start date
         * @param {Date} endDate - Sprint end date
         * @param {Date} today - Current date
         * @param {Number} totalTasks - Total number of tasks
         * @param {Array} tasksData - Array of task objects
         * @param {Number} numDataPoints - Number of data points in the chart
         * @return {Array} Actual burndown data array
         */
        function generateActualBurndown(startDate, endDate, today, totalTasks, tasksData, numDataPoints) {
            // Initialize with total tasks remaining
            const actualBurndown = Array(numDataPoints).fill(totalTasks);

            // If sprint hasn't started yet, return the initial value
            if (today < startDate) {
                return actualBurndown;
            }

            // Extract completed tasks with valid completion dates
            const completedTasks = tasksData
                .filter(task => task.status_id == 6 && task.complete_date)
                .map(task => ({
                    id: task.id,
                    date: new Date(task.complete_date)
                }))
                .filter(task => !isNaN(task.date.getTime()))
                .sort((a, b) => a.date - b.date);

            // Update actual burndown based on completed tasks
            let remainingTasks = totalTasks;
            let taskIndex = 0;

            for (let i = 0; i < numDataPoints; i++) {
                const currentDate = new Date(startDate);
                currentDate.setDate(startDate.getDate() + i);

                // Count tasks completed by this date
                while (taskIndex < completedTasks.length && completedTasks[taskIndex].date <= currentDate) {
                    remainingTasks--;
                    taskIndex++;
                }

                actualBurndown[i] = Math.max(0, remainingTasks);

                // Don't project beyond today
                if (currentDate > today) {
                    break;
                }
            }

            return actualBurndown;
        }

        /**
         * Format a date as "Mon 01"
         * 
         * @param {Date} date - Date to format
         * @return {String} Formatted date string
         */
        function formatDate(date) {
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        }

        /**
         * Render the burndown chart using Chart.js
         * 
         * @param {HTMLElement} container - Chart container element
         * @param {Object} chartData - Prepared chart data
         */
        function renderBurndownChart(container, chartData) {
            const ctx = container.getContext('2d');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                            label: 'Ideal Burndown',
                            data: chartData.idealBurndown,
                            borderColor: 'rgba(156, 163, 175, 0.7)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            pointRadius: 0
                        },
                        {
                            label: 'Actual Burndown',
                            data: chartData.actualBurndown,
                            borderColor: 'rgba(79, 70, 229, 1)',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Tasks Remaining'
                            },
                            ticks: {
                                precision: 0,
                                stepSize: 1
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Sprint Timeline'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        /**
         * Render a message when there's not enough data for the chart
         * 
         * @param {HTMLElement} container - Chart container element
         * @param {String} message - Optional custom message to display
         */
        function renderEmptyChartMessage(container, message) {
            const defaultMessage = 'Insufficient data for burndown chart.';
            const subMessage = 'Make sure sprint start date, end date, and tasks are set.';

            container.innerHTML = `<div class="flex flex-col items-center justify-center h-64 text-gray-400 dark:text-gray-500 text-center">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <p>${message || defaultMessage}</p>
            <p class="text-sm mt-2">${message ? '' : subMessage}</p>
        </div>
    `;
        }
    </script>
</body>
</html>