<?php
// file: Views/Install/database.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

ob_start();
?>
<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Database</h2>
<p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
    Aureo will connect with these credentials and create the database if it does not already exist.
</p>

<?php if (!empty($error)): ?>
    <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
        <p class="text-sm text-red-700 dark:text-red-200"><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars($assetBase) ?>/install/database" class="space-y-4">
    <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div>
        <label for="db_host" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Host</label>
        <input type="text" id="db_host" name="db_host" required value="<?= htmlspecialchars($dbHost ?? 'localhost:3306') ?>"
               placeholder="localhost:3306"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
    </div>

    <div>
        <label for="db_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Database name</label>
        <input type="text" id="db_name" name="db_name" required value="<?= htmlspecialchars($dbName ?? '') ?>"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
    </div>

    <div>
        <label for="db_user" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <input type="text" id="db_user" name="db_user" required value="<?= htmlspecialchars($dbUser ?? '') ?>"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
    </div>

    <div>
        <label for="db_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" id="db_password" name="db_password" autocomplete="new-password"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
    </div>

    <div class="text-right">
        <button type="submit"
                class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200">
            Continue
        </button>
    </div>
</form>
<?php
$content = ob_get_clean();
include BASE_PATH . '/../src/Views/Install/layout.php';
