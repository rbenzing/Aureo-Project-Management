<?php
//file: Views/Projects/inc/table_header.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Get current search parameters for form values
$currentSearch = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$currentView = isset($_GET['by']) ? htmlspecialchars($_GET['by']) : 'tasks';
$currentStatus = isset($_GET['status_id']) ? (int)$_GET['status_id'] : '';
$currentCompany = isset($_GET['company_id']) ? (int)$_GET['company_id'] : '';
$currentSort = isset($_GET['sort']) ? htmlspecialchars($_GET['sort']) : 'updated_at';
$currentDir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';

// Map status IDs to names
$statusOptions = [
    1 => 'Ready',
    2 => 'In Progress',
    3 => 'Completed',
    4 => 'On Hold',
    6 => 'Delayed',
    7 => 'Cancelled',
];
?>

<!-- Toolbar for Table View -->
<div class="flex flex-wrap items-center gap-4">
    
    <!-- Search Form -->
    <form action="/projects" method="GET" class="flex-grow sm:flex-grow-0 flex flex-wrap md:flex-nowrap gap-2">
        <input type="hidden" name="view" value="table">
        <?php if ($currentView): ?>
            <input type="hidden" name="by" value="<?= $currentView ?>">
        <?php endif; ?>
        <?php if ($currentSort): ?>
            <input type="hidden" name="sort" value="<?= $currentSort ?>">
        <?php endif; ?>
        <?php if ($currentDir): ?>
            <input type="hidden" name="dir" value="<?= $currentDir ?>">
        <?php endif; ?>
        
        <!-- Search Box -->
        <div class="relative flex-grow sm:flex-grow-0 min-w-[200px]">
            <input type="text" name="search" placeholder="Search projects" 
                value="<?= $currentSearch ?>"
                class="pl-10 pr-4 py-2 w-full bg-white dark:bg-gray-800 text-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        
        <!-- Search Button -->
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Search
        </button>
        
        <!-- Clear Button (shows only when filters are active) -->
        <?php if ($currentSearch || $currentStatus || $currentCompany): ?>
            <a href="/projects?view=table<?= $currentView ? '&by=' . $currentView : '' ?>" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 shadow-sm">
                Clear
            </a>
        <?php endif; ?>
    </form>
    
    <!-- View By Dropdown -->
    <div class="dropdown relative">
        <button type="button" class="view-dropdown-toggle px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View: <?= ucfirst($currentView) ?>
                <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
        </button>
        <div class="view-dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
            <div class="py-1">
                <?php
                $viewOptions = [
                    'projects' => 'Project Overview',
                    'tasks' => 'Tasks',
                    'milestones' => 'Milestones',
                    'sprints' => 'Sprints',
                ];

