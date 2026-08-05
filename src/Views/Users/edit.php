<?php
//file: Views/Users/edit.php
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
    <title>Edit User: <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Edit User: <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?></h1>
            
            <div class="flex space-x-2">
                <a href="/users/view/<?= htmlspecialchars((string)$user->id) ?>" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View Profile
                </a>
                <a href="/users" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">User Information</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update user details, role, and company assignment</p>
            </div>
            
            <!-- Edit User Form -->
            <form method="POST" action="/users/update" class="p-6 space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? ''); ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$user->id); ?>">
                
                <!-- Form Grid Layout -->
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- First Name -->
                    <div class="sm:col-span-3">
                        <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            First Name
                        </label>
                        <div class="mt-1">
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user->first_name ?? ''); ?>" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Last Name -->
                    <div class="sm:col-span-3">
                        <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Last Name
                        </label>
                        <div class="mt-1">
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user->last_name ?? ''); ?>" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="sm:col-span-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email Address
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user->email ?? ''); ?>" required
                                class="pl-10 px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="sm:col-span-2">
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone Number
                        </label>
                        <div class="mt-1">
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user->phone ?? ''); ?>"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="sm:col-span-3">
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Role
                        </label>
                        <div class="mt-1">
                            <select id="role_id" name="role_id" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                                <option value="">Select a role</option>
                                <?php foreach ($roles['records'] as $role): ?>
                                    <option value="<?= htmlspecialchars((string)$role->id); ?>" <?= $role->id == $user->role_id ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($role->name); ?> <?= $role->description ? '- ' . htmlspecialchars($role->description) : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Company -->
                    <div class="sm:col-span-3">
                        <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Company
                        </label>
                        <div class="mt-1">
                            <select id="company_id" name="company_id"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                                <option value="">No Company</option>
                                <?php foreach ($companies['records'] as $company): ?>
                                    <option value="<?= htmlspecialchars((string)$company->id); ?>" <?= $company->id == $user->company_id ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($company->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div class="sm:col-span-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="is_active" name="is_active" type="checkbox" value="1" <?= $user->is_active ? 'checked' : ''; ?>
                                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="is_active" class="font-medium text-gray-700 dark:text-gray-300">Account Active</label>
                                <p class="text-gray-500 dark:text-gray-400">Users with inactive accounts cannot log in</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-end">
                        <a href="/users/view/<?= htmlspecialchars((string)$user->id) ?>" class="bg-white dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Additional Options Section -->
        <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Account Options</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Additional user account management options</p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Reset Password Option -->
                <div class="bg-gray-50 dark:bg-gray-700 shadow-sm rounded-lg p-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-2">Reset Password</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Send a password reset link to this user's email address.
                    </p>
                    <form action="/users/reset-password" method="POST" class="inline-block">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? ''); ?>">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$user->id); ?>">
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                            Send Reset Link
                        </button>
                    </form>
                </div>
                
                <!-- Company Assignments -->
                <div class="bg-gray-50 dark:bg-gray-700 shadow-sm rounded-lg p-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-2">Additional Company Access</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Grant this user access to additional companies beyond their primary company.
                    </p>
                    <div class="mt-3 space-y-4">
                        <?php if (!empty($user->companies)): ?>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php foreach ($user->companies as $company): ?>
                                    <?php if ($company->id != $user->company_id): ?>
                                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                            <?= htmlspecialchars($company->name) ?>
                                            <form action="/users/remove-company" method="POST" class="inline-block ml-1">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? ''); ?>">
                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$user->id); ?>">
                                                <input type="hidden" name="company_id" value="<?= htmlspecialchars((string)$company->id); ?>">
                                                <button type="submit" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 ml-1">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="/users/add-company" method="POST" class="sm:flex sm:items-center">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$user->id); ?>">
                            <div class="w-full sm:max-w-xs">
                                <select name="company_id" id="add_company_id" required
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                                    <option value="">Select company to add</option>
                                    <?php foreach ($companies['records'] as $company): ?>
                                        <?php
                                        // Skip user's primary company and already assigned companies
                                        $isAlreadyAssigned = false;
                                        if (!empty($user->companies)) {
                                            foreach ($user->companies as $userCompany) {
                                                if ($userCompany->id == $company->id) {
                                                    $isAlreadyAssigned = true;

                                                    break;
                                                }
                                            }
                                        }
                                        if ($company->id != $user->company_id && !$isAlreadyAssigned):
                                            ?>
                                            <option value="<?= htmlspecialchars((string)$company->id); ?>">
                                                <?= htmlspecialchars($company->name); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="mt-3 sm:mt-0 sm:ml-3 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Company
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

</body>
</html>