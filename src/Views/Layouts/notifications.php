<?php
//file: Views/Layouts/notifications.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

if (!empty($error)): ?>
    <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php unset($error); ?>
<?php endif;

if (!empty($success)): ?>
    <div class="bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php unset($success); ?>
<?php endif; ?>