<?php
//file: Views/Projects/edit.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Get form data from session if validation failed previously
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
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

        <div class="pb-6 flex justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Edit Project</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update project details and settings</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="/projects/view/<?= $project->id ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600 dark:hover:bg-gray-600">
                    Cancel
                </a>
                <button type="submit" form="editProjectForm" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Save Changes
                </button>
            </div>
        </div>

        <!-- Edit Project Form -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <form id="editProjectForm" method="POST" action="/projects/update" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo $project->id; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project Name <span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" required
                                value="<?php echo htmlspecialchars($formData['name'] ?? $project->name); ?>"
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <!-- Company -->
                        <div>
                            <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company <span class="text-red-500">*</span></label>
                            <select id="company_id" name="company_id" required
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Select a company</option>
                                <?php foreach ($companies['records'] as $company): ?>
                                    <option value="<?php echo $company->id; ?>"
                                        <?php echo (isset($formData['company_id']) ? $formData['company_id'] : $project->company_id) == $company->id ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status <span class="text-red-500">*</span></label>
                            <select id="status_id" name="status_id" required
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Select a status</option>
                                <?php foreach ($statuses as $status): ?>
                                    <?php $statusInfo = getProjectStatusInfo($status->id); ?>
                                    <option value="<?php echo $status->id; ?>"
                                        <?php echo (isset($formData['status_id']) ? $formData['status_id'] : $project->status_id) == $status->id ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Start Date -->
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                            <input type="date" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($formData['start_date'] ?? $project->start_date ?? ''); ?>"
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <!-- End Date -->
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expected End Date</label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($formData['end_date'] ?? $project->end_date ?? ''); ?>"
                                class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>
                </div>

                <!-- Template Selection -->
                <div>
                    <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description Template</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <select id="template_id" name="template_id"
                            class="block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Select a template or keep current description</option>
                            <?php
                            // Load templates
                            if (isset($templates) && !empty($templates)) {
                                foreach ($templates as $template) {
                                    echo '<option value="' . $template->id . '"';
                                    if (isset($formData['template_id']) && $formData['template_id'] == $template->id) {
                                        echo ' selected';
                                    }
                                    echo '>' . htmlspecialchars($template->name) . '</option>';
                                }
                            }
?>
                        </select>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Selecting a template will replace your current description</p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="description" name="description" rows="6"
                        class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo htmlspecialchars($formData['description'] ?? $project->description ?? ''); ?></textarea>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <?php if (isset($currentUser['permissions']) && in_array('delete_projects', $currentUser['permissions'])): ?>
            <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-red-600 dark:text-red-400">Danger Zone</h3>
                <div class="mt-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">Delete this project</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Once deleted, this project and all its data will be permanently removed.</p>
                    </div>
                    <form method="POST" action="/projects/delete/<?php echo $project->id; ?>" onsubmit="return confirm('Are you sure you want to delete this project? This action cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            Delete Project
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script>
        // Date validation
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            function validateDates() {
                if (startDateInput.value && endDateInput.value) {
                    if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                        alert('End date cannot be earlier than start date');
                        endDateInput.value = '';
                        return false;
                    }
                }
                return true;
            }

            endDateInput.addEventListener('change', validateDates);

            // Also validate before form submission
            document.getElementById('editProjectForm').addEventListener('submit', function(e) {
                if (!validateDates()) {
                    e.preventDefault();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const templateSelect = document.getElementById('template_id');
            const descriptionTextarea = document.getElementById('description');

            if (templateSelect && descriptionTextarea) {
                templateSelect.addEventListener('change', function() {
                    const templateId = this.value;

                    // If no template selected, do nothing
                    if (!templateId) {
                        return;
                    }

                    // If description already has content, confirm before overwriting
                    if (descriptionTextarea.value.trim() !== '' &&
                        !confirm('This will replace your current description. Continue?')) {
                        // Reset the select if user cancels
                        templateSelect.value = '';
                        return;
                    }

                    // Fetch template content
                    fetch(`/project-templates/get/${templateId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.template) {
                                // Convert literal \n characters to actual line breaks
                                descriptionTextarea.value = data.template.description.replace(/\\n/g, '\n');
                            } else {
                                console.error('Error loading template:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching template:', error);
                        });
                });
            }
        });
    </script>
</body>

</html>