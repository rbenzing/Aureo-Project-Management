<?php
// file: Views/Dashboard/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Models\User;
use App\Utils\Breadcrumb;

// Helper function to check permissions - moved to view_helpers.php
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= Config::get('company_name', 'Aureo') ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .timer-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Debug Information -->
        <?php if (isset($_GET['debug'])): ?>
            <div class="mb-6 p-4 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 rounded-lg">
                <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-3">🐛 Dashboard Debug Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                    <!-- User Info -->
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">User Info</h4>
                        <div class="text-xs space-y-1">
                            <div>ID: <?= $currentUser['profile']['id'] ?? 'Not set' ?></div>
                            <div>Name: <?= htmlspecialchars(($currentUser['profile']['first_name'] ?? '') . ' ' . ($currentUser['profile']['last_name'] ?? '')) ?></div>
                            <div>Permissions: <?= count($currentUser['permissions'] ?? []) ?> total</div>
                        </div>
                    </div>

                    <!-- Projects Debug -->
                    <?php if (hasUserPermission('view_projects')): ?>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Projects</h4>
                        <div class="text-xs space-y-1">
                            <div>Recent: <?= count($dashboardData['recent_projects'] ?? []) ?></div>
                            <div>Summary Total: <?= $dashboardData['project_summary']['total'] ?? 0 ?></div>
                            <div>In Progress: <?= $dashboardData['project_summary']['in_progress'] ?? 0 ?></div>
                            <div>Completed: <?= $dashboardData['project_summary']['completed'] ?? 0 ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Tasks Debug -->
                    <?php if (hasUserPermission('view_tasks')): ?>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Tasks</h4>
                        <div class="text-xs space-y-1">
                            <div>Recent: <?= count($dashboardData['recent_tasks'] ?? []) ?></div>
                            <div>Priority: <?= count($dashboardData['priority_tasks'] ?? []) ?></div>
                            <div>Total: <?= $dashboardData['task_summary']['total'] ?? 0 ?></div>
                            <div>Completed: <?= $dashboardData['task_summary']['completed'] ?? 0 ?></div>
                            <div>Overdue: <?= $dashboardData['task_summary']['overdue'] ?? 0 ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Milestones Debug -->
                    <?php if (hasUserPermission('view_milestones')): ?>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Milestones</h4>
                        <div class="text-xs space-y-1">
                            <div>Upcoming: <?= count($dashboardData['upcoming_milestones'] ?? []) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Time Tracking Debug -->
                    <?php if (hasUserPermission('view_time_tracking')): ?>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Time Tracking</h4>
                        <div class="text-xs space-y-1">
                            <div>Total Hours: <?= number_format(($dashboardData['time_tracking_summary']['total_hours'] ?? 0) / 3600, 1) ?></div>
                            <div>This Week: <?= number_format(($dashboardData['time_tracking_summary']['this_week'] ?? 0) / 3600, 1) ?></div>
                            <div>Active Timer: <?= isset($dashboardData['active_timer']) && $dashboardData['active_timer'] ? 'Yes' : 'No' ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sprints Debug -->
                    <?php if (hasUserPermission('view_sprints')): ?>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Sprints</h4>
                        <div class="text-xs space-y-1">
                            <div>Active: <?= count($dashboardData['active_sprints'] ?? []) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3 text-xs text-yellow-700 dark:text-yellow-300">
                    💡 Check browser console and server logs for detailed debug information. Remove ?debug=1 from URL to hide this panel.
                </div>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <?= Breadcrumb::render('dashboard', []) ?>

        <!-- Welcome & Stats Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
            <!-- Welcome Card -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Welcome back, <?= htmlspecialchars($user->first_name) ?>!
                        </h1>
                        <?php if (hasUserPermission('view_tasks') || hasUserPermission('view_projects') || hasUserPermission('view_time_tracking')): ?>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">
                                Here's an overview of your work and progress.
                            </p>
                        <?php else: ?>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">
                                You're successfully logged in. Contact your administrator for access to additional features.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Active Timer Widget (if any) -->
                    <?php if (isset($_SESSION['active_timer']) && (hasUserPermission('view_time_tracking') || hasUserPermission('create_time_tracking'))):
                        $activeTask = $dashboardData['active_timer']['task'] ?? null;
                        ?>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 timer-pulse">
                            <div class="flex items-center justify-between">
                                <div class="mr-4">
                                    <div class="text-sm font-medium text-gray-600 dark:text-gray-300">Currently working on:</div>
                                    <a href="/tasks/view/<?= $activeTask->id ?>" class="text-blue-600 dark:text-blue-400 font-medium">
                                        <?= htmlspecialchars($activeTask->title) ?>
                                    </a>
                                </div>
                                <div class="text-right">
                                    <div id="active-timer" class="text-lg font-mono font-bold text-blue-600 dark:text-blue-400">00:00:00</div>
                                    <form action="/tasks/stop-timer/<?= $_SESSION['active_timer']['task_id'] ?>" method="POST" class="mt-1">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                        <button type="submit" class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                            Stop Timer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                    <?php if (hasUserPermission('view_tasks')): ?>
                        <!-- Row 1 -->
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= $dashboardData['task_summary']['in_progress'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Tasks In Progress</div>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-red-600 dark:text-red-400"><?= $dashboardData['task_summary']['overdue'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Overdue Tasks</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400"><?= $dashboardData['story_points_summary']['this_week'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Story Points This Week</div>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-red-600 dark:text-red-400"><?= $dashboardData['task_summary']['bugs'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Bugs to Fix</div>
                        </div>

                        <!-- Row 2 -->
                        <div class="bg-purple-50 dark:bg-purple-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?= $dashboardData['task_summary']['total'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Tasks</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400"><?= $dashboardData['task_summary']['completed'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Completed Tasks</div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= $dashboardData['task_summary']['sprint_ready'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Sprint Ready</div>
                        </div>
                        <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400"><?= $dashboardData['task_summary']['backlog_items'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Backlog Items</div>
                        </div>
                    <?php elseif (hasUserPermission('view_projects')): ?>
                        <!-- Row 1 -->
                        <div class="bg-purple-50 dark:bg-purple-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?= $dashboardData['project_summary']['total'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Projects</div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= $dashboardData['project_summary']['in_progress'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Active Projects</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400"><?= $dashboardData['project_summary']['completed'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Completed Projects</div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400"><?= $dashboardData['project_summary']['delayed'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Delayed Projects</div>
                        </div>

                        <!-- Row 2 -->
                        <div class="bg-orange-50 dark:bg-orange-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-orange-600 dark:text-orange-400"><?= $dashboardData['project_summary']['on_hold'] ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">On Hold Projects</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                    <?php elseif (hasUserPermission('view_time_tracking')): ?>
                        <!-- Row 1 -->
                        <div class="bg-purple-50 dark:bg-purple-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?= number_format($dashboardData['time_tracking_summary']['this_week'] / 3600, 1) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Hours This Week</div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= number_format($dashboardData['time_tracking_summary']['this_month'] / 3600, 1) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Hours This Month</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400"><?= number_format($dashboardData['time_tracking_summary']['billable_hours'] / 3600, 1) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Billable Hours</div>
                        </div>
                        <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400"><?= number_format($dashboardData['time_tracking_summary']['total_hours'] / 3600, 1) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Hours</div>
                        </div>

                        <!-- Row 2 -->
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data</div>
                        </div>
                    <?php else: ?>
                        <!-- Show placeholder for users with no permissions -->
                        <!-- Row 1 -->
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>

                        <!-- Row 2 -->
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 text-center">
                            <div class="text-3xl font-bold text-gray-400 dark:text-gray-600">-</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">No Data Available</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Time Tracking Summary -->
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Time Tracking</h2>

                <?php
                // Force reload permissions from database for admin users
                if (isset($currentUser['roles']) && in_array('admin', $currentUser['roles'])) {
                    // For admin users, ensure they have all permissions
                    $userId = $currentUser['profile']['id'] ?? null;
                    if ($userId) {
                        $userModel = new User();
                        $rolesAndPermissions = $userModel->getRolesAndPermissions($userId);
                        $currentUser['permissions'] = $rolesAndPermissions['permissions'];
                    }
                }
?>

                <?php if (hasUserPermission('view_time_tracking')): ?>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">This Week</span>
                                <span class="font-medium"><?= formatTime($dashboardData['time_tracking_summary']['this_week']) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <?php
                // Assume 40-hour work week
                $weeklyPercentage = min(100, ($dashboardData['time_tracking_summary']['this_week'] / (40 * 3600)) * 100);
                    ?>
                                <div class="bg-blue-600 rounded-full h-2" style="width: <?= $weeklyPercentage ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">This Month</span>
                                <span class="font-medium"><?= formatTime($dashboardData['time_tracking_summary']['this_month']) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <?php
                    // Assume 160-hour work month
                    $monthlyPercentage = min(100, ($dashboardData['time_tracking_summary']['this_month'] / (160 * 3600)) * 100);
                    ?>
                                <div class="bg-purple-600 rounded-full h-2" style="width: <?= $monthlyPercentage ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">Billable Hours</span>
                                <span class="font-medium"><?= formatTime($dashboardData['time_tracking_summary']['billable_hours']) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <?php
                    $billablePercentage = $dashboardData['time_tracking_summary']['total_hours'] > 0
                        ? min(100, ($dashboardData['time_tracking_summary']['billable_hours'] / $dashboardData['time_tracking_summary']['total_hours']) * 100)
                        : 0;
                    ?>
                                <div class="bg-green-600 rounded-full h-2" style="width: <?= $billablePercentage ?>%"></div>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                            <?php if (hasUserPermission('create_tasks')): ?>
                                <a href="/tasks/create" class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-800">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    New Task
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No access to time tracking data</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Quick Actions</h2>

                <div class="space-y-3">
                    <?php if (hasUserPermission('create_projects')): ?>
                        <a href="/projects/create" class="flex items-center p-2 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-gray-800 dark:text-gray-200">New Project</span>
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('create_tasks')): ?>
                        <a href="/tasks/create" class="flex items-center p-2 bg-green-50 dark:bg-green-900/30 hover:bg-green-100 dark:hover:bg-green-900/50 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span class="text-gray-800 dark:text-gray-200">New Task</span>
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('create_milestones')): ?>
                        <a href="/milestones/create" class="flex items-center p-2 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                            </svg>
                            <span class="text-gray-800 dark:text-gray-200">New Milestone</span>
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('create_sprints')): ?>
                        <a href="/sprints" class="flex items-center p-2 bg-yellow-50 dark:bg-yellow-900/30 hover:bg-yellow-100 dark:hover:bg-yellow-900/50 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span class="text-gray-800 dark:text-gray-200">New Sprint</span>
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('create_companies')): ?>
                        <a href="/companies/create" class="flex items-center p-2 bg-purple-50 dark:bg-purple-900/30 hover:bg-purple-100 dark:hover:bg-purple-900/50 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span class="text-gray-800 dark:text-gray-200">New Company</span>
                        </a>
                    <?php endif; ?>

                    <?php
                    // Show message if user has no create permissions
                    $hasAnyCreatePermission = hasUserPermission('create_projects') || hasUserPermission('create_tasks') ||
                                 hasUserPermission('create_milestones') || hasUserPermission('create_sprints') ||
                                 hasUserPermission('create_companies');
if (!$hasAnyCreatePermission):
    ?>
                        <div class="text-center py-4">
                            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No create permissions available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Widgets -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Tasks Section -->
            <?php if (hasUserPermission('view_tasks')): ?>
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">My Tasks</h2>
                        <?php if (hasUserPermission('view_tasks')): ?>
                            <a href="/tasks" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                View All →
                            </a>
                        <?php endif; ?>
                    </div>
                
                <!-- Task Tabs Navigation -->
                <div class="grid grid-cols-3 border-b border-gray-200 dark:border-gray-700">
                    <button class="task-tab active p-3 text-center font-medium border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400" data-target="upcoming-tasks">
                        Upcoming
                    </button>
                    <button class="task-tab p-3 text-center font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 border-b-2 border-transparent" data-target="in-progress-tasks">
                        In Progress
                    </button>
                    <button class="task-tab p-3 text-center font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 border-b-2 border-transparent" data-target="overdue-tasks">
                        Overdue
                    </button>
                </div>
                
                <!-- Upcoming Tasks Tab -->
                <div class="p-4 task-content" id="upcoming-tasks">
                    <?php
    // Debug info for tasks
    if (isset($_GET['debug'])) {
        echo "<div class='mb-4 p-2 bg-green-100 text-black text-xs'>";
        echo "<strong>Tasks Debug:</strong><br>";
        echo "• Recent tasks: " . count($dashboardData['recent_tasks'] ?? []) . "<br>";
        echo "• Priority tasks: " . count($dashboardData['priority_tasks'] ?? []) . "<br>";
        echo "• Task types: " . json_encode($dashboardData['task_type_distribution'] ?? []) . "<br>";
        echo "• Story points this week: " . ($dashboardData['story_points_summary']['this_week'] ?? 0) . "<br>";
        echo "</div>";
    }
                ?>
                    <?php
                $upcomingTasks = array_filter($dashboardData['recent_tasks'], function ($task) {
                    return !empty($task->due_date) &&
                          strtotime($task->due_date) >= time() &&
                          $task->status_id != 5 && $task->status_id != 6; // Not closed or completed
                });

                if (!empty($upcomingTasks)):
                    ?>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach (array_slice($upcomingTasks, 0, 5) as $task): ?>
                                <li class="py-3">
                                    <div class="flex justify-between">
                                        <div>
                                            <a href="/tasks/view/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($task->title) ?>
                                            </a>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?= htmlspecialchars($task->project_name ?? 'No Project') ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium <?= isDueSoon($task->due_date) ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-400' ?>">
                                                <?= formatDueDate($task->due_date) ?>
                                            </div>
                                            <div class="flex items-center justify-end space-x-2 mt-1">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?= getTaskStatusClass($task->status_id) ?>">
                                                    <?= htmlspecialchars($task->status_name) ?>
                                                </span>

                                                <?php
                                                // Check if this specific task has an active timer
                                                $activeTimer = $_SESSION['active_timer'] ?? null;
                                $isTimerActiveForThisTask = isset($activeTimer) && $activeTimer['task_id'] == $task->id;

                                if (hasUserPermission('view_time_tracking') && $isTimerActiveForThisTask):
                                    ?>
                                                    <!-- Stop Timer Button -->
                                                    <form action="/tasks/stop-timer/<?= $task->id ?>" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Stop Timer">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php elseif (hasUserPermission('view_time_tracking') && !isset($activeTimer)): // Only show start timer if no timer is running at all?>
                                                    <!-- Start Timer Button -->
                                                    <form action="/tasks/start-timer/<?= $task->id ?>" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                        <button type="submit" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 p-1 rounded hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors" title="Start Timer">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Edit Button -->
                                                <?php if (hasUserPermission('edit_tasks')): ?>
                                                    <a href="/tasks/edit/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 p-1 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors" title="Edit Task">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                            No upcoming tasks.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- In Progress Tasks Tab -->
                <div class="p-4 task-content hidden" id="in-progress-tasks">
                    <?php
                    $inProgressTasks = array_filter($dashboardData['recent_tasks'], function ($task) {
                        return $task->status_id == 2; // In Progress status
                    });

if (!empty($inProgressTasks)):
    ?>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach (array_slice($inProgressTasks, 0, 5) as $task): ?>
                                <li class="py-3">
                                    <div class="flex justify-between">
                                        <div>
                                            <a href="/tasks/view/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($task->title) ?>
                                            </a>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?= htmlspecialchars($task->project_name ?? 'No Project') ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <?php if (!empty($task->due_date)): ?>
                                                <div class="text-sm font-medium <?= isDueSoon($task->due_date) ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-400' ?>">
                                                    <?= formatDueDate($task->due_date) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex items-center justify-end space-x-2 mt-1">
                                                <?php
                                // Check if this specific task has an active timer
                                $activeTimer = $_SESSION['active_timer'] ?? null;
                                $isTimerActiveForThisTask = isset($activeTimer) && $activeTimer['task_id'] == $task->id;

                                if ($isTimerActiveForThisTask):
                                    ?>
                                                    <!-- Stop Timer Button -->
                                                    <form action="/tasks/stop-timer/<?= $task->id ?>" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Stop Timer">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php elseif (hasUserPermission('view_time_tracking') && !isset($activeTimer)): // Only show start timer if no timer is running at all?>
                                                    <!-- Start Timer Button -->
                                                    <form action="/tasks/start-timer/<?= $task->id ?>" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                        <button type="submit" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 p-1 rounded hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors" title="Start Timer">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Edit Button -->
                                                <?php if (hasUserPermission('edit_tasks')): ?>
                                                    <a href="/tasks/edit/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 p-1 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors" title="Edit Task">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                            No tasks in progress.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Overdue Tasks Tab -->
                <div class="p-4 task-content hidden" id="overdue-tasks">
                    <?php
                    $overdueTasks = array_filter($dashboardData['recent_tasks'], function ($task) {
                        return !empty($task->due_date) &&
                              strtotime($task->due_date) < time() &&
                              $task->status_id != 5 && $task->status_id != 6; // Not closed or completed
                    });

if (!empty($overdueTasks)):
    ?>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach (array_slice($overdueTasks, 0, 5) as $task): ?>
                                <li class="py-3">
                                    <div class="flex justify-between">
                                        <div>
                                            <a href="/tasks/view/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($task->title) ?>
                                            </a>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?= htmlspecialchars($task->project_name ?? 'No Project') ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-red-600 dark:text-red-400">
                                                <?= formatOverdueDate($task->due_date) ?>
                                            </div>
                                            <div class="flex space-x-2 mt-1 justify-end">
                                                <?php
                                // Check if this specific task has an active timer
                                $activeTimer = $_SESSION['active_timer'] ?? null;
                                $isTimerActiveForThisTask = isset($activeTimer) && $activeTimer['task_id'] == $task->id;

                                if ($isTimerActiveForThisTask):
                                    ?>
                                                    <!-- Stop Timer Button -->
                                                    <form action="/tasks/stop-timer/<?= $task->id ?>" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Stop Timer">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php elseif (!isset($activeTimer)): // Only show start timer if no timer is running at all?>
                                                    <!-- Start Timer Button -->
                                                    <form action="/tasks/start-timer/<?= $task->id ?>" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                        <button type="submit" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 p-1 rounded hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors" title="Start Timer">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Edit Button -->
                                                <?php if (hasUserPermission('edit_tasks')): ?>
                                                    <a href="/tasks/edit/<?= $task->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 p-1 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors" title="Edit Task">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                            No overdue tasks.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
                <!-- Show placeholder for users without task permissions -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No Task Access</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You don't have permission to view tasks.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Projects & Milestones Section -->
        <div class="lg:col-span-1">
            <!-- Recent Projects -->
            <?php if (hasUserPermission('view_projects')): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Projects</h2>
                        <a href="/projects" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                            View All →
                        </a>
                    </div>

                    <div class="p-4">
                        <?php
                        // Temporary debug info
                        if (isset($_GET['debug'])) {
                            echo "<div class='mb-4 p-2 bg-yellow-100 text-black text-xs'>";
                            echo "<strong>Recent Projects Debug:</strong><br>";
                            echo "• Projects count: " . count($dashboardData['recent_projects'] ?? []) . "<br>";
                            echo "• User has view_projects permission: " . (hasUserPermission('view_projects') ? 'Yes' : 'No') . "<br>";
                            echo "• Current user ID: " . ($currentUser['profile']['id'] ?? 'Not set') . "<br>";
                            echo "• Query filters: Projects where user is owner OR has assigned tasks<br>";
                            if (!empty($dashboardData['recent_projects'])) {
                                echo "• First project: " . htmlspecialchars($dashboardData['recent_projects'][0]->name ?? 'No name') . "<br>";
                                echo "• Project owner: " . ($dashboardData['recent_projects'][0]->owner_id ?? 'Not set') . "<br>";
                            } else {
                                echo "• No projects found - user may not own any projects or have assigned tasks<br>";
                            }
                            echo "</div>";
                        }
?>
                        <?php if (!empty($dashboardData['recent_projects'])): ?>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach (array_slice($dashboardData['recent_projects'], 0, 4) as $project): ?>
                                    <li class="py-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <a href="/projects/view/<?= $project->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                    <?= htmlspecialchars($project->name) ?>
                                                </a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= htmlspecialchars($project->company_name ?? '') ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php
                        // Project status mapping to match task style
                        $projectStatusMap = [
                            1 => ['label' => 'READY', 'color' => 'bg-gray-600'],
                            2 => ['label' => 'IN PROGRESS', 'color' => 'bg-blue-600'],
                            3 => ['label' => 'COMPLETED', 'color' => 'bg-green-500'],
                            4 => ['label' => 'ON HOLD', 'color' => 'bg-yellow-500'],
                            6 => ['label' => 'DELAYED', 'color' => 'bg-orange-500'],
                            7 => ['label' => 'CANCELLED', 'color' => 'bg-red-500'],
                        ];
                                    $projectStatus = $projectStatusMap[$project->status_id] ?? ['label' => 'UNKNOWN', 'color' => 'bg-gray-500'];
                                    ?>
                                                <span class="px-3 py-1 text-xs rounded-full bg-opacity-20 text-white font-medium <?= $projectStatus['color'] ?>">
                                                    <?= $projectStatus['label'] ?>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                    <?php else: ?>
                        <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                            No recent projects.
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Upcoming Milestones -->
            <?php if (hasUserPermission('view_milestones')): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Upcoming Milestones</h2>
                        <a href="/milestones" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                            View All →
                        </a>
                    </div>

                    <div class="p-4">
                        <?php
                        // Debug info for milestones
                        if (isset($_GET['debug'])) {
                            echo "<div class='mb-4 p-2 bg-blue-100 text-black text-xs'>";
                            echo "<strong>Milestones Debug:</strong><br>";
                            echo "• Count: " . count($dashboardData['upcoming_milestones'] ?? []) . "<br>";
                            echo "• Filter: due_date > " . date('Y-m-d') . " AND is_deleted = 0<br>";
                            if (!empty($dashboardData['upcoming_milestones'])) {
                                echo "• First milestone: " . htmlspecialchars($dashboardData['upcoming_milestones'][0]->title ?? 'No title') . "<br>";
                                echo "• Due date: " . ($dashboardData['upcoming_milestones'][0]->due_date ?? 'Not set') . "<br>";
                            }
                            echo "</div>";
                        }
                ?>
                        <?php if (!empty($dashboardData['upcoming_milestones'])): ?>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach (array_slice($dashboardData['upcoming_milestones'], 0, 4) as $milestone): ?>
                                    <li class="py-3">
                                        <div class="flex justify-between">
                                            <div>
                                                <a href="/milestones/view/<?= $milestone->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                    <?= htmlspecialchars($milestone->title) ?>
                                                </a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= htmlspecialchars($milestone->project_name ?? 'No Project') ?>
                                                </div>
                                            </div>
                                            <div class="text-right text-sm">
                                                <div class="<?= isDueSoon($milestone->due_date) ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-400' ?>">
                                                    <?= formatDueDate($milestone->due_date) ?>
                                                </div>

                                                <?php if (isset($milestone->completion_rate)): ?>
                                                <div class="mt-1 w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 ml-auto">
                                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?= $milestone->completion_rate ?>%"></div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                                No upcoming milestones.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Show placeholder if user has no project or milestone permissions -->
            <?php if (!hasUserPermission('view_projects') && !hasUserPermission('view_milestones')): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Limited Access</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You don't have permission to view projects or milestones.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Project & Sprint Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <!-- Project Status Chart -->
        <?php if (hasUserPermission('view_projects')): ?>
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Project Status</h2>
            
            <div class="flex justify-center">
                <div class="relative inline-block w-40 h-40">
                    <!-- Donut chart using CSS conic gradient -->
                    <div class="absolute inset-0 rounded-full" style="background: conic-gradient(
                        #4ade80 0% <?= $projectCompletedPercent = ($dashboardData['project_summary']['completed'] / max(1, $dashboardData['project_summary']['total'])) * 100 ?>%, 
                        #60a5fa <?= $projectCompletedPercent ?>% <?= $projectCompletedPercent + ($dashboardData['project_summary']['in_progress'] / max(1, $dashboardData['project_summary']['total'])) * 100 ?>%, 
                        #f97316 <?= $projectCompletedPercent + ($dashboardData['project_summary']['in_progress'] / max(1, $dashboardData['project_summary']['total'])) * 100 ?>% <?= $projectCompletedPercent + ($dashboardData['project_summary']['in_progress'] / max(1, $dashboardData['project_summary']['total'])) * 100 + ($dashboardData['project_summary']['delayed'] / max(1, $dashboardData['project_summary']['total'])) * 100 ?>%, 
                        #a8a29e <?= $projectCompletedPercent + ($dashboardData['project_summary']['in_progress'] / max(1, $dashboardData['project_summary']['total'])) * 100 + ($dashboardData['project_summary']['delayed'] / max(1, $dashboardData['project_summary']['total'])) * 100 ?>% 100%);"></div>
                    <!-- Inner white circle to create donut -->
                    <div class="absolute inset-4 bg-white dark:bg-gray-800 rounded-full flex items-center justify-center">
                        <span class="text-lg font-semibold"><?= $dashboardData['project_summary']['total'] ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Legend -->
            <div class="grid grid-cols-2 gap-2 mt-4">
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-green-400 rounded-full mr-2"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Completed (<?= $dashboardData['project_summary']['completed'] ?>)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-blue-400 rounded-full mr-2"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active (<?= $dashboardData['project_summary']['in_progress'] ?>)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-orange-400 rounded-full mr-2"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Delayed (<?= $dashboardData['project_summary']['delayed'] ?>)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">On Hold (<?= $dashboardData['project_summary']['on_hold'] ?? 0 ?>)</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Task Completion Status -->
        <?php if (hasUserPermission('view_tasks')): ?>
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Task Status</h2>
            
            <?php
            // Calculate task completion percentage
            $taskTotal = $dashboardData['task_summary']['total'] ?: 1;
            $completedPercentage = round(($dashboardData['task_summary']['completed'] / $taskTotal) * 100);
            $inProgressPercentage = round(($dashboardData['task_summary']['in_progress'] / $taskTotal) * 100);
            $overduePercentage = round(($dashboardData['task_summary']['overdue'] / $taskTotal) * 100);
            $openOtherCount = $dashboardData['task_summary']['open_other'] ?? 0;
            $remainingPercentage = round(($openOtherCount / $taskTotal) * 100);
            ?>
            
            <!-- Task Progress Bars -->
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 dark:text-gray-400">Completed</span>
                        <span class="font-medium"><?= $dashboardData['task_summary']['completed'] ?> (<?= $completedPercentage ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= $completedPercentage ?>%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 dark:text-gray-400">In Progress</span>
                        <span class="font-medium"><?= $dashboardData['task_summary']['in_progress'] ?> (<?= $inProgressPercentage ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?= $inProgressPercentage ?>%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 dark:text-gray-400">Overdue</span>
                        <span class="font-medium"><?= $dashboardData['task_summary']['overdue'] ?> (<?= $overduePercentage ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-red-500 h-2.5 rounded-full" style="width: <?= $overduePercentage ?>%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 dark:text-gray-400">Open/Other</span>
                        <span class="font-medium"><?= $dashboardData['task_summary']['open_other'] ?? 0 ?> (<?= $remainingPercentage ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-gray-500 h-2.5 rounded-full" style="width: <?= $remainingPercentage ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Sprints -->
        <?php if (hasUserPermission('view_sprints')): ?>
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Active Sprints</h2>
                    <a href="/sprints" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                        View All →
                    </a>
                </div>

            <?php
            // Debug info for active sprints
            if (isset($_GET['debug'])) {
                echo "<div class='mb-4 p-2 bg-purple-100 text-black text-xs'>";
                echo "<strong>Active Sprints Debug:</strong><br>";
                echo "• Count: " . count($dashboardData['active_sprints'] ?? []) . "<br>";
                echo "• Filter: status = 'active' for user projects<br>";
                if (!empty($dashboardData['active_sprints'])) {
                    $sprint = $dashboardData['active_sprints'][0];
                    echo "• First sprint: " . htmlspecialchars($sprint->name ?? 'No name') . "<br>";
                    echo "• Start date: " . ($sprint->start_date ?? 'Not set') . "<br>";
                    echo "• End date: " . ($sprint->end_date ?? 'Not set') . "<br>";
                    echo "• Status: " . ($sprint->status_name ?? 'Unknown') . "<br>";
                } else {
                    echo "• No active sprints found for user's projects<br>";
                }
                echo "</div>";
            }
?>

            <?php if (isset($dashboardData['active_sprints']) && !empty($dashboardData['active_sprints'])): ?>
                <ul class="space-y-4">
                    <?php foreach (array_slice($dashboardData['active_sprints'], 0, 3) as $sprint):
                        // Calculate sprint progress
                        $sprintStartDate = strtotime($sprint->start_date);
                        $sprintEndDate = strtotime($sprint->end_date);
                        $today = time();
                        $sprintDuration = $sprintEndDate - $sprintStartDate;
                        $elapsed = $today - $sprintStartDate;
                        $progressPercentage = min(100, max(0, ($elapsed / $sprintDuration) * 100));

                        // Calculate days remaining
                        $daysRemaining = ceil(($sprintEndDate - $today) / (60 * 60 * 24));
                        ?>
                        <li class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <a href="/sprints/view/<?= $sprint->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                        <?= htmlspecialchars($sprint->name) ?>
                                    </a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?= date('M d', strtotime($sprint->start_date)) ?> - <?= date('M d, Y', strtotime($sprint->end_date)) ?>
                                    </div>
                                </div>
                                <div class="text-sm font-medium <?= $daysRemaining <= 2 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' ?>">
                                    <?= $daysRemaining > 0 ? $daysRemaining . ' days left' : 'Ends today' ?>
                                </div>
                            </div>
                            
                            <!-- Sprint progress bar -->
                            <div class="mt-2">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500 dark:text-gray-400">Progress</span>
                                    <span><?= round($progressPercentage) ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?= $progressPercentage ?>%"></div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                    No active sprints.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Show placeholder if user has no chart permissions -->
        <?php if (!hasUserPermission('view_projects') && !hasUserPermission('view_tasks') && !hasUserPermission('view_sprints')): ?>
            <div class="lg:col-span-3 bg-white dark:bg-gray-800 shadow rounded-lg p-8">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No Chart Data Available</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You don't have permission to view project, task, or sprint analytics.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>


<?php





?>

<!-- JavaScript for Active Timer and Tab Switching -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug logging to console
        <?php if (isset($_GET['debug'])): ?>
        console.log('🐛 Dashboard Debug Data:', {
            user_id: <?= $currentUser['profile']['id'] ?? 'null' ?>,
            permissions: <?= json_encode($currentUser['permissions'] ?? []) ?>,
            recent_projects: <?= json_encode($dashboardData['recent_projects'] ?? []) ?>,
            recent_tasks: <?= json_encode($dashboardData['recent_tasks'] ?? []) ?>,
            task_summary: <?= json_encode($dashboardData['task_summary'] ?? []) ?>,
            project_summary: <?= json_encode($dashboardData['project_summary'] ?? []) ?>,
            time_tracking: <?= json_encode($dashboardData['time_tracking_summary'] ?? []) ?>,
            active_timer: <?= json_encode($dashboardData['active_timer'] ?? null) ?>,
            upcoming_milestones: <?= json_encode($dashboardData['upcoming_milestones'] ?? []) ?>,
            active_sprints: <?= json_encode($dashboardData['active_sprints'] ?? []) ?>
        });
        <?php endif; ?>
        // Active timer counter
        const activeTimerElement = document.getElementById('active-timer');
        if (activeTimerElement) {
            let startTime = <?= isset($dashboardData['active_timer']) ? $dashboardData['active_timer']['start_time'] : 0 ?>;
            
            function updateTimer() {
                if (!startTime) return;
                
                const now = Math.floor(Date.now() / 1000);
                const duration = now - startTime;
                
                const hours = Math.floor(duration / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((duration % 3600) / 60).toString().padStart(2, '0');
                const seconds = Math.floor(duration % 60).toString().padStart(2, '0');
                
                activeTimerElement.textContent = `${hours}:${minutes}:${seconds}`;
            }
            
            // Update every second
            setInterval(updateTimer, 1000);
            updateTimer(); // Initial update
        }
        
        // Tab switching for tasks
        const taskTabs = document.querySelectorAll('.task-tab');
        const taskContents = document.querySelectorAll('.task-content');
        
        taskTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Deactivate all tabs
                taskTabs.forEach(t => {
                    t.classList.remove('active', 'border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                    t.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'dark:text-gray-400', 'dark:hover:text-gray-300');
                });
                
                // Activate clicked tab
                tab.classList.add('active', 'border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                tab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'dark:text-gray-400', 'dark:hover:text-gray-300');
                
                // Hide all content
                taskContents.forEach(content => {
                    content.classList.add('hidden');
                });
                
                // Show relevant content
                const targetId = tab.dataset.target;
                document.getElementById(targetId).classList.remove('hidden');
            });
        });
    });
</script>
</body>
</html>