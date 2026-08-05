<?php
//file: Views/Templates/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Parse markdown content for display
function parseMarkdown($content)
{
    // Use security service for HTML sanitization
    try {
        $securityService = \App\Services\SecurityService::getInstance();

        // Apply HTML sanitization based on security settings
        $settingsService = \App\Services\SettingsService::getInstance();
        if ($settingsService->isSecurityFeatureEnabled('html_sanitization')) {
            $content = $securityService->sanitizeRichContent($content);
        } else {
            // Fallback to basic sanitization
            $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    } catch (\Exception $e) {
        // Fallback to basic sanitization if security service fails
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Headers (only if content is already sanitized)
    $content = preg_replace('/^### (.*$)/m', '<h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-4 mb-2">$1</h3>', $content);
    $content = preg_replace('/^## (.*$)/m', '<h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mt-6 mb-3">$1</h2>', $content);
    $content = preg_replace('/^# (.*$)/m', '<h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-6 mb-4">$1</h1>', $content);

    // Bold and italic
    $content = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold">$1</strong>', $content);
    $content = preg_replace('/\*(.*?)\*/', '<em class="italic">$1</em>', $content);

    // Lists
    $content = preg_replace('/^- (.*$)/m', '<li class="ml-4">• $1</li>', $content);

    // Line breaks
    $content = nl2br($content);

    return $content;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template: <?= htmlspecialchars($template->name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Breadcrumb -->
        <nav class="flex mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/templates" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        All Templates
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">
                            Template #<?= $template->id ?>
                            <button onclick="copyToClipboard(window.location.href)" class="ml-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Copy page URL">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="pb-6 flex justify-between items-start">
            <div class="flex-1">
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= htmlspecialchars($template->name) ?>
                    </h1>
                    <?php if ($template->is_default): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            Default
                        </span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <?= ucfirst($template->template_type) ?> Template
                    </span>
                    <span class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Created <?= date('M j, Y', strtotime($template->created_at)) ?>
                    </span>
                    <?php if ($template->updated_at && $template->updated_at !== $template->created_at): ?>
                        <span class="inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Updated <?= date('M j, Y', strtotime($template->updated_at)) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Action Buttons -->
            <div class="flex items-center space-x-3">
                <a href="/templates/edit/<?= $template->id ?>" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Template
                </a>
                <a href="/templates" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Back to Templates
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Template Content -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Template Content</h2>
                    <div class="prose prose-sm max-w-none dark:prose-invert">
                        <?= parseMarkdown($template->description) ?>
                    </div>
                </div>
            </div>

            <!-- Template Details -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Template Details</h2>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Template Type</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    <?= ucfirst($template->template_type) ?>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Company</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <?= $template->company_id ? 'Company Specific' : 'Global (All Companies)' ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Default Template</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <?= $template->is_default ? 'Yes' : 'No' ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <?= date('F j, Y \a\t g:i A', strtotime($template->created_at)) ?>
                            </dd>
                        </div>
                        <?php if ($template->updated_at && $template->updated_at !== $template->created_at): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <?php
                                $settingsService = \App\Services\SettingsService::getInstance();
                            $timezone = $settingsService->getDefaultTimezone();

                            try {
                                $date = new DateTime($template->updated_at);
                                $date->setTimezone(new DateTimeZone($timezone));
                                echo $date->format('F j, Y \a\t g:i A');
                            } catch (Exception $e) {
                                echo date('F j, Y \a\t g:i A', strtotime($template->updated_at));
                            }
                            ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show a temporary success message
                const button = event.target.closest('button');
                const originalTitle = button.title;
                button.title = 'Copied!';
                setTimeout(() => {
                    button.title = originalTitle;
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
</body>
</html>
