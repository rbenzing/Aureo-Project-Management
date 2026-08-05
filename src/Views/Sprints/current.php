<?php
// file: Views/Sprints/current.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

$pageTitle = 'Current Sprints';
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

        <!-- Breadcrumb -->
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
                                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">Current Sprints</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center">
                <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Current Sprints</h1>
            </div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Overview of all active sprints you're involved in
            </p>
        </div>

        <?php if (empty($sprintDetails)): ?>
            <!-- No Active Sprints -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No active sprints</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        You don't have any active sprints at the moment. Get started by creating a new sprint or viewing existing ones.
                    </p>
                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="/sprints/planning" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Start Sprint Planning
                        </a>
                        <a href="/sprints" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            View All Sprints
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Today's Focus -->
            <?php if (!empty($todaysFocus)): ?>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            <svg class="inline w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Today's Focus
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($todaysFocus as $task): ?>
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                <a href="/tasks/view/<?= $task->id ?>" class="hover:text-blue-600">
                                                    <?= htmlspecialchars($task->title ?? $task->name ?? 'Untitled Task') ?>
                                                </a>
                                            </h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                <?= htmlspecialchars($task->project_name) ?> • <?= htmlspecialchars($task->sprint_name) ?>
                                            </p>
                                            <?php if ($task->due_date): ?>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    Due: <?= date('M j, Y', strtotime($task->due_date)) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            $priority = $task->priority ?? 'normal';
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
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Active Sprints -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php foreach ($sprintDetails as $detail): ?>
                    <?php
                    $sprint = $detail['sprint'];
                    $progress = $detail['progress'];
                    $userTasks = $detail['user_tasks'];
                    $allTasks = $detail['all_tasks'];
                    $project = $detail['project'];
                    ?>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <!-- Sprint Header -->
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <a href="/sprints/view/<?= $sprint->id ?>" class="hover:text-blue-600">
                                            <?= htmlspecialchars($sprint->name) ?>
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= htmlspecialchars($project->name) ?>
                                    </p>
                                    <?php if (!empty($sprint->sprint_goal)): ?>
                                        <p class="text-sm text-blue-600 dark:text-blue-400 mt-1 italic">
                                            "<?= htmlspecialchars($sprint->sprint_goal) ?>"
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Active
                                </span>
                            </div>
                        </div>

                        <!-- Sprint Progress -->
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progress</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    <?= $progress['completed_tasks'] ?>/<?= $progress['total_tasks'] ?> tasks
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $progress['completion_percentage'] ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?= $progress['completion_percentage'] ?>% complete
                            </div>
                        </div>

                        <!-- Sprint Dates -->
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Start Date:</span>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?= date('M j, Y', strtotime($sprint->start_date)) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">End Date:</span>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?= date('M j, Y', strtotime($sprint->end_date)) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Your Tasks -->
                        <?php if (!empty($userTasks)): ?>
                            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Your Tasks (<?= count($userTasks) ?>)</h4>
                                <div class="space-y-2">
                                    <?php foreach (array_slice($userTasks, 0, 3) as $task): ?>
                                        <div class="flex items-center justify-between">
                                            <a href="/tasks/view/<?= $task->id ?>" class="text-sm text-gray-700 dark:text-gray-300 hover:text-blue-600 truncate">
                                                <?= htmlspecialchars($task->title ?? $task->name ?? 'Untitled Task') ?>
                                            </a>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                <?php
                                                switch ($task->status_id) {
                                                    case 6: echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

                                                        break;
                                                    case 3: case 4: echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';

                                                        break;
                                                    default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                                }
                                        ?>">
                                                <?= htmlspecialchars($task->status_name) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($userTasks) > 3): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            +<?= count($userTasks) - 3 ?> more tasks
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                            <div class="flex space-x-3">
                                <a href="/sprints/view/<?= $sprint->id ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                                    View Details
                                </a>
                                <a href="/tasks?sprint_id=<?= $sprint->id ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                                    View Tasks
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

</body>
</html>
