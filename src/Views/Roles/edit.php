<?php
//file: Views/Roles/edit.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include form components
require_once BASE_PATH . '/../src/Views/Layouts/FormComponents.php';

// Fetch organized permissions and templates
$permissionModel = new \App\Models\Permission();
$organizedPermissions = $permissionModel->getOrganizedPermissions();
$roleTemplates = $permissionModel->getRoleTemplates();

// Count total permissions
$totalPermissions = 0;
foreach ($organizedPermissions as $entityData) {
    $totalPermissions += count($entityData['permissions']);
}

// Get current role's permissions
$currentPermissionIds = array_column($role->permissions, 'id');

// Prepare form data from session if exists
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role: <?= htmlspecialchars($role->name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>
    
    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <!-- Notifications -->
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Breadcrumb -->
        <?php echo \App\Utils\Breadcrumb::render('roles/edit', ['id' => $role->id]); ?>

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Edit Role</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Update role details and assigned permissions</p>
            </div>

            <div class="flex space-x-2">
                <a href="/roles/view/<?= htmlspecialchars((string)$role->id) ?>" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View Role
                </a>
                <a href="/roles" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <form method="POST" action="/roles/update">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Main Form -->
                <div class="w-full lg:w-2/3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 space-y-6">
                        <!-- CSRF Token and ID -->
                        <?= renderCSRFToken() ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$role->id); ?>">

                        <!-- Role Name -->
                        <?= renderTextInput([
                            'name' => 'name',
                            'label' => 'Role Name',
                            'value' => $formData['name'] ?? $role->name,
                            'required' => true,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />',
                            'error' => $errors['name'] ?? '',
                        ]) ?>

                        <!-- Description -->
                        <?= renderTextarea([
                            'name' => 'description',
                            'label' => 'Description',
                            'value' => $formData['description'] ?? ($role->description ?? ''),
                            'rows' => 3,
                            'placeholder' => 'Provide a brief description of this role',
                            'error' => $errors['description'] ?? '',
                        ]) ?>

                        <!-- Permissions Section -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Permissions
                                </h3>

                                <!-- Permission Templates -->
                                <div class="flex items-center space-x-3">
                                    <label for="role-template" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Quick Templates:
                                    </label>
                                    <select id="role-template" class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Select a template...</option>
                                        <?php foreach ($roleTemplates as $key => $template): ?>
                                            <option value="<?= htmlspecialchars($key) ?>" data-permissions="<?= htmlspecialchars(json_encode($permissionModel->getTemplatePermissionIds($key))) ?>">
                                                <?= htmlspecialchars($template['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Search and Controls -->
                            <div class="mb-4">
                                <div class="flex flex-col sm:flex-row gap-3 mb-3">
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            id="permission-search"
                                            placeholder="Search permissions..."
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button type="button" id="select-all-permissions" class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            Select All
                                        </button>
                                        <button type="button" id="clear-all-permissions" class="px-3 py-2 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            Clear All
                                        </button>
                                    </div>
                                </div>

                                <!-- Permission Count -->
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span id="selected-count">0</span> of <span id="total-count">0</span> permissions selected
                                </div>
                            </div>

                            <!-- Permissions Grid -->
                            <div class="space-y-4" id="permissions-container">
                                <?php foreach ($organizedPermissions as $entity => $entityData): ?>
                                    <div class="permission-entity border border-gray-200 dark:border-gray-700 rounded-lg" data-entity="<?= htmlspecialchars($entity) ?>">
                                        <!-- Entity Header -->
                                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 mr-3">
                                                        <?php
                                                        $iconName = $entityData['config']['icon'];
                                    $iconPaths = [
                                        'chart-bar' => 'M3 3v18h18M7 12l4-4 4 4 4-4',
                                        'folder' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
                                        'clipboard-list' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                                        'flag' => 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 2H21l-3 6 3 6h-8.5l-1-2H5a2 2 0 00-2 2zm9-13.5V9',
                                        'lightning-bolt' => 'M13 10V3L4 14h7v7l9-11h-7z',
                                        'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                                        'users' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a4 4 0 11-8 0 4 4 0 018 0z',
                                        'shield-check' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                                        'office-building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                                        'template' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                        'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                                        'collection' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                                    ];
                                    $iconPath = $iconPaths[$iconName] ?? $iconPaths['collection'];
                                    ?>
                                                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>"></path>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                            <?= htmlspecialchars($entityData['config']['label']) ?>
                                                        </h4>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            <?= htmlspecialchars($entityData['config']['description']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        id="select-entity-<?= htmlspecialchars($entity) ?>"
                                                        data-entity="<?= htmlspecialchars($entity) ?>"
                                                        class="entity-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded"
                                                    >
                                                    <label for="select-entity-<?= htmlspecialchars($entity) ?>" class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                                        Select All
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Entity Permissions -->
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                <?php foreach ($entityData['permissions'] as $permission): ?>
                                                    <div class="permission-item flex items-start p-3 rounded-md border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700" data-permission-name="<?= htmlspecialchars($permission['name']) ?>">
                                                        <input
                                                            type="checkbox"
                                                            id="permission_<?= htmlspecialchars((string)$permission['id']); ?>"
                                                            name="permissions[]"
                                                            value="<?= htmlspecialchars((string)$permission['id']); ?>"
                                                            data-entity="<?= htmlspecialchars($entity); ?>"
                                                            class="permission-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded mt-0.5"
                                                            <?=
                                            (isset($formData['permissions']) && in_array($permission['id'], $formData['permissions'])) ||
                                            in_array($permission['id'], $currentPermissionIds)
                                            ? 'checked' : '';
                                                    ?>
                                                        >
                                                        <div class="ml-3 flex-1">
                                                            <label
                                                                for="permission_<?= htmlspecialchars((string)$permission['id']); ?>"
                                                                class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer block"
                                                            >
                                                                <?= htmlspecialchars($permission['action_config']['label']); ?>
                                                            </label>
                                                            <?php if ($permission['description']): ?>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($permission['description']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $permission['action_config']['color'] ?>-100 text-<?= $permission['action_config']['color'] ?>-800 dark:bg-<?= $permission['action_config']['color'] ?>-900 dark:text-<?= $permission['action_config']['color'] ?>-200 mt-1">
                                                                Level <?= $permission['action_config']['level'] ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar with Form Actions and Summary -->
                <div class="w-full lg:w-1/3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 space-y-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Role Summary</h3>

                        <!-- Permission Summary -->
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Selected Permissions:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100" id="summary-selected-count">0</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Total Available:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100" id="summary-total-count">0</span>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <?= renderFormButtons([
                            'submit_text' => 'Update Role',
                            'cancel_url' => '/roles/view/' . $role->id,
                            'show_cancel' => true,
                        ]) ?>
                    </div>
                </div>
            </div>
        </form>


    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>
    
    <!-- JavaScript for enhanced permission selection -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllBtn = document.getElementById('select-all-permissions');
            const clearAllBtn = document.getElementById('clear-all-permissions');
            const entityCheckboxes = document.querySelectorAll('.entity-checkbox');
            const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
            const searchInput = document.getElementById('permission-search');
            const roleTemplateSelect = document.getElementById('role-template');
            const selectedCountSpan = document.getElementById('selected-count');
            const totalCountSpan = document.getElementById('total-count');
            const summarySelectedCountSpan = document.getElementById('summary-selected-count');
            const summaryTotalCountSpan = document.getElementById('summary-total-count');

            // Initialize counts
            updatePermissionCounts();

            // Select all permissions
            selectAllBtn.addEventListener('click', function() {
                permissionCheckboxes.forEach(checkbox => {
                    if (isCheckboxVisible(checkbox)) {
                        checkbox.checked = true;
                    }
                });
                updateEntityCheckboxes();
                updatePermissionCounts();
            });

            // Clear all permissions
            clearAllBtn.addEventListener('click', function() {
                permissionCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                entityCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                updatePermissionCounts();
            });

            // Entity checkbox handling
            entityCheckboxes.forEach(entityCheckbox => {
                entityCheckbox.addEventListener('change', function() {
                    const entity = this.dataset.entity;
                    const isChecked = this.checked;

                    document.querySelectorAll(`.permission-checkbox[data-entity="${entity}"]`).forEach(checkbox => {
                        if (isCheckboxVisible(checkbox)) {
                            checkbox.checked = isChecked;
                        }
                    });

                    updatePermissionCounts();
                });
            });

            // Individual permission checkbox handling
            permissionCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateEntityCheckboxes();
                    updatePermissionCounts();
                });
            });

            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterPermissions(searchTerm);
                updateEntityCheckboxes();
                updatePermissionCounts();
            });

            // Role template selection
            roleTemplateSelect.addEventListener('change', function() {
                if (this.value) {
                    const permissionIds = JSON.parse(this.options[this.selectedIndex].dataset.permissions || '[]');

                    // Clear all first
                    permissionCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    // Select template permissions
                    permissionIds.forEach(id => {
                        const checkbox = document.querySelector(`input[value="${id}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });

                    updateEntityCheckboxes();
                    updatePermissionCounts();

                    // Reset template selection
                    this.value = '';
                }
            });

            function updateEntityCheckboxes() {
                entityCheckboxes.forEach(entityCheckbox => {
                    const entity = entityCheckbox.dataset.entity;
                    const entityPermissions = document.querySelectorAll(`.permission-checkbox[data-entity="${entity}"]`);
                    const visiblePermissions = Array.from(entityPermissions).filter(isCheckboxVisible);
                    const checkedPermissions = visiblePermissions.filter(checkbox => checkbox.checked);

                    if (visiblePermissions.length === 0) {
                        entityCheckbox.checked = false;
                        entityCheckbox.indeterminate = false;
                    } else if (checkedPermissions.length === visiblePermissions.length) {
                        entityCheckbox.checked = true;
                        entityCheckbox.indeterminate = false;
                    } else if (checkedPermissions.length > 0) {
                        entityCheckbox.checked = false;
                        entityCheckbox.indeterminate = true;
                    } else {
                        entityCheckbox.checked = false;
                        entityCheckbox.indeterminate = false;
                    }
                });
            }

            function updatePermissionCounts() {
                const visiblePermissions = Array.from(permissionCheckboxes).filter(isCheckboxVisible);
                const selectedPermissions = visiblePermissions.filter(checkbox => checkbox.checked);

                // Update main counts
                selectedCountSpan.textContent = selectedPermissions.length;
                totalCountSpan.textContent = visiblePermissions.length;

                // Update summary counts
                summarySelectedCountSpan.textContent = selectedPermissions.length;
                summaryTotalCountSpan.textContent = permissionCheckboxes.length;
            }

            function filterPermissions(searchTerm) {
                const entities = document.querySelectorAll('.permission-entity');

                entities.forEach(entity => {
                    const permissionItems = entity.querySelectorAll('.permission-item');
                    let hasVisiblePermissions = false;

                    permissionItems.forEach(item => {
                        const permissionName = item.dataset.permissionName.toLowerCase();
                        const permissionLabel = item.querySelector('label').textContent.toLowerCase();
                        const isVisible = permissionName.includes(searchTerm) || permissionLabel.includes(searchTerm);

                        item.style.display = isVisible ? 'flex' : 'none';
                        if (isVisible) {
                            hasVisiblePermissions = true;
                        }
                    });

                    // Show/hide entire entity based on whether it has visible permissions
                    entity.style.display = hasVisiblePermissions ? 'block' : 'none';
                });
            }

            function isCheckboxVisible(checkbox) {
                const permissionItem = checkbox.closest('.permission-item');
                const entity = checkbox.closest('.permission-entity');
                return permissionItem && permissionItem.style.display !== 'none' &&
                       entity && entity.style.display !== 'none';
            }

            // Initialize states
            updateEntityCheckboxes();
        });
    </script>
</body>
</html>