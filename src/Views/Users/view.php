<?php
//file: Views/Users/view.php
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
    <title>User Profile: <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <!-- Action buttons -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">    
                <h1 class="text-2xl font-bold mr-2"><?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?></h1>
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
                <a href="/users/edit/<?= htmlspecialchars((string)$user->id) ?>" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit User
                </a>
                <form action="/users/delete/<?= htmlspecialchars((string)$user->id) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete
                    </button>
                </form>
                <a href="/users" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <!-- User Profile Layout -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- User Info Card -->
            <div class="md:col-span-1">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="flex flex-col items-center">
                        <!-- User Avatar -->
                        <div class="h-32 w-32 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-700 dark:text-indigo-200 font-bold text-4xl mb-4">
                            <?= strtoupper(substr($user->first_name ?? '', 0, 1) . substr($user->last_name ?? '', 0, 1)) ?>
                        </div>
                        
                        <!-- User Name & Status -->
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">
                            <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?>
                        </h2>
                        
                        <!-- Role Badge -->
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 mb-4">
                            <?= htmlspecialchars($user->role_name ?? 'No Role') ?>
                        </span>
                        
                        <div class="w-full">
                            <hr class="border-gray-200 dark:border-gray-700 my-4">
                            
                            <!-- Contact Information -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user->email ?? 'No email') ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user->phone ?? 'No phone number') ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user->company_name ?? 'No company') ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">Joined: <?= !empty($user->created_at) ? date('M j, Y', strtotime($user->created_at)) : 'N/A' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Details Section -->
            <div class="md:col-span-2">
                <!-- Permissions & Roles Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Roles & Permissions</h3>
                    </div>
                    <div class="px-6 py-4">
                        <!-- User Roles -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Assigned Roles</h4>
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
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Permissions</h4>
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

                <!-- Projects Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Assigned Projects</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            <?= count($user->projects ?? []) ?> Projects
                        </span>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($user->projects)): ?>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($user->projects as $project): ?>
                                    <div class="py-3 flex justify-between items-center">
                                        <div>
                                            <a href="/projects/view/<?= htmlspecialchars((string)$project->id) ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($project->name) ?>
                                            </a>
                                            <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($project->status_name ?? '') ?></p>
                                        </div>
                                        <div>
                                            <?php
                                            // Determine badge color based on status
                                            $statusInfo = getProjectStatusInfo($project->status_id ?? 1);
                                    echo renderStatusPill($statusInfo['label'], $statusInfo['color'], 'sm');
                                    ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 text-sm italic">No projects assigned</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active Tasks Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Active Tasks</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                            <?= count($user->active_tasks ?? []) ?> Active
                        </span>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($user->active_tasks)): ?>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($user->active_tasks as $task): ?>
                                    <div class="py-3">
                                        <div class="flex justify-between items-center mb-1">
                                            <a href="/tasks/view/<?= htmlspecialchars((string)$task->id) ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($task->title) ?>
                                            </a>
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
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $priorityClass ?>">
                                                <?= ucfirst(htmlspecialchars($task->priority ?? 'None')) ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">
                                                <?= htmlspecialchars($task->project_name ?? 'No Project') ?>
                                            </span>
                                            <span class="text-gray-500 dark:text-gray-400">
                                                <?php if (!empty($task->due_date)): ?>
                                                    Due: <?= date('M j, Y', strtotime($task->due_date)) ?>
                                                <?php else: ?>
                                                    No due date
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 text-sm italic">No active tasks</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

</body>
</html>