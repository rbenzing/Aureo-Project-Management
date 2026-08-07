<?php
// file: Views/Install/refused.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

ob_start();
?>
<h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Installer unavailable</h2>
<div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-500 p-4 rounded">
    <p class="text-sm text-red-700 dark:text-red-200"><?= htmlspecialchars($reason) ?></p>
</div>
<?php
$content = ob_get_clean();
include BASE_PATH . '/../src/Views/Install/layout.php';
