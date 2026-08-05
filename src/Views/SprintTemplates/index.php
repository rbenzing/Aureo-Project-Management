<?php
// file: Views/SprintTemplates/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprint Templates - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Breadcrumb -->
        <nav class="flex mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/dashboard" class="text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-gray-500 dark:text-gray-400">Sprint Templates</span>
                    </div>
                </li>
            </ol>
            <div class="ml-auto">
                <a href="/sprint-templates/create" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    New Template
                </a>
            </div>
        </nav>

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center">
                <div class="h-10 w-10 rounded-lg bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center mr-4">
                    <svg class="h-6 w-6 text-indigo-500 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sprint Templates</h1>
                    <p class="text-gray-600 dark:text-gray-400">Manage reusable sprint configurations and settings</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-64">
                    <label for="project_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Project</label>
                    <select id="project_filter" name="project_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white">
                        <option value="">All Projects</option>
                        <?php foreach ($projects ?? [] as $project): ?>
                            <option value="<?= $project->id ?>" <?= ($_GET['project_id'] ?? '') == $project->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Templates Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (!empty($templates)): ?>
                <?php foreach ($templates as $template): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                        <div class="p-6">
                            <!-- Template Header -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                        <?= htmlspecialchars($template->name) ?>
                                        <?php if ($template->is_default): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 ml-2">
                                                Default
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                        <?= htmlspecialchars($template->description) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Template Configuration -->
                            <div class="space-y-3 mb-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Sprint Length:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?= $template->sprint_length ?? 2 ?> weeks
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Estimation:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?= ucfirst($template->estimation_method ?? 'hours') ?>
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Default Capacity:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?= $template->default_capacity ?? 40 ?>
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Auto-assign Subtasks:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?= $template->auto_assign_subtasks ? 'Yes' : 'No' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Ceremony Settings -->
                            <?php if (!empty($template->ceremony_settings)): ?>
                                <?php
                                // Handle both array and JSON string formats
                                if (is_array($template->ceremony_settings)) {
                                    $ceremonies = $template->ceremony_settings;
                                } else {
                                    $ceremonies = json_decode($template->ceremony_settings, true);
                                }
                    ?>
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SCRUM Ceremonies</h4>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($ceremonies as $ceremony => $settings): ?>
                                            <?php if ($settings['enabled'] ?? false): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <?= ucfirst(str_replace('_', ' ', $ceremony)) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex space-x-2">
                                    <a href="/sprint-templates/edit/<?= $template->id ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        Edit
                                    </a>
                                    <button onclick="applyTemplate(<?= $template->id ?>)" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">
                                        Use Template
                                    </button>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <?= date('M j, Y', strtotime($template->created_at)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No sprint templates</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first sprint template.</p>
                        <div class="mt-6">
                            <a href="/sprint-templates/create" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                New Sprint Template
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- Project Selection Modal -->
    <div id="projectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Project</h3>
                <select id="projectSelect" class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white mb-4">
                    <option value="">Choose a project...</option>
                    <?php foreach ($projects ?? [] as $project): ?>
                        <option value="<?= $project->id ?>">
                            <?= htmlspecialchars($project->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="flex justify-end space-x-3">
                    <button onclick="closeProjectModal()" class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-400 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button onclick="confirmApplyTemplate()" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Apply Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedTemplateId = null;

        function applyTemplate(templateId) {
            selectedTemplateId = templateId;
            document.getElementById('projectModal').classList.remove('hidden');
        }

        function closeProjectModal() {
            document.getElementById('projectModal').classList.add('hidden');
            selectedTemplateId = null;
        }

        function confirmApplyTemplate() {
            const projectId = document.getElementById('projectSelect').value;
            if (!projectId) {
                alert('Please select a project');
                return;
            }

            window.location.href = `/sprint-templates/apply?template_id=${selectedTemplateId}&project_id=${projectId}`;
        }

        // Close modal when clicking outside
        document.getElementById('projectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProjectModal();
            }
        });
    </script>
</body>
</html>
