<?php
// file: Views/Install/preflight.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

$severityClasses = [
    'pass' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
    'warn' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200',
    'fail' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
];

ob_start();
?>
<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Environment check</h2>
<p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
    Before anything else, Aureo checks that this host can actually run it.
</p>

<ul class="space-y-3 mb-6">
    <?php foreach ($checks as $check): ?>
        <li class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
            <div class="flex items-center justify-between">
                <span class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($check['label']) ?></span>
                <span class="text-xs font-semibold uppercase px-2 py-1 rounded-full <?= $severityClasses[$check['severity']] ?? '' ?>">
                    <?= htmlspecialchars($check['severity']) ?>
                </span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1"><?= htmlspecialchars($check['detail']) ?></p>
            <?php if ($check['remedy'] !== ''): ?>
                <p class="text-sm text-indigo-600 dark:text-indigo-400 mt-1"><?= htmlspecialchars($check['remedy']) ?></p>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($blocked): ?>
    <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
        <p class="text-sm text-red-700 dark:text-red-200">
            Resolve the failures above, then reload this page.
        </p>
    </div>
<?php else: ?>
    <div class="text-right">
        <a href="<?= htmlspecialchars($assetBase) ?>/install/exposure"
           class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200">
            Continue
        </a>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include BASE_PATH . '/../src/Views/Install/layout.php';
