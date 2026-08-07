<?php
// file: Views/Install/layout.php
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

// This chrome intentionally does not use asset() or Config::get(): neither
// exists yet at this point in the boot sequence (see InstallController's
// class docblock). $assetBase and $content are supplied by the step view
// that includes this file.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aureo Installer</title>
    <link href="<?= htmlspecialchars($assetBase) ?>/assets/css/styles.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <header class="bg-indigo-600 text-white shadow-md">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-3">
            <h1 class="text-lg font-bold">Aureo Installer</h1>
        </div>
    </header>

    <?php if (isset($steps, $currentStep)): ?>
        <nav class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 pt-6">
            <ol class="flex flex-wrap gap-2 text-xs sm:text-sm max-w-xl mx-auto">
                <?php foreach ($steps as $stepName): ?>
                    <li class="px-3 py-1 rounded-full <?= $stepName === $currentStep ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' ?>">
                        <?= htmlspecialchars(ucfirst($stepName)) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <main class="w-full grow flex items-start justify-center py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-xl w-full bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 sm:p-8">
                <?= $content ?? '' ?>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-4 mt-auto">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 text-center text-sm">
            Aureo Installer
        </div>
    </footer>
</body>

</html>
