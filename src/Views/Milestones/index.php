<?php
// file: Views/Milestones/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include view helpers for permission functions and formatting
require_once BASE_PATH . '/../src/Views/Layouts/ViewHelpers.php';

// Include milestone-specific helpers
require_once BASE_PATH . '/../src/Views/Milestones/inc/helpers.php';

// Ensure $milestones is always defined
$milestones = $milestones ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milestones - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
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

        <?php
        // Set up breadcrumb parameters
        $breadcrumbParams = [
            'route' => 'milestones',
            'params' => [],
        ];
include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php';
?>

        <?php if (!empty($project)): ?>
            <!-- Project Header with Navigation -->
            <?php include BASE_PATH . '/../src/Views/Milestones/inc/project_header.php'; ?>
        <?php endif; ?>

        <!-- Include the header with stats and filters -->
        <?php include BASE_PATH . '/../src/Views/Milestones/inc/table_header.php'; ?>

        <!-- Include the table view -->
        <?php include BASE_PATH . '/../src/Views/Milestones/inc/table.php'; ?>

        <!-- Include the timeline view -->
        <?php include BASE_PATH . '/../src/Views/Milestones/inc/timeline.php'; ?>

        <!-- Include the card view -->
        <?php include BASE_PATH . '/../src/Views/Milestones/inc/cards.php'; ?>

        <!-- Include the pagination -->
        <?php include BASE_PATH . '/../src/Views/Milestones/inc/pagination.php'; ?>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // View toggle - make sure we're selecting the correct elements
            const tableViewBtn = document.getElementById('table-view-btn');
            const timelineViewBtn = document.getElementById('timeline-view-btn');
            const cardViewBtn = document.getElementById('card-view-btn');
            const tableView = document.getElementById('table-view');
            const timelineView = document.getElementById('timeline-view');
            const cardView = document.getElementById('card-view');

            // Store current view in localStorage
            const setActiveView = (viewName) => {
                localStorage.setItem('milestoneActiveView', viewName);
            };

            // Explicitly set up the click handlers with proper toggle
            if (tableViewBtn) {
                tableViewBtn.addEventListener('click', function() {
                    // Show table view, hide others
                    if (tableView) tableView.classList.remove('hidden');
                    if (timelineView) timelineView.classList.add('hidden');
                    if (cardView) cardView.classList.add('hidden');

                    // Update active button styles
                    tableViewBtn.classList.add('text-indigo-600', 'border-indigo-600');
                    tableViewBtn.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');

                    if (timelineViewBtn) {
                        timelineViewBtn.classList.remove('text-indigo-600', 'border-indigo-600');
                        timelineViewBtn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                    }

                    if (cardViewBtn) {
                        cardViewBtn.classList.remove('text-indigo-600', 'border-indigo-600');
                        cardViewBtn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                    }

                    setActiveView('table');
                });
            }

            if (timelineViewBtn) {
                timelineViewBtn.addEventListener('click', function() {
                    // Show timeline view, hide others
                    if (tableView) tableView.classList.add('hidden');
                    if (timelineView) timelineView.classList.remove('hidden');
                    if (cardView) cardView.classList.add('hidden');

                    // Update active button styles
                    if (tableViewBtn) {
                        tableViewBtn.classList.remove('text-indigo-600', 'border-indigo-600');
                        tableViewBtn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                    }

                    timelineViewBtn.classList.add('text-indigo-600', 'border-indigo-600');
                    timelineViewBtn.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');

                    if (cardViewBtn) {
                        cardViewBtn.classList.remove('text-indigo-600', 'border-indigo-600');
                        cardViewBtn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                    }

                    setActiveView('timeline');
                });
            }

            if (cardViewBtn) {
                cardViewBtn.addEventListener('click', function() {
                    // Show card view, hide others
                    if (tableView) tableView.classList.add('hidden');
                    if (timelineView) timelineView.classList.add('hidden');
                    if (cardView) cardView.classList.remove('hidden');

                    // Update active button styles
                    if (tableViewBtn) {
                        tableViewBtn.classList.remove('text-indigo-600', 'border-indigo-600');
                        tableViewBtn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                    }

                    if (timelineViewBtn) {
                        timelineViewBtn.classList.remove('text-indigo-600', 'border-indigo-600');
                        timelineViewBtn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                    }

                    cardViewBtn.classList.add('text-indigo-600', 'border-indigo-600');
                    cardViewBtn.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');

                    setActiveView('card');
                });
            }

            // Get filter elements
            const milestoneFilter = document.getElementById('milestone-filter');
            const projectFilter = document.getElementById('project-filter');
            const searchInput = document.getElementById('milestone-search');

            // Helper function to update visibility
            function updateVisibility() {
                const filter = milestoneFilter ? milestoneFilter.value : 'all';
                const projectId = projectFilter ? projectFilter.value : '';
                const searchText = searchInput ? searchInput.value.toLowerCase() : '';

                // Table rows
                const tableRows = document.querySelectorAll('.milestone-row');
                tableRows.forEach(row => {
                    let shouldShow = true;

                    // Status filter
                    if (filter === 'epic' && row.dataset.type !== 'epic') {
                        shouldShow = false;
                    } else if (filter === 'milestone' && row.dataset.type === 'epic') {
                        shouldShow = false;
                    } else if (filter === 'overdue' && row.dataset.overdue !== '1') {
                        shouldShow = false;
                    } else if (filter === 'in-progress' && row.dataset.status !== '2') {
                        shouldShow = false;
                    } else if (filter === 'completed' && row.dataset.status !== '3') {
                        shouldShow = false;
                    } else if (filter === 'on-hold' && row.dataset.status !== '4') {
                        shouldShow = false;
                    }

                    // Project filter
                    if (projectId && row.dataset.project !== projectId) {
                        shouldShow = false;
                    }

                    // Search filter
                    if (searchText && !row.dataset.title.toLowerCase().includes(searchText)) {
                        shouldShow = false;
                    }

                    row.style.display = shouldShow ? '' : 'none';
                });

                // Timeline rows - apply filters but let timeline navigation handle visibility
                const timelineRows = document.querySelectorAll('.milestone-timeline-row');
                timelineRows.forEach(row => {
                    let shouldShow = true;

                    // Status filter
                    if (filter === 'epic' && row.dataset.type !== 'epic') {
                        shouldShow = false;
                    } else if (filter === 'milestone' && row.dataset.type === 'epic') {
                        shouldShow = false;
                    } else if (filter === 'overdue' && row.dataset.overdue !== '1') {
                        shouldShow = false;
                    } else if (filter === 'in-progress' && row.dataset.status !== '2') {
                        shouldShow = false;
                    } else if (filter === 'completed' && row.dataset.status !== '3') {
                        shouldShow = false;
                    } else if (filter === 'on-hold' && row.dataset.status !== '4') {
                        shouldShow = false;
                    }

                    // Project filter
                    if (projectId && row.dataset.project !== projectId) {
                        shouldShow = false;
                    }

                    // Search filter
                    if (searchText && !row.dataset.title.toLowerCase().includes(searchText)) {
                        shouldShow = false;
                    }

                    // Store filter result in data attribute for timeline navigation to use
                    row.dataset.filteredOut = shouldShow ? 'false' : 'true';
                });

                // Trigger timeline update if timeline view is active
                if (document.getElementById('timeline-view') && !document.getElementById('timeline-view').classList.contains('hidden')) {
                    // Trigger timeline refresh to apply filters
                    const timelineRefreshEvent = new CustomEvent('timelineRefresh');
                    document.dispatchEvent(timelineRefreshEvent);
                }

                // Card view
                const cards = document.querySelectorAll('.milestone-card');
                cards.forEach(card => {
                    let shouldShow = true;

                    // Status filter
                    if (filter === 'epic' && card.dataset.type !== 'epic') {
                        shouldShow = false;
                    } else if (filter === 'milestone' && card.dataset.type === 'epic') {
                        shouldShow = false;
                    } else if (filter === 'overdue' && card.dataset.overdue !== '1') {
                        shouldShow = false;
                    } else if (filter === 'in-progress' && card.dataset.status !== '2') {
                        shouldShow = false;
                    } else if (filter === 'completed' && card.dataset.status !== '3') {
                        shouldShow = false;
                    } else if (filter === 'on-hold' && card.dataset.status !== '4') {
                        shouldShow = false;
                    }

                    // Project filter
                    if (projectId && card.dataset.project !== projectId) {
                        shouldShow = false;
                    }

                    // Search filter
                    if (searchText && !card.dataset.title.toLowerCase().includes(searchText)) {
                        shouldShow = false;
                    }

                    card.style.display = shouldShow ? '' : 'none';
                });

                // Store filter values in sessionStorage
                if (milestoneFilter) sessionStorage.setItem('milestoneFilter', milestoneFilter.value);
                if (projectFilter) sessionStorage.setItem('milestoneProject', projectFilter.value);
                if (searchInput) sessionStorage.setItem('milestoneSearch', searchInput.value);
            }

            // Add event listeners for filters
            if (milestoneFilter) {
                milestoneFilter.addEventListener('change', updateVisibility);
            }

            if (projectFilter) {
                projectFilter.addEventListener('change', updateVisibility);
            }

            if (searchInput) {
                searchInput.addEventListener('input', updateVisibility);
            }

            // Load saved view preference
            const savedView = localStorage.getItem('milestoneActiveView');
            if (savedView === 'timeline') {
                timelineViewBtn.click();
            } else if (savedView === 'card') {
                cardViewBtn.click();
            } else {
                tableViewBtn.click(); // Default to table view
            }

            // Restore saved filters
            const storedFilter = sessionStorage.getItem('milestoneFilter');
            const storedProject = sessionStorage.getItem('milestoneProject');
            const storedSearch = sessionStorage.getItem('milestoneSearch');

            if (storedFilter && milestoneFilter) {
                milestoneFilter.value = storedFilter;
            }

            if (storedProject && projectFilter) {
                projectFilter.value = storedProject;
            }

            if (storedSearch && searchInput) {
                searchInput.value = storedSearch;
            }

            // Apply filters on load
            updateVisibility();
        });
    </script>
</body>
</html>