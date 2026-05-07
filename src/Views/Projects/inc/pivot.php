<?php
//file: Views/Projects/inc/pivot.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Get filter type (status, company, owner)
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'status';

// Get filter value if exists
$filterValue = isset($_GET['filter_value']) ? $_GET['filter_value'] : '';

// Define status labels and colors
$statusLabels = [
    1 => 'Ready',
    2 => 'In Progress',
    3 => 'Completed',
    4 => 'On Hold',
    5 => 'Delayed',
    6 => 'Cancelled',
];

$statusColors = [
    1 => 'bg-blue-600',
    2 => 'bg-yellow-600',
    3 => 'bg-green-600',
    4 => 'bg-purple-600',
    5 => 'bg-red-600',
    6 => 'bg-gray-600',
];

// Create arrays to hold unique companies and owners
$companies = [];
$owners = [];

// Filter projects based on criteria
$filteredProjects = [];

if (!empty($projects)) {
    foreach ($projects as $project) {
        // Extract company and owner data for filter options
        if (isset($project->company_name) && !empty($project->company_name)) {
            $companies[$project->company_name] = $project->company_name;
        }

        if (isset($project->owner) && !empty($project->owner)) {
            $owners[$project->owner] = $project->owner;
        }

        // Apply filters
        $includeProject = true;

        if (!empty($filterType) && !empty($filterValue)) {
            switch ($filterType) {
                case 'status':
                    $includeProject = isset($project->status_id) && $project->status_id == $filterValue;

                    break;
                case 'company':
                    $includeProject = isset($project->company_name) && $project->company_name == $filterValue;

                    break;
                case 'owner':
                    $includeProject = isset($project->owner) && $project->owner == $filterValue;

                    break;
                default:
                    $includeProject = true;
            }
        }

        if ($includeProject) {
            $filteredProjects[] = $project;
        }
    }
}

// Group filtered projects by status
$groupedProjects = [];
foreach ($statusLabels as $statusId => $statusLabel) {
    $groupedProjects[$statusId] = [];
}

foreach ($filteredProjects as $project) {
    $status = $project->status_id ?? 1;
    $groupedProjects[$status][] = $project;
}

// Sort companies and owners alphabetically
asort($companies);
asort($owners);
?>