foreach ($viewOptions as $value => $label):
    $isActive = $currentView === $value;
    $baseUrl = "/projects?view=table&by=" . $value;
    $queryParams = [];

    if ($currentSearch) {
        $queryParams[] = "search=" . urlencode($currentSearch);
    }
    if ($currentStatus) {
        $queryParams[] = "status_id=" . $currentStatus;
    }
    if ($currentCompany) {
        $queryParams[] = "company_id=" . $currentCompany;
    }
    if ($currentSort) {
        $queryParams[] = "sort=" . $currentSort;
    }
    if ($currentDir) {
        $queryParams[] = "dir=" . $currentDir;
    }

    $fullUrl = $baseUrl;
    if (!empty($queryParams)) {
        $fullUrl .= "&" . implode("&", $queryParams);
    }
    ?>
                    <a href="<?= $fullUrl ?>" class="block px-4 py-2 text-sm <?= $isActive ? 'bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filter Dropdown -->
    <div class="dropdown relative">
        <button type="button" class="filter-dropdown-toggle px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filter
                <?php if ($currentStatus || $currentCompany): ?>
                    <span class="ml-1.5 flex h-2 w-2 relative">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-indigo-500"></span>
                    </span>
                <?php endif; ?>
                <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
        </button>
        <div class="filter-dropdown-menu hidden absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
            <form action="/projects" method="GET" class="p-4">
                <input type="hidden" name="view" value="table">
                <?php if ($currentSearch): ?>
                    <input type="hidden" name="search" value="<?= $currentSearch ?>">
                <?php endif; ?>
                <?php if ($currentView): ?>
                    <input type="hidden" name="by" value="<?= $currentView ?>">
                <?php endif; ?>
                <?php if ($currentSort): ?>
                    <input type="hidden" name="sort" value="<?= $currentSort ?>">
                <?php endif; ?>
                <?php if ($currentDir): ?>
                    <input type="hidden" name="dir" value="<?= $currentDir ?>">
                <?php endif; ?>
                
                <!-- Status Filter -->
                <div class="mb-4">
                    <label for="status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select id="status_id" name="status_id" class="block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusOptions as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $currentStatus === $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Company Filter -->
                <div class="mb-4">
                    <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
                    <select id="company_id" name="company_id" class="block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">All Companies</option>
                        <?php if (isset($companies) && is_array($companies)):
                            foreach ($companies as $company): ?>
                                <option value="<?= $company->id ?>" <?= $currentCompany == $company->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($company->name) ?>
                                </option>
                        <?php endforeach;
                        endif; ?>
                    </select>
                </div>
                
                <!-- Filter Actions -->
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Apply
                    </button>
                    <a href="/projects?view=table<?= $currentView ? '&by=' . $currentView : '' ?><?= $currentSort ? '&sort=' . $currentSort : '' ?><?= $currentDir ? '&dir=' . $currentDir : '' ?>" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Sort Dropdown -->
    <div class="dropdown relative">
        <button type="button" class="sort-dropdown-toggle px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                </svg>
                Sort: <?= ucfirst($currentSort) ?> (<?= $currentDir ?>)
                <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
        </button>
        <div class="sort-dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
            <div class="py-1">
                <?php
                $sortOptions = [
                    ['field' => 'name', 'dir' => 'asc', 'label' => 'Name (A-Z)'],
                    ['field' => 'name', 'dir' => 'desc', 'label' => 'Name (Z-A)'],
                    ['field' => 'created_at', 'dir' => 'desc', 'label' => 'Newest First'],
                    ['field' => 'created_at', 'dir' => 'asc', 'label' => 'Oldest First'],
                    ['field' => 'end_date', 'dir' => 'asc', 'label' => 'Due Date (Soonest)'],
                    ['field' => 'end_date', 'dir' => 'desc', 'label' => 'Due Date (Latest)'],
                    ['field' => 'status_id', 'dir' => 'asc', 'label' => 'Status (A-Z)'],
                    ['field' => 'company_id', 'dir' => 'asc', 'label' => 'Company (A-Z)'],
                ];

foreach ($sortOptions as $option):
    $isActive = $currentSort === $option['field'] && $currentDir === $option['dir'];
    $baseUrl = "/projects?view=table&sort=" . $option['field'] . "&dir=" . $option['dir'];
    $queryParams = [];

    if ($currentSearch) {
        $queryParams[] = "search=" . urlencode($currentSearch);
    }
    if ($currentStatus) {
        $queryParams[] = "status_id=" . $currentStatus;
    }
    if ($currentCompany) {
        $queryParams[] = "company_id=" . $currentCompany;
    }
    if ($currentView) {
        $queryParams[] = "by=" . $currentView;
    }

    $fullUrl = $baseUrl;
    if (!empty($queryParams)) {
        $fullUrl .= "&" . implode("&", $queryParams);
    }
    ?>
                    <a href="<?= $fullUrl ?>" class="block px-4 py-2 text-sm <?= $isActive ? 'bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                        <?= $option['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

        <!-- Toggle All Projects Control -->
    <div class="dropdown relative">
        <button class="accordion-dropdown-toggle text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
            </svg>
        </button>
        <div class="accordion-dropdown-menu hidden absolute right-0 mt-3 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
            <div class="py-1">         
                <button id="toggle-all-projects" type="button" class="w-full flex items-center justify-between px-4 py-2 text-sm bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                    <span class="toggle-all-text">Collapse All</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown toggle functionality
    function setupDropdown(toggleClass, menuClass) {
        const toggles = document.querySelectorAll('.' + toggleClass);
        const menus = document.querySelectorAll('.' + menuClass);
        
        toggles.forEach((toggle, index) => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close all other dropdowns
                menus.forEach((menu, menuIndex) => {
                    if (menuIndex !== index) {
                        menu.classList.add('hidden');
                    }
                });
                
                // Toggle current dropdown
                menus[index].classList.toggle('hidden');
            });
        });
    }
    
    // Setup each dropdown type
    setupDropdown('view-dropdown-toggle', 'view-dropdown-menu');
    setupDropdown('filter-dropdown-toggle', 'filter-dropdown-menu');
    setupDropdown('sort-dropdown-toggle', 'sort-dropdown-menu');
    setupDropdown('accordion-dropdown-toggle', 'accordion-dropdown-menu');
    
    // Close all dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.view-dropdown-menu, .filter-dropdown-menu, .sort-dropdown-menu, .accordion-dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
        });
    });
    
    // Prevent click inside dropdown from closing it
    document.querySelectorAll('.view-dropdown-menu, .filter-dropdown-menu, .sort-dropdown-menu, .accordion-dropdown-menu').forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>