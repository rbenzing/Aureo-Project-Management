<?php
// file: Views/Install/settings.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

ob_start();
?>
<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Site settings</h2>
<p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
    These become the application's defaults; every one of them can be changed later from Settings.
</p>

<?php if (!empty($error)): ?>
    <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
        <p class="text-sm text-red-700 dark:text-red-200"><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars($assetBase) ?>/install/settings" class="space-y-4">
    <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div>
        <label for="domain" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
        <input type="text" id="domain" name="domain" required value="<?= htmlspecialchars($domain ?? '') ?>"
               placeholder="example.com"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
    </div>

    <div>
        <label for="scheme" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Scheme</label>
        <select id="scheme" name="scheme"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
            <option value="https" <?= ($scheme ?? 'https') === 'https' ? 'selected' : '' ?>>https</option>
            <option value="http" <?= ($scheme ?? 'https') === 'http' ? 'selected' : '' ?>>http</option>
        </select>
    </div>

    <div>
        <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
        <select id="timezone" name="timezone"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
            <?php foreach (\DateTimeZone::listIdentifiers() as $identifier): ?>
                <option value="<?= htmlspecialchars($identifier) ?>" <?= ($timezone ?? 'UTC') === $identifier ? 'selected' : '' ?>>
                    <?= htmlspecialchars($identifier) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="company" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company name</label>
        <input type="text" id="company" name="company" value="<?= htmlspecialchars($company ?? '') ?>"
               placeholder="Aureo"
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
