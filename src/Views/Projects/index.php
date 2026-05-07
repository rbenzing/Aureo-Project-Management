<?php
//file: Views/Projects/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Determine which view to show based on the tab parameter
$activeTab = $_GET['view'] ?? 'table';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Breadcrumb with Action Button -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <!-- Breadcrumb Section -->
            <div class="flex-1">
                <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
            </div>

            <!-- Action Button -->
            <div class="flex-shrink-0">
                <?php if (isset($currentUser['permissions']) && in_array('create_projects', $currentUser['permissions'])): ?>
                    <a href="/projects/create" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        New Project
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Project Stats Summary -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-indigo-100 dark:bg-indigo-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-indigo-500 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Projects</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        <?= $projectStats['total'] ?? 0 ?>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-blue-100 dark:bg-blue-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-blue-500 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">In Progress</div>
                    <div class="text-xl font-semibold text-blue-600 dark:text-blue-400">
                        <?= $projectStats['in_progress'] ?? 0 ?>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-green-100 dark:bg-green-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-green-500 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed</div>
                    <div class="text-xl font-semibold text-green-600 dark:text-green-400">
                        <?= $projectStats['completed'] ?? 0 ?>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-yellow-100 dark:bg-yellow-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-yellow-500 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">On Hold</div>
                    <div class="text-xl font-semibold text-yellow-600 dark:text-yellow-400">
                        <?= $projectStats['on_hold'] ?? 0 ?>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                <div class="rounded-full bg-red-100 dark:bg-red-900 p-3 mr-4">
                    <svg class="w-6 h-6 text-red-500 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Delayed</div>
                    <div class="text-xl font-semibold text-red-600 dark:text-red-400">
                        <?= $projectStats['delayed'] ?? 0 ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Title with Star Icon -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-2">
                <h1 class="text-2xl font-medium dark:text-white">Projects</h1>
                <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors"
                        data-type="page"
                        data-title="Projects"
                        data-url="/projects"
                        data-icon="📁"
                        title="Add to favorites">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                </button>
            </div>
            <div class="flex items-center space-x-4">
                <?php
                    switch ($activeTab) {
                        case 'table':
                            include BASE_PATH . '/../src/Views/Projects/inc/table_header.php';

                            break;
                        default:
                    }
?>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center space-x-6 border-b border-gray-200 dark:border-gray-700 mb-6">
            <a href="/projects?view=table" class="px-4 py-2 <?= $activeTab === 'table' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-400 dark:border-blue-400' : 'text-gray-700 dark:text-gray-300' ?>">Table</a>
            <a href="/projects?view=pivot" class="px-4 py-2 <?= $activeTab === 'pivot' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-400 dark:border-blue-400' : 'text-gray-700 dark:text-gray-300' ?>">Pivot Board</a>
            <a href="/projects?view=gantt" class="px-4 py-2 <?= $activeTab === 'gantt' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-400 dark:border-blue-400' : 'text-gray-700 dark:text-gray-300' ?>">Gantt</a>
            <a href="/projects?view=charts" class="px-4 py-2 <?= $activeTab === 'charts' ? 'text-blue-600 border-b-2 border-blue-600 dark:text-blue-400 dark:border-blue-400' : 'text-gray-700 dark:text-gray-300' ?>">Charts</a>
        </div>

        <?php
        // Include the appropriate view based on the active tab
        switch ($activeTab) {
            case 'table':
                include BASE_PATH . '/../src/Views/Projects/inc/table.php';

                break;
            case 'charts':
                include BASE_PATH . '/../src/Views/Projects/inc/charts.php';

                break;
            case 'pivot':
                include BASE_PATH . '/../src/Views/Projects/inc/pivot.php';

                break;
            case 'gantt':
                include BASE_PATH . '/../src/Views/Projects/inc/gantt.php';

                break;
            default:
                include BASE_PATH . '/../src/Views/Projects/inc/table.php';
        }
?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>
    
    <script>
        // Page-specific dropdown functionality (excluding header dropdown)
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('main .dropdown, .content .dropdown');
            dropdowns.forEach(dropdown => {
                const button = dropdown.querySelector('button');
                const menu = dropdown.querySelector('.dropdown-menu');

                if (button && menu) {
                    button.addEventListener('click', function() {
                        menu.classList.toggle('hidden');
                    });

                    // Close when clicking outside
                    document.addEventListener('click', function(event) {
                        if (!dropdown.contains(event.target)) {
                            menu.classList.add('hidden');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>