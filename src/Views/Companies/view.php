<?php
//file: Views/Companies/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company: <?= htmlspecialchars($company->name) ?></title>
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
                <h1 class="text-2xl font-bold mr-2"><?= htmlspecialchars($company->name) ?></h1>
            </div>  
            <div class="flex space-x-2">
                <a href="/companies/edit/<?= htmlspecialchars((string)$company->id) ?>" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Company
                </a>
                <form action="/companies/delete/<?= htmlspecialchars((string)$company->id) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this company?');" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete
                    </button>
                </form>
                <a href="/companies" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <!-- Company Profile Layout -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Company Info Card -->
            <div class="md:col-span-1">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="flex flex-col items-center">
                        <!-- Company Avatar -->
                        <div class="h-32 w-32 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-700 dark:text-indigo-200 font-bold text-4xl mb-4">
                            <?= strtoupper(substr($company->name ?? '', 0, 1)) ?>
                        </div>
                        
                        <!-- Company Name -->
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <?= htmlspecialchars($company->name) ?>
                        </h2>
                        
                        <div class="w-full">
                            <hr class="border-gray-200 dark:border-gray-700 my-4">
                            
                            <!-- Contact Information -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($company->email ?? 'No email') ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($company->phone ?? 'No phone number') ?></span>
                                </div>

                                <?php if (!empty($company->website)): ?>
                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                    </svg>
                                    <a href="<?= htmlspecialchars($company->website) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        <?= htmlspecialchars($company->website) ?>
                                    </a>
                                </div>
                                <?php endif; ?>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">
                                        Created: <?= !empty($company->created_at) ? date('M j, Y', strtotime($company->created_at)) : 'N/A' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Card -->
                <?php if (!empty($company->address)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Address</h3>
                    <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line"><?= nl2br(htmlspecialchars($company->address)) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Company Details Section -->
            <div class="md:col-span-2">
                <!-- Company Users Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Company Users</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            <?= count($users ?? []) ?> Users
                        </span>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($users)): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($users as $user): ?>
                                    <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-700 dark:text-gray-300 font-semibold text-lg">
                                            <?= strtoupper(substr($user->first_name ?? '', 0, 1) . substr($user->last_name ?? '', 0, 1)) ?>
                                        </div>
                                        <div class="ml-3">
                                            <a href="/users/view/<?= htmlspecialchars((string)$user->id) ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?>
                                            </a>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?= htmlspecialchars($user->email) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 text-sm italic">No users associated with this company</p>
                        <?php endif; ?>
                        <div class="mt-4">
                            <a href="/users" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Manage Users
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Projects Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Projects</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                            <?= count($projects ?? []) ?> Projects
                        </span>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($projects)): ?>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($projects as $project): ?>
                                    <div class="py-3 flex justify-between items-center">
                                        <div>
                                            <a href="/projects/view/<?= htmlspecialchars((string)$project->id) ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                <?= htmlspecialchars($project->name) ?>
                                            </a>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php
                                                // Display date range if available
                                                if (!empty($project->start_date) && !empty($project->end_date)) {
                                                    echo date('M j, Y', strtotime($project->start_date)) . ' - ' . date('M j, Y', strtotime($project->end_date));
                                                } elseif (!empty($project->start_date)) {
                                                    echo 'From ' . date('M j, Y', strtotime($project->start_date));
                                                } elseif (!empty($project->end_date)) {
                                                    echo 'Due ' . date('M j, Y', strtotime($project->end_date));
                                                }
                                    ?>
                                            </p>
                                        </div>
                                        <?php
                                        // Determine badge color based on status
                                        $statusClasses = [
                                            'ready' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                                            'in_progress' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
                                            'completed' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
                                            'delayed' => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
                                            'on_hold' => 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
                                            'cancelled' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                                        ];
                                    $statusClass = $statusClasses[$project->status_name ?? 'ready'] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
                                    ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                                            <?= htmlspecialchars($project->status_name ?? 'Unknown') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 text-sm italic">No projects associated with this company</p>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="/projects/create" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                New Project
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Notes/Description Card (if needed in the future) -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Statistics</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Tasks</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    <?= $activeTasks ?? 0 ?>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Projects</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    <?= $activeProjects ?? 0 ?>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    <?= count($users ?? []) ?>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Projects</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    <?= count($projects ?? []) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

</body>
</html>