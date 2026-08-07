<?php
// file: Views/Install/exposure.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

ob_start();
?>
<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Exposure self-test</h2>
<p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
    Aureo asked this server, over loopback HTTP, whether it will hand out files that must never be
    served publicly.
</p>

<?php if ($safe !== []): ?>
    <div class="mb-4 bg-green-50 dark:bg-green-900/50 border-l-4 border-green-500 p-4 rounded">
        <p class="text-sm font-medium text-green-700 dark:text-green-200 mb-1">Not publicly readable:</p>
        <ul class="text-sm text-green-700 dark:text-green-200 list-disc list-inside">
            <?php foreach ($safe as $path): ?>
                <li><?= htmlspecialchars($path) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($exposed !== []): ?>
    <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
        <p class="text-sm font-medium text-red-700 dark:text-red-200 mb-1">Publicly readable - installation is blocked:</p>
        <ul class="text-sm text-red-700 dark:text-red-200 list-disc list-inside">
            <?php foreach ($exposed as $path): ?>
                <li><?= htmlspecialchars($path) ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="text-sm text-red-700 dark:text-red-200 mt-2">
            Fix your server's deny rules for these paths (see docs/DEPLOYMENT.md), then reload this
            page. No acknowledgement can override this.
        </p>
    </div>
<?php endif; ?>

<?php if ($unreachable !== []): ?>
    <div class="mb-4 bg-yellow-50 dark:bg-yellow-900/50 border-l-4 border-yellow-500 p-4 rounded">
        <p class="text-sm font-medium text-yellow-700 dark:text-yellow-200 mb-1">Could not be checked:</p>
        <ul class="text-sm text-yellow-700 dark:text-yellow-200 list-disc list-inside">
            <?php foreach ($unreachable as $path): ?>
                <li><?= htmlspecialchars($path) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$blocked): ?>
    <form method="POST" action="<?= htmlspecialchars($assetBase) ?>/install/exposure" class="space-y-4">
        <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <?php if ($needsAcknowledgement): ?>
            <div class="flex items-start">
                <input id="acknowledge" name="acknowledge" type="checkbox" value="1"
                       class="h-4 w-4 mt-1 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="acknowledge" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                    I have manually confirmed the paths above are not publicly readable, and I want to
                    continue anyway.
                </label>
            </div>
        <?php endif; ?>

        <div class="text-right">
            <button type="submit"
                    class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200">
                Continue
            </button>
        </div>
    </form>
<?php endif; ?>
<?php
$content = ob_get_clean();
include BASE_PATH . '/../src/Views/Install/layout.php';
