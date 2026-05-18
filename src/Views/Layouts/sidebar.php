<?php
//file: Views/Layouts/sidebar.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Include view helpers for permission functions
require_once BASE_PATH . '/../src/views/Layouts/ViewHelpers.php';

// Define menu items with required permissions
$mainItems = [
    [
        'label' => 'Dashboard',
        'path' => '/dashboard',
        'icon' => '🏠',
        'permission' => 'view_dashboard',
    ],
];

$projectItems = [
    [
        'label' => 'Projects',
        'path' => '/projects',
        'icon' => '📋',
        'permission' => 'view_projects',
    ],
    [
        'label' => 'Sprints',
        'path' => '/sprints',
        'icon' => '🏃',
        'permission' => 'view_sprints',
    ],
    [
        'label' => 'Milestones',
        'path' => '/milestones',
        'icon' => '📍',
        'permission' => 'view_milestones',
    ],
];

$taskItems = [
    [
        'label' => 'All Tasks',
        'path' => '/tasks',
        'icon' => '📝',
        'permission' => 'view_tasks',
    ],
    [
        'label' => 'Backlog',
        'path' => '/tasks/backlog',
        'icon' => '📋',
        'permission' => 'view_tasks',
    ],
    [
        'label' => 'My Tasks',
        'path' => '/tasks/assigned/' . $currentUser['profile']['id'],
        'icon' => '📌',
        'permission' => 'view_tasks',
    ],
    [
        'label' => 'Sprint Planning',
        'path' => '/tasks/sprint-planning',
        'icon' => '🎯',
        'permission' => 'view_tasks',
    ],
];

$timeTrackingItems = [
    [
        'label' => 'Time Tracking',
        'path' => '/time-tracking',
        'icon' => '⏱️',
        'permission' => 'view_time_tracking',
    ],
];

$adminItems = [
    [
        'label' => 'Templates',
        'path' => '/templates',
        'icon' => '📝',
        'permission' => 'view_templates',
    ],
    [
        'label' => 'Sprint Templates',
        'path' => '/sprint-templates',
        'icon' => '🏃',
        'permission' => 'view_templates',
    ],
    [
        'label' => 'Companies',
        'path' => '/companies',
        'icon' => '🏢',
        'permission' => 'view_companies',
    ],
    [
        'label' => 'Users',
        'path' => '/users',
        'icon' => '👥',
        'permission' => 'view_users',
    ],
    [
        'label' => 'Roles',
        'path' => '/roles',
        'icon' => '🔑',
        'permission' => 'view_roles',
    ],
    [
        'label' => 'Settings',
        'path' => '/settings',
        'icon' => '⚙️',
        'permission' => 'view_settings',
    ],
];

// Function to check if the user has permission
function hasPermission($permission)
{
    if (empty($permission)) {
        return true; // No permission required
    }

    // Get user permissions from session
    $userPermissions = $_SESSION['user']['permissions'] ?? [];

    return in_array($permission, $userPermissions, true);
}

// Function to filter menu items by permission
function filterMenuByPermission($items)
{
    return array_filter($items, function ($item) {
        return hasPermission($item['permission'] ?? '');
    });
}

// Filter menu items
$mainItems = filterMenuByPermission($mainItems);
$projectItems = filterMenuByPermission($projectItems);
$taskItems = filterMenuByPermission($taskItems);
$timeTrackingItems = filterMenuByPermission($timeTrackingItems);
$adminItems = filterMenuByPermission($adminItems);

// Get the current path
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<aside class="bg-gray-800 text-white w-64 h-full min-h-screen shadow-lg fixed top-0 left-0 bottom-0 z-20 transition-transform duration-300 ease-in-out transform -translate-x-full flex flex-col" id="sidebar" aria-label="Sidebar">
    <!-- Sidebar Header -->
    <div class="flex-shrink-0 p-4 border-b border-gray-700">
        <div class="flex flex-row justify-between items-center">
            <h2 class="text-lg font-bold">Menu</h2>
            <!-- Close Button -->
            <button id="sidebar-close" class="block bg-none w-4 h-4 text-white hover:text-gray-300 focus:outline-none" aria-label="Close Sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Scrollable Navigation Area -->
    <div class="flex-1 overflow-y-auto p-4">
        <!-- Main Navigation -->
        <nav class="space-y-4">

            <!-- Main Items -->
            <?php if (!empty($mainItems)): ?>
                <div>
                    <ul class="space-y-1">
                        <?php
                        foreach ($mainItems as $item) {
                            $isActive = $currentPath === $item['path'] ? 'bg-indigo-600' : '';
                            echo '<li>';
                            echo '<a href="' . htmlspecialchars($item['path']) . '" class="flex items-center p-2 rounded-md hover:bg-gray-700 transition duration-150 ' . $isActive . '" aria-current="' . ($isActive ? 'page' : 'false') . '">';
                            echo '<span class="mr-2 text-sm">' . $item['icon'] . '</span>';
                            echo '<span class="text-sm">' . htmlspecialchars($item['label']) . '</span>';
                            echo '</a>';
                            echo '</li>';
                        }
                ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Project Management Section -->
            <?php if (!empty($projectItems)): ?>
                <div>
                    <p class="px-1 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Projects</p>
                    <ul class="space-y-1">
                        <?php
                foreach ($projectItems as $item) {
                    $isActive = strpos($currentPath, $item['path']) === 0 ? 'bg-indigo-600' : '';
                    echo '<li>';
                    echo '<a href="' . htmlspecialchars($item['path']) . '" class="flex items-center py-1.5 px-2 rounded-md hover:bg-gray-700 transition duration-150 ' . $isActive . '" aria-current="' . ($isActive ? 'page' : 'false') . '">';
                    echo '<span class="mr-2 text-sm">' . $item['icon'] . '</span>';
                    echo '<span class="text-sm">' . htmlspecialchars($item['label']) . '</span>';
                    echo '</a>';
                    echo '</li>';
                }
                ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Task Management Section -->
            <?php if (!empty($taskItems)): ?>
                <div>
                    <p class="px-1 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Tasks</p>
                    <ul class="space-y-1">
                        <?php
                foreach ($taskItems as $item) {
                    // Use exact path matching for task items to prevent prefix conflicts
                    // Special handling for "My Tasks" which includes user ID
                    if (strpos($item['path'], '/tasks/assigned/') === 0) {
                        $isActive = strpos($currentPath, '/tasks/assigned/') === 0 ? 'bg-indigo-600' : '';
                    } else {
                        $isActive = $currentPath === $item['path'] ? 'bg-indigo-600' : '';
                    }
                    echo '<li>';
                    echo '<a href="' . htmlspecialchars($item['path']) . '" class="flex items-center py-1.5 px-2 rounded-md hover:bg-gray-700 transition duration-150 ' . $isActive . '" aria-current="' . ($isActive ? 'page' : 'false') . '">';
                    echo '<span class="mr-2 text-sm">' . $item['icon'] . '</span>';
                    echo '<span class="text-sm">' . htmlspecialchars($item['label']) . '</span>';
                    echo '</a>';
                    echo '</li>';
                }
