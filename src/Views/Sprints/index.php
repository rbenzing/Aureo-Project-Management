<?php
//file: Views/Sprints/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include helper functions
include_once BASE_PATH . '/../src/Views/Sprints/inc/helpers.php';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprints - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Breadcrumb with New Sprint Button -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <!-- Breadcrumb Section -->
            <div class="flex-1">
                <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
            </div>

            <!-- New Sprint Button (only show when project is selected) -->
            <?php if (!empty($project) && isset($currentUser['permissions']) && in_array('create_sprints', $currentUser['permissions'])): ?>
            <div class="flex-shrink-0">
                <a
                    href="/sprints/create/<?= $project->id ?? 0 ?>"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    New Sprint
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Project Selection or Project-Specific Content -->
        <?php if (empty($project)): ?>
            <!-- Sprint Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                    <div class="rounded-full bg-indigo-100 dark:bg-indigo-900 p-3 mr-4">
                        <svg class="w-6 h-6 text-indigo-500 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Projects</div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            <?= count($projects ?? []) ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                    <div class="rounded-full bg-green-100 dark:bg-green-900 p-3 mr-4">
                        <svg class="w-6 h-6 text-green-500 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Sprints</div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            <?php
                            $totalActiveSprints = 0;
            if (!empty($projectSprintCounts)) {
                foreach ($projectSprintCounts as $counts) {
                    $totalActiveSprints += $counts['active'] ?? 0;
                }
            }
            echo $totalActiveSprints;
            ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center">
                    <div class="rounded-full bg-blue-100 dark:bg-blue-900 p-3 mr-4">
                        <svg class="w-6 h-6 text-blue-500 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed Sprints</div>
                        <div class="text-xl font-semibold text-blue-600 dark:text-blue-400">
                            <?php
            $totalCompletedSprints = 0;
            if (!empty($projectSprintCounts)) {
                foreach ($projectSprintCounts as $counts) {
                    $totalCompletedSprints += $counts['completed'] ?? 0;
                }
            }
            echo $totalCompletedSprints;
            ?>
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
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Planning Sprints</div>
                        <div class="text-xl font-semibold text-yellow-600 dark:text-yellow-400">
                            <?php
            $totalPlanningSprints = 0;
            if (!empty($projectSprintCounts)) {
                foreach ($projectSprintCounts as $counts) {
                    $totalPlanningSprints += $counts['planning'] ?? 0;
                }
            }
            echo $totalPlanningSprints;
            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Title with Star Icon -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center space-x-2">
                    <h1 class="text-2xl font-medium dark:text-white">Sprints</h1>
                    <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors"
                            data-type="page"
                            data-title="Sprints"
                            data-url="/sprints"
                            data-icon="🚀"
                            title="Add to favorites">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <?php include BASE_PATH . '/../src/Views/Sprints/inc/project_selection.php'; ?>
        <?php else: ?>
            <!-- Project is selected - display sprints -->



            <!-- Sprint Stats Summary -->
            <?php include BASE_PATH . '/../src/Views/Sprints/inc/sprint_stats.php'; ?>

            <!-- Page Title with Star Icon and Filters -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="flex items-center space-x-2">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($project->name ?? 'Project') ?></h1>
                    <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors"
                            data-type="page"
                            data-title="<?= htmlspecialchars($project->name ?? 'Project') ?> - Sprints"
                            data-url="/sprints/project/<?= $project->id ?? 0 ?>"
                            data-icon="🚀"
                            title="Add to favorites">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </button>
                    <?php if (!empty($sprints) && $activeSprintCount > 0): ?>
                        <span class="ml-3 px-2.5 py-0.5 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 text-xs font-medium rounded-full">
                            Active Sprint
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Search and Filter Controls -->
                <div class="flex space-x-3">
                    <!-- Status Filter -->
                    <div class="relative min-w-[160px]">
                        <select id="status-filter" class="h-10 appearance-none w-full px-4 py-2 dark:text-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md pl-10 pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="all">All Statuses</option>
                            <?php
            for ($i = 1; $i <= 5; $i++) {
                $statusInfo = getSprintStatusInfo($i);
                echo '<option value="' . (string)$i . '">' . htmlspecialchars($statusInfo['label']) . '</option>';
            }
?>
                        </select>
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                        </div>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative min-w-[200px]">
                        <input
                            id="sprint-search"
                            type="search"
                            placeholder="Search sprints..."
                            class="h-10 w-full appearance-none py-2 pr-10 pl-10 dark:text-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Active Sprint Panel (if exists) -->
            <?php
            $activeSprint = null;
if (!empty($sprints)) {
    foreach ($sprints as $sprint) {
        if (isset($sprint->status_id) && $sprint->status_id == 2) { // Active status
            $activeSprint = $sprint;

            break;
        }
    }
}

if ($activeSprint):
    include BASE_PATH . '/../src/Views/Sprints/inc/active_sprint.php';
endif;
?>

            <!-- All Sprints List -->
            <?php include BASE_PATH . '/../src/Views/Sprints/inc/sprint_list.php'; ?>

            <!-- Pagination (if needed) -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
                <div class="mt-6 flex justify-between items-center">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Page <?= $page ?? 1 ?> of <?= $totalPages ?>
                    </div>
                    <div class="flex space-x-2">
                        <?php if (($page ?? 1) > 1): ?>
                            <a 
                                href="/sprints/project/<?= $project->id ?? 0 ?>&page=<?= ($page ?? 1) - 1 ?>" 
                                class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if (($page ?? 1) < $totalPages): ?>
                            <a 
                                href="/sprints/project/<?= $project->id ?? 0 ?>&page=<?= ($page ?? 1) + 1 ?>" 
                                class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- JavaScript for Filtering -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusFilter = document.getElementById('status-filter');
            const searchInput = document.getElementById('sprint-search');
            const sprintRows = document.querySelectorAll('.sprint-row');
            
            function filterSprints() {
                const statusValue = statusFilter ? statusFilter.value : 'all';
                const searchText = searchInput ? searchInput.value.toLowerCase() : '';
                
                sprintRows.forEach(row => {
                    let show = true;
                    
                    // Status filter
                    if (statusValue !== 'all' && row.dataset.status !== statusValue) {
                        show = false;
                    }
                    
                    // Search filter
                    if (searchText && !row.dataset.name.toLowerCase().includes(searchText)) {
                        show = false;
                    }
                    
                    row.style.display = show ? '' : 'none';
                });
                
                // Check if any visible rows
                const visibleRows = Array.from(sprintRows).filter(row => row.style.display !== 'none');
                const noResultsRow = document.getElementById('no-results-row');
                
                if (visibleRows.length === 0) {
                    if (!noResultsRow) {
                        const tbody = document.getElementById('sprints-list');
                        if (tbody) {
                            const newRow = document.createElement('tr');
                            newRow.id = 'no-results-row';
                            newRow.innerHTML = `
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                    No sprints match your filters. <a href="#" onclick="resetFilters(); return false;" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Reset filters</a>
                                </td>
                            `;
                            tbody.appendChild(newRow);
                        }
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            }
            
            // Add event listeners
            if (statusFilter) {
                statusFilter.addEventListener('change', filterSprints);
            }
            
            if (searchInput) {
                searchInput.addEventListener('input', filterSprints);
            }
            
            // Function to reset filters (for the "Reset filters" link)
            window.resetFilters = function() {
                if (statusFilter) statusFilter.value = 'all';
                if (searchInput) searchInput.value = '';
                filterSprints();
            };
        });
    </script>
</body>
</html>