<?php
//file: Views/Users/profile.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">    
                <h1 class="text-2xl font-bold mr-2">My Profile</h1>
                <!-- Status Badge -->
                <?php if (isset($user->is_active) && $user->is_active): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        Active
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        Inactive
                    </span>
                <?php endif; ?>
            </div>  
            <div class="flex space-x-2">
                <a href="/dashboard" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Profile Layout - Three column layout: 1/4 - 2/4 - 1/4 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Profile Overview Column - Left side, 1/4 width -->
            <div class="md:col-span-1">
                <!-- Profile Details Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Profile Details</h3>
                        <button type="button" class="section-toggle" data-target="profile-details">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="profile-details" class="p-4 space-y-3 text-sm">
                        <!-- User Avatar -->
                        <div class="flex justify-center mb-4">
                            <div class="h-24 w-24 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-700 dark:text-indigo-200 font-bold text-2xl">
                                <?= strtoupper(substr($user->first_name ?? '', 0, 1) . substr($user->last_name ?? '', 0, 1)) ?>
                            </div>
                        </div>
                        
                        <!-- User Name -->
                        <div class="text-center mb-4">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?>
                            </h2>
                            <!-- Role Badge -->
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 mt-2">
                                <?= htmlspecialchars($user->role_name ?? 'No Role') ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-2">
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Email:</div>
                            <div class="text-gray-900 dark:text-white break-all">
                                <?= htmlspecialchars($user->email ?? 'None') ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Phone:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= htmlspecialchars($user->phone ?? 'None') ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Company:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= htmlspecialchars($user->company_name ?? 'None') ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Member Since:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= !empty($user->created_at) ? date('M j, Y', strtotime($user->created_at)) : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content Section - Middle, 2/4 width -->
            <div class="md:col-span-2">
                <?php
                // Check permissions for tasks and projects
                $canViewTasks = in_array('view_tasks', $user->permissions ?? []);
$canViewProjects = in_array('view_projects', $user->permissions ?? []);
$hasContent = $canViewTasks || $canViewProjects;
?>

                <?php if ($canViewTasks): ?>
                <!-- My Active Tasks Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">My Active Tasks</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                            <?= count($user->active_tasks ?? []) ?> Active
                        </span>
                        <button type="button" class="section-toggle" data-target="my-tasks">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="my-tasks">
                        <?php if (!empty($user->active_tasks)): ?>
                            <div class="overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Task
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Priority
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Due Date
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php foreach ($user->active_tasks as $task): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <div>
                                                        <a href="/tasks/view/<?= htmlspecialchars((string)$task->id) ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium text-sm">
                                                            <?= htmlspecialchars($task->title) ?>
                                                        </a>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            <?= htmlspecialchars($task->project_name ?? 'No Project') ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <?php
                                    // Priority badge
                                    $priorityClasses = [
                                        'high' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                                        'medium' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
                                        'low' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                                        'none' => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200',
                                    ];
                                            $priorityClass = $priorityClasses[$task->priority ?? 'none'] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
                                            ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $priorityClass ?>">
                                                        <?= ucfirst(htmlspecialchars($task->priority ?? 'None')) ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                    <?php if (!empty($task->due_date)): ?>
                                                        <?php
                                                $dueDate = strtotime($task->due_date);
                                                        $today = strtotime('today');
                                                        $isOverdue = $dueDate < $today && ($task->status_id != 6 && $task->status_id != 5); // Not completed or closed
                                                        ?>
                                                        <span class="<?= $isOverdue ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-900 dark:text-gray-200' ?> whitespace-nowrap">
                                                            <?= date('M j, Y', $dueDate) ?>
                                                        </span>
                                                        <?php if ($isOverdue): ?>
                                                            <div class="mt-1">
                                                                <span class="text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 px-2 py-0.5 rounded">
                                                                    Overdue
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
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
                            <div class="p-4">
                                <p class="text-gray-500 dark:text-gray-400 text-sm italic">No active tasks</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($canViewProjects): ?>
                <!-- My Projects Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">My Projects</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            <?= count($user->projects ?? []) ?> Projects
                        </span>
                        <button type="button" class="section-toggle" data-target="my-projects">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="my-projects" class="p-4">
                        <?php if (!empty($user->projects)): ?>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($user->projects as $project): ?>
                                    <div class="py-3 flex justify-between items-center">
                                        <div class="flex-1 min-w-0">
                                            <a href="/projects/view/<?= htmlspecialchars((string)$project->id) ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($project->name) ?>
                                            </a>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                <?php if (!empty($project->description)): ?>
                                                    <?= htmlspecialchars(substr($project->description, 0, 80)) . (strlen($project->description) > 80 ? '...' : '') ?>
                                                <?php else: ?>
                                                    No description available
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="ml-3 flex-shrink-0">
                                            <?php
                                            // Project status mapping to match tasks page style
                                            $projectStatusMap = [
                                                'ready' => ['label' => 'READY', 'color' => 'bg-gray-600'],
                                                'in_progress' => ['label' => 'IN PROGRESS', 'color' => 'bg-blue-600'],
                                                'completed' => ['label' => 'COMPLETED', 'color' => 'bg-green-500'],
                                                'on_hold' => ['label' => 'ON HOLD', 'color' => 'bg-yellow-500'],
                                                'delayed' => ['label' => 'DELAYED', 'color' => 'bg-orange-500'],
                                                'cancelled' => ['label' => 'CANCELLED', 'color' => 'bg-red-500'],
                                            ];
                                    $statusInfo = $projectStatusMap[$project->status_name ?? 'ready'] ?? ['label' => 'UNKNOWN', 'color' => 'bg-gray-500'];
                                    ?>
                                            <span class="px-3 py-1 text-xs rounded-full bg-opacity-20 text-white font-medium whitespace-nowrap <?= $statusInfo['color'] ?>">
                                                <?= $statusInfo['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 text-sm italic">No projects assigned</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$hasContent): ?>
                <!-- No Content Available Placeholder -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">My Work</h3>
                    </div>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No work items available</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            You don't have permission to view tasks or projects, or none are assigned to you.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Roles & Permissions Column - Right side, 1/4 width -->
            <div class="md:col-span-1">
                <!-- Roles & Permissions Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">My Roles & Permissions</h3>
                        <button type="button" class="section-toggle" data-target="my-permissions">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="my-permissions" class="p-4">
                        <!-- User Roles -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">My Roles</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php if (!empty($user->roles)): ?>
                                    <?php foreach ($user->roles as $role): ?>
                                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                            <?= htmlspecialchars($role) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-gray-500 dark:text-gray-400 text-sm italic">No roles assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- User Permissions -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">My Permissions</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php if (!empty($user->permissions)): ?>
                                    <?php foreach ($user->permissions as $permission): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            <?= htmlspecialchars($permission) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-gray-500 dark:text-gray-400 text-sm italic">No permissions assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>
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
            // Setup collapsible sections
            const toggles = document.querySelectorAll('.section-toggle');
            
            toggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetElement = document.getElementById(targetId);
                    const icon = this.querySelector('svg');
                    
                    if (targetElement) {
                        targetElement.classList.toggle('hidden');
                        icon.classList.toggle('rotate-180');
                    }
                });
            });
        });
    </script>

</body>
</html>
