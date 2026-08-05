<?php
//file: Views/Companies/edit.php
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
    <title>Edit Company: <?= htmlspecialchars($company->name) ?></title>
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
                <h1 class="text-2xl font-bold">Edit Company: <?= htmlspecialchars($company->name) ?></h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Update company information</p>
            </div>
            
            <div class="flex space-x-2">
                <a href="/companies/view/<?= htmlspecialchars((string)$company->id) ?>" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View Company
                </a>
                <a href="/companies" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Company Information</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Edit the company's details</p>
            </div>
            
            <!-- Edit Company Form -->
            <form method="POST" action="/companies/update" class="p-6 space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? ''); ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$company->id); ?>">
                
                <!-- Form Grid Layout -->
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Name -->
                    <div class="sm:col-span-3">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Company Name
                        </label>
                        <div class="mt-1">
                            <input type="text" id="name" name="name" 
                                value="<?= htmlspecialchars($_SESSION['form_data']['name'] ?? $company->name ?? ''); ?>" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="sm:col-span-3">
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
                            <input type="email" id="email" name="email" 
                                value="<?= htmlspecialchars($_SESSION['form_data']['email'] ?? $company->email ?? ''); ?>" required
                                class="pl-10 focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="sm:col-span-3">
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone Number
                        </label>
                        <div class="mt-1">
                            <input type="tel" id="phone" name="phone" 
                                value="<?= htmlspecialchars($_SESSION['form_data']['phone'] ?? $company->phone ?? ''); ?>"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        </div>
                    </div>

                    <!-- Website -->
                    <div class="sm:col-span-3">
                        <label for="website" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Website
                        </label>
                        <div class="mt-1">
                            <input type="url" id="website" name="website" 
                                value="<?= htmlspecialchars($_SESSION['form_data']['website'] ?? $company->website ?? ''); ?>"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"
                                placeholder="https://example.com">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="sm:col-span-6">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Address
                        </label>
                        <div class="mt-1">
                            <textarea id="address" name="address" rows="3"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full px-3 py-2 sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"><?= htmlspecialchars($_SESSION['form_data']['address'] ?? $company->address ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-end">
                        <a href="/companies/view/<?= htmlspecialchars((string)$company->id) ?>" class="bg-white dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Company Users</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage users associated with this company</p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- User assignments would go here -->
                <div class="bg-gray-50 dark:bg-gray-700 shadow-sm rounded-lg p-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-2">Current Users</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Users associated with this company.
                    </p>
                    
                    <?php if (isset($users) && !empty($users)): ?>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($users as $user): ?>
                                <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                    <?= htmlspecialchars($user->first_name . ' ' . $user->last_name) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No users assigned to this company yet.</p>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="/users" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Manage Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>
</body>
</html>