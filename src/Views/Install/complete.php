<?php
// file: Views/Install/complete.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

ob_start();
?>
<?php if ($done): ?>
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Installation complete</h2>
    <div class="mb-4 bg-green-50 dark:bg-green-900/50 border-l-4 border-green-500 p-4 rounded">
        <p class="text-sm text-green-700 dark:text-green-200">
            Aureo is configured and the installer is now disabled. Sign in with the administrator
            account you just created.
        </p>
    </div>
    <div class="text-right">
        <a href="<?= htmlspecialchars($loginUrl) ?>"
           class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200">
            Go to login
        </a>
    </div>
<?php else: ?>
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Finish installation</h2>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Aureo will now run its database migrations, create the administrator account, and write the
        configuration file. This cannot be undone from this page.
    </p>

    <?php if (!empty($error)): ?>
        <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
            <p class="text-sm text-red-700 dark:text-red-200"><?= htmlspecialchars($error) ?></p>
            <p class="text-sm text-red-700 dark:text-red-200 mt-2">
                Nothing has been written yet. Correct the problem and try again.
            </p>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($assetBase) ?>/install/complete">
        <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="text-right">
            <button type="submit"
                    class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200">
                Install
            </button>
        </div>
    </form>
<?php endif; ?>
<?php
$content = ob_get_clean();
include BASE_PATH . '/../src/Views/Install/layout.php';
