<?php
//file: Views/Templates/edit.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Models\Template;

// Include form components
require_once BASE_PATH . '/../src/Views/Layouts/FormComponents.php';

// Load form data from session if available (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Load errors from session if available
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Template: <?= htmlspecialchars($template->name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>

        <!-- Tips Box (movable above page title) -->
        <div id="tips-box" class="bg-indigo-50 dark:bg-indigo-900 rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-indigo-800 dark:text-indigo-300">Template editing tips</h3>
                        <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-200">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Use markdown formatting for better structure</li>
                                <li>Include placeholders for dynamic content</li>
                                <li>Test your template before setting as default</li>
                                <li>Consider your team's workflow when creating templates</li>
                                <li>Use the markdown buttons below for quick formatting</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <button type="button" id="close-tips" class="text-indigo-400 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Page Header -->
        <div class="pb-6 flex justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Template</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Update the template "<?= htmlspecialchars($template->name) ?>"
                </p>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="/templates/view/<?= $template->id ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" form="editTemplateForm"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Template
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Form -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <form id="editTemplateForm" method="POST" action="/templates/update" class="space-y-6">
                        <!-- CSRF Token -->
                        <?= renderCSRFToken() ?>
                        <input type="hidden" name="id" value="<?= $template->id ?>">

                        <!-- Template Name -->
                        <?= renderTextInput([
                            'name' => 'name',
                            'label' => 'Template Name',
                            'value' => $formData['name'] ?? $template->name,
                            'required' => true,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
                            'error' => $errors['name'] ?? '',
                        ]) ?>

                        <!-- Template Type -->
                        <?= renderSelect([
                            'name' => 'template_type',
                            'label' => 'Template Type',
                            'value' => $formData['template_type'] ?? $template->template_type,
                            'options' => Template::TEMPLATE_TYPES,
                            'required' => true,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
                            'help_text' => 'Choose what type of content this template will be used for',
                            'error' => $errors['template_type'] ?? '',
                        ]) ?>

                        <!-- Company Selection -->
                        <?php
                        $companyOptions = ['' => 'Global (available to all companies)'];
foreach ($companies as $company) {
    $companyOptions[$company->id] = $company->name;
}
?>
                        <?= renderSelect([
    'name' => 'company_id',
    'label' => 'Company <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>',
    'value' => $formData['company_id'] ?? $template->company_id,
    'options' => $companyOptions,
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
    'help_text' => 'If selected, this template will only be available to this company',
    'error' => $errors['company_id'] ?? '',
]) ?>

                        <!-- Default Template Checkbox -->
                        <?= renderCheckbox([
    'name' => 'is_default',
    'label' => 'Set as Default Template',
    'checked' => (isset($formData['is_default']) && $formData['is_default']) || $template->is_default,
    'help_text' => 'Make this the default template for this type',
    'error' => $errors['is_default'] ?? '',
]) ?>

                        <!-- Markdown Editor Toolbar -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Template Content <span class="text-red-500">*</span>
                            </label>
                            
                            <!-- Markdown Toolbar -->
                            <div class="flex flex-wrap gap-1 mb-2 p-2 bg-gray-50 dark:bg-gray-700 rounded-t-md border border-gray-300 dark:border-gray-600">
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="bold" title="Bold">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 011-1h4.5a3.5 3.5 0 013.5 3.5v.5a3 3 0 01-1.5 2.6A3 3 0 0112 12v.5a3.5 3.5 0 01-3.5 3.5H4a1 1 0 01-1-1V4zm2 1v4h3.5a1.5 1.5 0 000-3H5zm0 6v4h3.5a1.5 1.5 0 000-3H5z"/>
                                    </svg>
                                </button>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="italic" title="Italic">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9.5L7 16h1.5a1 1 0 110 2H6a1 1 0 110-2h1.5L10 4H8a1 1 0 01-1-1z"/>
                                    </svg>
                                </button>
                                <div class="w-px h-8 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="h1" title="Heading 1">
                                    <span class="text-sm font-bold">H1</span>
                                </button>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="h2" title="Heading 2">
                                    <span class="text-sm font-bold">H2</span>
                                </button>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="h3" title="Heading 3">
                                    <span class="text-sm font-bold">H3</span>
                                </button>
                                <div class="w-px h-8 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="ul" title="Bullet List">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 000 2h1a1 1 0 000-2H3zM3 8a1 1 0 000 2h1a1 1 0 000-2H3zM3 12a1 1 0 100 2h1a1 1 0 100-2H3zM7 4a1 1 0 000 2h10a1 1 0 100-2H7zM7 8a1 1 0 000 2h10a1 1 0 100-2H7zM7 12a1 1 0 100 2h10a1 1 0 100-2H7z"/>
                                    </svg>
                                </button>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="ol" title="Numbered List">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 000 2h1a1 1 0 000-2H3zM3 8a1 1 0 000 2h1a1 1 0 000-2H3zM3 12a1 1 0 100 2h1a1 1 0 100-2H3zM7 4a1 1 0 000 2h10a1 1 0 100-2H7zM7 8a1 1 0 000 2h10a1 1 0 100-2H7zM7 12a1 1 0 100 2h10a1 1 0 100-2H7z"/>
                                    </svg>
                                </button>
                                <div class="w-px h-8 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="link" title="Link">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                    </svg>
                                </button>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="code" title="Code">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                    </svg>
                                </button>
                                <button type="button" class="markdown-btn w-10 h-10 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" data-action="quote" title="Quote">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Textarea -->
                            <textarea id="description" name="description" rows="15" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-b-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm font-mono"><?= htmlspecialchars($formData['description'] ?? $template->description) ?></textarea>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use the toolbar buttons above for quick markdown formatting. Markdown is supported for better structure and formatting.</p>
                            <?php if (!empty($errors['description'])): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= htmlspecialchars($errors['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Preview Container -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Live Preview</h3>
                    <div id="preview-content" class="prose prose-sm max-w-none dark:prose-invert">
                        <!-- Preview will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('description');
            const previewContent = document.getElementById('preview-content');
            const markdownButtons = document.querySelectorAll('.markdown-btn');
            const closeTipsBtn = document.getElementById('close-tips');
            const tipsBox = document.getElementById('tips-box');

            // Close tips functionality
            if (closeTipsBtn && tipsBox) {
                closeTipsBtn.addEventListener('click', function() {
                    tipsBox.style.display = 'none';
                });
            }

            // Markdown button actions
            const markdownActions = {
                bold: (text) => `**${text}**`,
                italic: (text) => `*${text}*`,
                h1: (text) => `# ${text}`,
                h2: (text) => `## ${text}`,
                h3: (text) => `### ${text}`,
                ul: (text) => `- ${text}`,
                ol: (text) => `1. ${text}`,
                link: (text) => `[${text}](url)`,
                code: (text) => `\`${text}\``,
                quote: (text) => `> ${text}`
            };

            // Add click handlers to markdown buttons
            markdownButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const selectedText = textarea.value.substring(start, end) || 'text';

                    if (markdownActions[action]) {
                        const formattedText = markdownActions[action](selectedText);
                        textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);

                        // Update cursor position
                        const newCursorPos = start + formattedText.length;
                        textarea.setSelectionRange(newCursorPos, newCursorPos);
                        textarea.focus();

                        // Update preview
                        updatePreview();
                    }
                });
            });

            // Simple markdown to HTML converter for preview
            function markdownToHtml(markdown) {
                let html = markdown;

                // Escape HTML
                html = html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                // Headers
                html = html.replace(/^### (.*$)/gm, '<h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-4 mb-2">$1</h3>');
                html = html.replace(/^## (.*$)/gm, '<h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mt-6 mb-3">$1</h2>');
                html = html.replace(/^# (.*$)/gm, '<h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-6 mb-4">$1</h1>');

                // Bold and italic
                html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold">$1</strong>');
                html = html.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');

                // Code
                html = html.replace(/`(.*?)`/g, '<code class="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-sm">$1</code>');

                // Links
                html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="text-indigo-600 dark:text-indigo-400 hover:underline">$1</a>');

                // Lists
                html = html.replace(/^- (.*$)/gm, '<li class="ml-4 list-disc">$1</li>');
                html = html.replace(/^(\d+)\. (.*$)/gm, '<li class="ml-4 list-decimal">$2</li>');

                // Quotes
                html = html.replace(/^> (.*$)/gm, '<blockquote class="border-l-4 border-gray-300 dark:border-gray-600 pl-4 italic text-gray-600 dark:text-gray-400">$1</blockquote>');

                // Line breaks
                html = html.replace(/\n/g, '<br>');

                return html;
            }

            // Update preview
            function updatePreview() {
                const markdown = textarea.value;
                const html = markdownToHtml(markdown);
                previewContent.innerHTML = html || '<p class="text-gray-500 dark:text-gray-400 italic">Preview will appear here as you type...</p>';
            }

            // Initial preview update
            updatePreview();

            // Update preview on textarea change
            textarea.addEventListener('input', updatePreview);
        });
    </script>
</body>
</html>
