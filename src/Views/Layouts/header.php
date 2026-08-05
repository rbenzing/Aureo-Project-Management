<?php
//file: Views/Layouts/header.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Function to check if the user has permission (same as in sidebar)
function hasHeaderPermission($permission)
{
    if (empty($permission)) {
        return true; // No permission required
    }

    // Get user permissions from session
    $userPermissions = $currentUser['permissions'] ?? [];

    return in_array($permission, $userPermissions, true);
}
?>
<header class="bg-indigo-600 text-white shadow-md">
    <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-3 flex justify-between items-center">
        <div class="flex flex-row items-center">
            <button id="sidebar-toggle" class="block bg-indigo-600 text-white p-2 rounded z-10" aria-label="Toggle Sidebar">
            ☰
            </button>
            <h1 class="text-lg font-bold ml-2">Aureo</h1>

            <!-- Favorites Navigation -->
            <div id="favorites-nav" class="ml-6 flex items-center space-x-2">
                <!-- Favorites will be loaded here dynamically -->
            </div>
        </div>
        <?php if (!empty($_SESSION['active_timer'])): ?>
        <div id="header-timer-indicator" class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg px-3 py-1 cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors" onclick="if(typeof showFloatingTimer === 'function') showFloatingTimer();" title="Click to show timer">
            <div class="flex items-center">
                <svg class="w-3 h-3 text-indigo-500 mr-1 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-xs font-medium text-indigo-700 dark:text-indigo-300">
                    Timer Active - Click to Show
                </div>
            </div>
        </div>
        <?php endif; ?>
        <nav class="space-x-4 flex flex-row items-center">
            <?php if (isset($currentUser['profile']['id'])): ?>
                <a href="/activity" class="hover:text-indigo-200">📄 Activity</a>

                <!-- Command Palette Trigger -->
                <button type="button"
                        id="cmd-open-btn"
                        class="hidden sm:flex items-center gap-2 px-3 py-1.5 text-sm
                               text-indigo-200 hover:text-white
                               bg-indigo-700 hover:bg-indigo-500
                               rounded-lg border border-indigo-500/50
                               transition-colors focus:outline-none focus:ring-2
                               focus:ring-white/50"
                        aria-label="Open command palette (Ctrl+K)"
                        title="Search (Ctrl+K)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span>Search</span>
                    <kbd class="hidden lg:inline-flex items-center gap-0.5 px-1.5 py-0.5
                                text-xs font-medium rounded
                                bg-indigo-600 border border-indigo-400/50
                                text-indigo-300">
                        <span>⌘</span><span>K</span>
                    </kbd>
                </button>

                <!-- User Avatar Dropdown -->
                <div class="relative dropdown">
                    <button type="button" class="h-11 w-11 rounded-full bg-indigo-100 dark:bg-indigo-900 flex-shrink-0 flex items-center justify-center hover:bg-indigo-200 dark:hover:bg-indigo-800 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" aria-expanded="false" aria-haspopup="true">
                        <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                            <?= htmlspecialchars(substr($currentUser['profile']['first_name'], 0, 1) . substr($currentUser['profile']['last_name'], 0, 1)) ?>
                        </span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu hidden absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                        <div class="py-1" role="menu" aria-orientation="vertical">
                            <!-- Profile - Always show for logged in users -->
                            <a href="/profile" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" role="menuitem">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                My Profile
                            </a>

                            <!-- My Projects - Only show if user can view projects -->
                            <?php if (hasHeaderPermission('view_projects')): ?>
                            <a href="/projects" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" role="menuitem">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                My Projects
                            </a>
                            <?php endif; ?>

                            <!-- My Tasks - Only show if user can view tasks -->
                            <?php if (hasHeaderPermission('view_tasks')): ?>
                            <a href="/tasks/assigned/<?= $currentUser['profile']['id'] ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" role="menuitem">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                My Tasks
                            </a>
                            <?php endif; ?>

                            <!-- Current Sprint - Only show if user can view sprints -->
                            <?php if (hasHeaderPermission('view_sprints')): ?>
                            <a href="/sprints/current" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" role="menuitem">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Current Sprint
                            </a>
                            <?php endif; ?>

                            <!-- Divider - Only show if there are menu items above -->
                            <?php if (hasHeaderPermission('view_projects') || hasHeaderPermission('view_tasks') || hasHeaderPermission('view_sprints')): ?>
                            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                            <?php endif; ?>

                            <!-- Logout - Always show -->
                            <a href="/logout" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" role="menuitem">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login" class="hover:text-indigo-200">🔐 Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php
// Add favorites script and CSRF token if not already added
if (!isset($GLOBALS['favorites_script_added'])) {
    $GLOBALS['favorites_script_added'] = true;
    echo '<script>
        // Add CSRF token to window for favorites.js
        window.csrfToken = "' . htmlspecialchars($csrfToken ?? '') . '";
    </script>';
    echo '<script src="' . asset('js/favorites.js') . '" defer></script>';
    echo '<script src="' . asset('js/favorites-utils.js') . '" defer></script>';
}
?>