<!-- Pivot Board View -->
<div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white no-wrap">Pivot Board</h2>
        
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <!-- Filter Type -->
            <select id="filter-type" class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-md px-4 py-2 w-full sm:w-auto">
                <option value="status" <?= $filterType === 'status' ? 'selected' : '' ?>>Filter by Status</option>
                <option value="company" <?= $filterType === 'company' ? 'selected' : '' ?>>Filter by Company</option>
                <option value="owner" <?= $filterType === 'owner' ? 'selected' : '' ?>>Filter by Owner</option>
            </select>
            
            <!-- Filter Value - Status -->
            <select id="filter-status" class="filter-value bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-md px-4 py-2 w-full sm:w-auto <?= $filterType !== 'status' ? 'hidden' : '' ?>">
                <option value="">All Statuses</option>
                <?php foreach ($statusLabels as $id => $label): ?>
                    <option value="<?= $id ?>" <?= $filterType === 'status' && $filterValue == $id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Filter Value - Company -->
            <select id="filter-company" class="filter-value bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-md px-4 py-2 w-full sm:w-auto <?= $filterType !== 'company' ? 'hidden' : '' ?>">
                <option value="">All Companies</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= htmlspecialchars($company) ?>" <?= $filterType === 'company' && $filterValue == $company ? 'selected' : '' ?>>
                        <?= htmlspecialchars($company) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Filter Value - Owner -->
            <select id="filter-owner" class="filter-value bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-md px-4 py-2 w-full sm:w-auto <?= $filterType !== 'owner' ? 'hidden' : '' ?>">
                <option value="">All Owners</option>
                <?php foreach ($owners as $owner): ?>
                    <option value="<?= htmlspecialchars($owner) ?>" <?= $filterType === 'owner' && $filterValue == $owner ? 'selected' : '' ?>>
                        <?= htmlspecialchars($owner) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Clear Filters Button -->
            <a href="?view=pivot" class="text-center bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 w-full sm:w-auto">
                Clear Filters
            </a>
        </div>
    </div>
    
    <!-- Status count summary -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-6">
        <?php foreach ($statusLabels as $statusId => $statusLabel):
            $count = count($groupedProjects[$statusId]);
            $totalCount = array_reduce($groupedProjects, function ($carry, $group) {
                return $carry + count($group);
            }, 0);
            $percentage = $totalCount > 0 ? round(($count / $totalCount) * 100) : 0;
            ?>
            <div class="bg-gray-100 dark:bg-gray-700 p-3 rounded-lg text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $statusLabel ?></div>
                <div class="text-xl font-bold text-gray-900 dark:text-white"><?= $count ?></div>
                <div class="mt-1 w-full bg-gray-300 dark:bg-gray-600 h-1 rounded-full">
                    <div class="<?= $statusColors[$statusId] ?> h-1 rounded-full" style="width: <?= $percentage ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Project Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($groupedProjects as $statusId => $statusProjects): ?>
            <?php if (!empty($statusProjects) || empty($filterValue)): // Only show non-empty columns or all when no filter?>
                <div class="bg-gray-100 dark:bg-gray-700 rounded-md overflow-hidden">
                    <div class="<?= $statusColors[$statusId] ?? 'bg-gray-600' ?> px-4 py-2 font-medium text-white">
                        <?= $statusLabels[$statusId] ?? 'Unknown' ?> (<?= count($statusProjects) ?>)
                    </div>
                    
                    <div class="p-4 space-y-2 max-h-96 overflow-y-auto">
                        <?php if (!empty($statusProjects)): ?>
                            <?php foreach ($statusProjects as $project): ?>
                                <a href="/projects/view/<?= $project->id ?>" class="block">
                                    <div class="bg-white dark:bg-gray-800 p-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border border-gray-200 dark:border-gray-600 shadow-sm">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($project->name) ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($project->company_name ?? 'No Company') ?>
                                            <?php if (isset($project->owner) && !empty($project->owner)): ?>
                                                &middot; <span class="text-gray-500 dark:text-gray-400"><?= htmlspecialchars($project->owner) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isset($project->due_date) && !empty($project->due_date)): ?>
                                            <?php
                                                    $dueDate = new DateTime($project->due_date);
                                            $today = new DateTime();
                                            $interval = $today->diff($dueDate);
                                            $isPast = $dueDate < $today;
                                            $isSoon = !$isPast && $interval->days <= 7;
                                            ?>
                                            <div class="mt-2 text-xs <?= $isPast ? 'text-red-600 dark:text-red-400' : ($isSoon ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400') ?>">
                                                Due: <?= $dueDate->format('M j, Y') ?>
                                                <?php if ($isPast): ?>
                                                    <span class="text-red-600 dark:text-red-400 font-medium">(<?= $interval->days ?> days overdue)</span>
                                                <?php elseif ($isSoon): ?>
                                                    <span class="text-yellow-600 dark:text-yellow-400 font-medium">(<?= $interval->days ?> days left)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($project->tasks)): ?>
                                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                <?php
                                                $totalTasks = count($project->tasks);
                                            $completedTasks = 0;

                                            foreach ($project->tasks as $task) {
                                                if (isset($task->status_id) && $task->status_id == 6) { // Assuming 6 is completed
                                                    $completedTasks++;
                                                }
                                            }

                                            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
                                            ?>
                                                <div class="flex justify-between mb-1">
                                                    <span><?= $completedTasks ?>/<?= $totalTasks ?> tasks</span>
                                                    <span><?= round($completionRate) ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1">
                                                    <div class="bg-blue-600 h-1 rounded-full" style="width: <?= $completionRate ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                                <div class="text-3xl mb-2">📋</div>
                                <div>No projects</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterType = document.getElementById('filter-type');
        const filterStatus = document.getElementById('filter-status');
        const filterCompany = document.getElementById('filter-company');
        const filterOwner = document.getElementById('filter-owner');
        const filterValueSelects = document.querySelectorAll('.filter-value');
        
        // Show/hide the appropriate filter value dropdown based on filter type
        filterType.addEventListener('change', function() {
            // Hide all filter value dropdowns
            filterValueSelects.forEach(select => {
                select.classList.add('hidden');
            });
            
            // Show the appropriate filter value dropdown
            const selectedFilter = this.value;
            document.getElementById(`filter-${selectedFilter}`).classList.remove('hidden');
        });
        
        // Handle filter value changes
        [filterStatus, filterCompany, filterOwner].forEach(select => {
            select.addEventListener('change', function() {
                applyFilter();
            });
        });
        
        // Apply filter and navigate to filtered URL
        function applyFilter() {
            const type = filterType.value;
            let value = '';
            
            switch(type) {
                case 'status':
                    value = filterStatus.value;
                    break;
                case 'company':
                    value = filterCompany.value;
                    break;
                case 'owner':
                    value = filterOwner.value;
                    break;
            }
            
            // Create URL with filter parameters
            const url = new URL(window.location.href);
            url.searchParams.set('view', 'pivot');
            url.searchParams.set('filter_type', type);
            
            if (value) {
                url.searchParams.set('filter_value', value);
            } else {
                url.searchParams.delete('filter_value');
            }
            
            // Navigate to the new URL
            window.location.href = url.toString();
        }
    });
</script>