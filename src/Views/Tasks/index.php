<?php
//file: Views/Tasks/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include helper functions
include BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Determine the context based on URL and viewType
$isMyTasksView = isset($userId); // This would be set in the controller when /:user_id is present
$currentUserId = $currentUser['profile']['id'] ?? null;
$viewingOwnTasks = $isMyTasksView && $userId == $currentUserId;
$isUnassignedView = isset($viewType) && $viewType === 'unassigned_tasks';
$isBacklogView = isset($viewType) && $viewType === 'backlog';

// Set view title based on context
if ($isUnassignedView) {
    $viewTitle = 'Unassigned Tasks';
} elseif ($isMyTasksView) {
    $viewTitle = $viewingOwnTasks ? 'My Tasks' : 'User Tasks';
} else {
    $viewTitle = 'All Tasks';
}

// Set up filter options based on context
$filterOptions = [
    'all' => 'All Statuses',
    'overdue' => 'Overdue',
    'today' => 'Due Today',
    'week' => 'Due This Week',
    'in-progress' => 'In Progress',
    'completed' => 'Completed',
];

// Add additional filters based on context
if ($isMyTasksView && !$viewingOwnTasks) {
    $filterOptions['assigned'] = 'Assigned';
    $filterOptions['unassigned'] = 'Unassigned';
} elseif (!$isMyTasksView && !$isUnassignedView && !$isBacklogView) {
    // For All Tasks view, add assignment filters
    $filterOptions['assigned'] = 'Assigned';
    $filterOptions['unassigned'] = 'Unassigned';
}

// Include task-specific helper functions if they exist
if (file_exists(BASE_PATH . '/../src/Views/Tasks/inc/helper_functions.php')) {
    include BASE_PATH . '/../src/Views/Tasks/inc/helper_functions.php';
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $viewTitle ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header and Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Page Header with Breadcrumb and New Task Button -->
        <?php include BASE_PATH . '/../src/Views/Tasks/inc/page_header.php'; ?>

        <?php if (isset($project) && !empty($project)): ?>
            <!-- Project Header with Navigation -->
            <?php include BASE_PATH . '/../src/Views/Tasks/inc/project_header.php'; ?>
        <?php endif; ?>

        <!-- Task Stats Summary -->
        <?php include BASE_PATH . '/../src/Views/Tasks/inc/stats.php'; ?>

        <!-- Page Header with Filters -->
        <?php include BASE_PATH . '/../src/Views/Tasks/inc/filters.php'; ?>

        <!-- Tasks Table -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <?php include BASE_PATH . '/../src/Views/Tasks/inc/table_header.php'; ?>
                    <?php include BASE_PATH . '/../src/Views/Tasks/inc/table.php'; ?>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php include BASE_PATH . '/../src/Views/Tasks/inc/pagination.php'; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- JavaScript for Task Filtering -->
    <script>
        <?php include BASE_PATH . '/../src/Views/Tasks/inc/task_filtering.js'; ?>
        
        // Additional script for active timer if needed
        <?php if (isset($activeTimer)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const timerDisplay = document.getElementById('timer-display');
            let seconds = <?= $activeTimer['duration'] ?? 0 ?>;
            
            setInterval(function() {
                seconds++;
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                
                timerDisplay.textContent = 
                    (hours < 10 ? '0' + hours : hours) + ':' +
                    (minutes < 10 ? '0' + minutes : minutes) + ':' +
                    (secs < 10 ? '0' + secs : secs);
            }, 1000);
        });
        <?php endif; ?>
    </script>
</body>
</html>