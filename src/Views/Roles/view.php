<?php
//file: Views/Roles/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Permission checks
$canEditRoles = isset($currentUser['permissions']) && in_array('edit_roles', $currentUser['permissions']);
$canDeleteRoles = isset($currentUser['permissions']) && in_array('delete_roles', $currentUser['permissions']);

// Users with this role are provided by the controller via $data.
$usersWithRole = $usersWithRole ?? [];

// Ensure permissions exist and are an array
$role->permissions = $role->permissions ?? [];

// Group permissions by category
$groupedPermissions = [];
foreach ($role->permissions as $permission) {
    $parts = explode('_', $permission->name);
    $group = $parts[0];
    if (!isset($groupedPermissions[$group])) {
        $groupedPermissions[$group] = [];
    }
    $groupedPermissions[$group][] = $permission;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Details: <?= htmlspecialchars($role->name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>
    
    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <!-- Notifications -->
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>
        
        <!-- Breadcrumb -->
        <?php echo \App\Utils\Breadcrumb::render('roles/view', ['id' => $role->id]); ?>
        
        <!-- Action buttons -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">    
                <h1 class="text-2xl font-bold mr-2"><?= htmlspecialchars($role->name) ?></h1>
            </div>  
            <div class="flex space-x-2">
                <?php if ($canEditRoles): ?>
                <a href="/roles/edit/<?= htmlspecialchars((string)$role->id) ?>" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Role
                </a>
                <?php endif; ?>
                
                <?php if ($canDeleteRoles): ?>
                <form action="/roles/delete/<?= htmlspecialchars((string)$role->id) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this role? This action cannot be undone.');" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete
                    </button>
                </form>
                <?php endif; ?>
                
                <a href="/roles" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <!-- Role Details Layout -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Role Info Card -->
            <div class="md:col-span-1">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="flex flex-col">
                        <!-- Role Icon -->
                        <div class="mx-auto h-24 w-24 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-700 dark:text-indigo-200 mb-4">
                            <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path>
                            </svg>
                        </div>
                        
                        <!-- Role Name & Info -->
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 text-center mb-1">
                            <?= htmlspecialchars($role->name) ?>
                        </h2>
                        
                        <div class="w-full">
                            <hr class="border-gray-200 dark:border-gray-700 my-4">
                            
                            <!-- Role Information -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">Description: <?= htmlspecialchars($role->description ?? 'No description provided') ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">Permissions: <?= count($role->permissions) ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">Users Assigned: <?= count($usersWithRole) ?></span>
                                </div>

                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">Created: <?= date('M j, Y', strtotime($role->created_at)) ?></span>
                                </div>
                                
                                <div class="flex items-center text-sm">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-gray-600 dark:text-gray-400">Last Updated: <?= date('M j, Y', strtotime($role->updated_at)) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role Details Section -->
            <div class="md:col-span-2">
                <!-- Permissions Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Assigned Permissions</h3>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($groupedPermissions)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($groupedPermissions as $group => $permissions): ?>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-2 capitalize">
                                            <?= htmlspecialchars(str_replace('_', ' ', $group)); ?> Permissions
                                        </h4>
                                        <ul class="space-y-1">
                                            <?php foreach ($permissions as $permission): ?>
                                                <li class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $permission->name))); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                            This role has no permissions assigned. Users with this role will not be able to perform any actions.
                                            <?php if ($canEditRoles): ?>
                                                <a href="/roles/edit/<?= htmlspecialchars((string)$role->id) ?>" class="font-medium underline">Edit this role</a> to assign permissions.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Users with this Role Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Users with this Role</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            <?= count($usersWithRole) ?> user<?= count($usersWithRole) !== 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($usersWithRole)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($usersWithRole as $user): ?>
                                    <div class="flex items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex-shrink-0 h-10 w-10 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                            <span class="text-gray-600 dark:text-gray-300">
                                                <?= strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <a href="/users/view/<?= htmlspecialchars((string)$user->id) ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                    <?= htmlspecialchars($user->first_name . ' ' . $user->last_name); ?>
                                                </a>
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <?= htmlspecialchars($user->email); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg text-center">
                                <p class="text-gray-500 dark:text-gray-400">
                                    No users are currently assigned to this role.
                                </p>
                            </div>
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