?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Time Tracking Section -->
            <?php
            // Check if user has any time tracking permissions before showing the section
            $hasTimeTrackingPermission = hasUserPermission('view_time_tracking') ||
               hasUserPermission('create_time_tracking') ||
               hasUserPermission('edit_time_tracking') ||
               hasUserPermission('delete_time_tracking');

if ($hasTimeTrackingPermission && !empty($timeTrackingItems)):
    ?>
                <div>
                    <p class="px-1 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Time</p>
                    <ul class="space-y-1">
                        <?php
                foreach ($timeTrackingItems as $item) {
                    $hasPermission = !isset($item['permission']) || hasUserPermission($item['permission']);

                    if ($hasPermission) {
                        $isActive = strpos($currentPath, $item['path']) === 0 ? 'bg-indigo-600' : '';
                        echo '<li>';
                        echo '<a href="' . htmlspecialchars($item['path']) . '" class="flex items-center py-1.5 px-2 rounded-md hover:bg-gray-700 transition duration-150 ' . $isActive . '" aria-current="' . ($isActive ? 'page' : 'false') . '">';
                        echo '<span class="mr-2 text-sm">' . $item['icon'] . '</span>';
                        echo '<span class="text-sm">' . htmlspecialchars($item['label']) . '</span>';
                        echo '</a>';
                        echo '</li>';
                    }
                }
    ?>
                    </ul>
                </div>
            <?php endif; ?>



            <!-- Administration Section -->
            <?php if (!empty($adminItems)): ?>
                <div>
                    <p class="px-1 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Admin</p>
                    <ul class="space-y-1">
                        <?php
    foreach ($adminItems as $item) {
        $isActive = strpos($currentPath, $item['path']) === 0 ? 'bg-indigo-600' : '';
        echo '<li>';
        echo '<a href="' . htmlspecialchars($item['path']) . '" class="flex items-center py-1.5 px-2 rounded-md hover:bg-gray-700 transition duration-150 ' . $isActive . '" aria-current="' . ($isActive ? 'page' : 'false') . '">';
        echo '<span class="mr-2 text-sm">' . $item['icon'] . '</span>';
        echo '<span class="text-sm">' . htmlspecialchars($item['label']) . '</span>';
        echo '</a>';
        echo '</li>';
    }
                ?>
                    </ul>
                </div>
            <?php endif; ?>
        </nav>
    </div>

    <!-- User Profile Section - Fixed at bottom -->
    <div class="flex-shrink-0 border-t border-gray-700 p-3">
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white font-semibold text-sm">
                <?php
                $userInitials = '';
if (isset($currentUser['profile'])) {
    $firstName = $currentUser['profile']['first_name'] ?? '';
    $lastName = $currentUser['profile']['last_name'] ?? '';
    $userInitials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
}
echo htmlspecialchars($userInitials);
?>
            </div>
            <div class="ml-2 flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">
                    <?php
    if (isset($currentUser['profile'])) {
        echo htmlspecialchars($currentUser['profile']['first_name'] . ' ' . $currentUser['profile']['last_name']);
    } else {
        echo 'Guest User';
    }
?>
                </p>
                <p class="text-xs text-gray-400 truncate">
                    <?php
if (isset($currentUser['roles']) && !empty($currentUser['roles'])) {
    echo htmlspecialchars($currentUser['roles'][0]);
} else {
    echo 'No role assigned';
}
?>
                </p>
            </div>
            <a href="/logout" onclick="if(typeof clearFavoritesCache === 'function') clearFavoritesCache();" class="ml-2 p-1 rounded-full hover:bg-gray-700 flex-shrink-0" title="Logout">
                <svg class="w-4 h-4 text-gray-400 hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </a>
        </div>
    </div>
</aside>