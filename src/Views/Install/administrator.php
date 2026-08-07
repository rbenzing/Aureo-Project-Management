<?php
// file: Views/Install/administrator.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

ob_start();
?>
<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Administrator account</h2>
<p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
    This account replaces the seeded default administrator and its default password.
</p>

<?php if (!empty($error)): ?>
    <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
        <p class="text-sm text-red-700 dark:text-red-200"><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars($assetBase) ?>/install/administrator" class="space-y-4">
    <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
        <input type="email" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($email ?? '') ?>"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First name</label>
            <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($firstName ?? '') ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
        </div>
        <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last name</label>
            <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($lastName ?? '') ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
        </div>
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">At least 12 chars, 1 uppercase, 1 lowercase, 1 number.</p>
    </div>

    <div>
        <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"
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
