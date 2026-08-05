<?php
//file: Views/Users/create.php
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
    <title>Create New User - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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
            <div>
                <h1 class="text-2xl font-bold">Create New User</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Add a new user to the system</p>
            </div>
            
            <a href="/users" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Users
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">User Information</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Enter the new user's details</p>
            </div>
            
            <!-- Create User Form -->
            <form method="POST" action="/users/create" class="p-6 space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? ''); ?>">
                
                <!-- Form Grid Layout -->
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- First Name -->
                    <div class="sm:col-span-3">
                        <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            First Name
                        </label>
                        <div class="mt-1">
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_SESSION['form_data']['first_name'] ?? ''); ?>" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Last Name -->
                    <div class="sm:col-span-3">
                        <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Last Name
                        </label>
                        <div class="mt-1">
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_SESSION['form_data']['last_name'] ?? ''); ?>" required
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
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>" required
                                class="pl-10 px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="sm:col-span-2">
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone Number
                        </label>
                        <div class="mt-1">
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($_SESSION['form_data']['phone'] ?? ''); ?>"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="sm:col-span-3">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <div class="mt-1">
                            <input type="password" id="password" name="password" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters, at least one letter and one number</p>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="sm:col-span-3">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirm Password
                        </label>
                        <div class="mt-1">
                            <input type="password" id="confirm_password" name="confirm_password" required
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
                                <?php
                                $selectedRoleId = $_SESSION['form_data']['role_id'] ?? '';
foreach ($roles['records'] as $role): ?>
                                    <option value="<?= htmlspecialchars((string)$role->id); ?>" <?= $selectedRoleId == $role->id ? 'selected' : ''; ?>>
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
                                <?php
$selectedCompanyId = $_SESSION['form_data']['company_id'] ?? '';
foreach ($companies['records'] as $company): ?>
                                    <option value="<?= htmlspecialchars((string)$company->id); ?>" <?= $selectedCompanyId == $company->id ? 'selected' : ''; ?>>
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
                                <input id="is_active" name="is_active" type="checkbox" value="1" checked
                                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="is_active" class="font-medium text-gray-700 dark:text-gray-300">Account Active</label>
                                <p class="text-gray-500 dark:text-gray-400">The user will still need to verify their email before logging in</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Send Welcome Email -->
                    <div class="sm:col-span-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="send_welcome_email" name="send_welcome_email" type="checkbox" value="1" checked
                                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="send_welcome_email" class="font-medium text-gray-700 dark:text-gray-300">Send Welcome Email</label>
                                <p class="text-gray-500 dark:text-gray-400">Send welcome email with activation instructions to the user</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-end">
                        <a href="/users" class="bg-white dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create User
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- User Creation Notes -->
        <div class="mt-6 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 md:flex md:justify-between">
                    <p class="text-sm text-blue-700 dark:text-blue-200">
                        After creating a user, they will receive an email with instructions to activate their account.
                        You can assign additional companies and projects to users after creation.
                    </p>
                    <p class="mt-3 text-sm md:mt-0 md:ml-6">
                        <a href="/roles" class="whitespace-nowrap font-medium text-blue-700 hover:text-blue-600 dark:text-blue-200 dark:hover:text-blue-100">
                            Manage Roles <span aria-hidden="true">&rarr;</span>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

</body>
</html>