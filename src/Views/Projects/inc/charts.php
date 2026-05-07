<?php
//file: Views/Projects/inc/charts.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Helper function to get a distinct color for each data point
function getChartColor($index)
{
    $colors = [
        '#3B82F6', // blue
        '#F59E0B', // yellow
        '#10B981', // green
        '#8B5CF6', // purple
        '#EF4444', // red
        '#6B7280', // gray
        '#EC4899', // pink
        '#14B8A6', // teal
        '#F97316', // orange
        '#8B5CF6',  // indigo
    ];

    return $colors[$index % count($colors)];
}

// Get current project ID (if any) from URL parameters
$selectedProjectId = isset($_GET['project_id']) ? filter_var($_GET['project_id'], FILTER_VALIDATE_INT) : null;
$selectedProject = null;

// Default values for data structures
$statusLabels = ['Ready', 'In Progress', 'Completed', 'On Hold', 'Delayed', 'Cancelled'];
$statusCounts = array_fill(0, count($statusLabels), 0);
$statusColors = [
    '#3B82F6', // blue
    '#F59E0B', // yellow
    '#10B981', // green
    '#8B5CF6', // purple
    '#EF4444', // red
    '#6B7280',  // gray
];

$projectBudgets = [];
$projectSpent = [];
$projectNames = [];
$projectIds = [];

$completedProjectDurations = [];
$inProgressProjectDurations = [];

$taskStatusCounts = [];
$taskPriorityCounts = ['none' => 0, 'low' => 0, 'medium' => 0, 'high' => 0];
$taskCompletionTrend = [];

$teamMemberWorkload = [];
$monthlyProjectProgress = [];
$clientProjectCounts = [];

// Load all available projects for the dropdown if not already passed from controller
if (!isset($allProjects) || empty($allProjects)) {
    $projectModel = new \App\Models\Project();
    $allProjects = $projectModel->getAll(['is_deleted' => 0])['records'];
}

// Process projects data only if we have projects to process
if (!empty($projects)) {
    // Find selected project if specified
    if ($selectedProjectId !== null) {
        foreach ($projects as $project) {
            if ($project->id == $selectedProjectId) {
                $selectedProject = $project;

                break;
            }
        }
    }

    // Determine which projects to process
    $projectsToProcess = ($selectedProject !== null) ? [$selectedProject] : $projects;

    // CLIENT CHART: Only for all-projects view
    if ($selectedProject === null) {
        $clientProjectCounts = [];
        foreach ($projects as $project) {
            $companyName = $project->company_name ?? ($project->company->name ?? 'Unknown Company');
            if (!isset($clientProjectCounts[$companyName])) {
                $clientProjectCounts[$companyName] = 0;
            }
            $clientProjectCounts[$companyName]++;
        }
        // Sort by count descending and get top 5
        arsort($clientProjectCounts);
        $clientProjectCounts = array_slice($clientProjectCounts, 0, 5, true);
    }

    // Process each project in batch
    foreach ($projectsToProcess as $project) {
        $projectId = $project->id ?? 0;
        $projectIds[] = $projectId;

        // Count by status (for status chart)
        $statusId = (int)($project->status_id ?? 0);
        if ($statusId > 0 && $statusId <= count($statusLabels)) {
            $statusCounts[$statusId - 1]++;
        }

        $projectName = htmlspecialchars($project->name ?? 'Unnamed Project');
        $projectNames[] = $projectName;

        // Get tasks if needed and available
        $tasks = null;
        if ($selectedProject !== null) {
            $tasks = $project->tasks ?? $selectedProject->tasks ?? null;
        } elseif (isset($project->tasks)) {
            $tasks = $project->tasks;
        }

        // Only process tasks if we actually have them
        $estimatedTime = 0;
        $spentTime = 0;

        if (!empty($tasks)) {
            // Process task data for charts
            if ($selectedProject !== null) {
                // Reset counters for single project view
                $taskStatusCounts = [];
                $taskPriorityCounts = ['none' => 0, 'low' => 0, 'medium' => 0, 'high' => 0];

                // Prepare month labels for completion trend
                // Only generate this once, not per task
                $sixMonthsAgo = (new DateTime())->modify('-5 months')->modify('first day of this month');
                $currentMonth = new DateTime('first day of this month');
                $interval = new DateInterval('P1M');
                $period = new DatePeriod($sixMonthsAgo, $interval, $currentMonth);

                $monthLabels = [];
                $completedCounts = [];

                foreach ($period as $month) {
                    $monthLabels[] = $month->format('M Y');
                    $completedCounts[] = 0;
                }
                $monthLabels[] = $currentMonth->format('M Y');
                $completedCounts[] = 0;

                // Initialize team member workload array
                $teamMemberWorkload = [];
            }

            // Process tasks using array operations when possible
            foreach ($tasks as $task) {
                $estimatedTime += (float)($task->estimated_time ?? 0);
                $spentTime += (float)($task->time_spent ?? 0);

                // Only process extra task details for selected project view
                if ($selectedProject !== null) {
                    // Count by task status
                    $taskStatusName = $task->status_name ?? 'Unknown';
                    if (!isset($taskStatusCounts[$taskStatusName])) {
                        $taskStatusCounts[$taskStatusName] = 0;
                    }
                    $taskStatusCounts[$taskStatusName]++;

                    // Count by priority
                    $priority = $task->priority ?? 'none';
                    $taskPriorityCounts[$priority]++;

                    // Track completion trend if task is completed
                    if (($task->status_id ?? 0) == 6 && isset($task->complete_date) && !empty($task->complete_date)) {
                        $completeDate = new DateTime($task->complete_date);
                        $monthDate = null;

                        // Find which month this task completion falls into
                        for ($i = 0; $i < count($monthLabels); $i++) {
                            $monthDate = DateTime::createFromFormat('M Y', $monthLabels[$i]);
                            $nextMonth = clone $monthDate;
                            $nextMonth->modify('+1 month');

                            if ($completeDate >= $monthDate && $completeDate < $nextMonth) {
                                $completedCounts[$i]++;

                                break;
                            }
                        }
                    }

                    // Track team member workload (for assignees)
                    if (isset($task->assigned_to) && !empty($task->assigned_to)) {
                        $assigneeName = isset($task->first_name) && isset($task->last_name) ?
                            "{$task->first_name} {$task->last_name}" : "User #{$task->assigned_to}";

                        if (!isset($teamMemberWorkload[$assigneeName])) {
                            $teamMemberWorkload[$assigneeName] = [
                                'assigned' => 0,
                                'completed' => 0,
                                'hours_logged' => 0,
                            ];
                        }

                        $teamMemberWorkload[$assigneeName]['assigned']++;

                        if (($task->status_id ?? 0) == 6) { // Completed
                            $teamMemberWorkload[$assigneeName]['completed']++;
                        }

                        $teamMemberWorkload[$assigneeName]['hours_logged'] += ($task->time_spent ?? 0) / 3600;
                    }
                }
            }

            // Save completion trend data for selected project
            if ($selectedProject !== null) {
                $taskCompletionTrend = [
                    'labels' => $monthLabels,
                    'counts' => $completedCounts,
                ];
            }
        }

        // Convert time to hours for charts
        $projectBudgets[] = $estimatedTime / 3600;
        $projectSpent[] = $spentTime / 3600;

        // Calculate project duration if dates are available
        if (isset($project->start_date) && !empty($project->start_date)) {
            $startDate = new DateTime($project->start_date);

            // End date could be actual end date or today for in-progress projects
            $endDate = null;
            if (isset($project->end_date) && !empty($project->end_date)) {
                $endDate = new DateTime($project->end_date);
            } else {
                $endDate = new DateTime();
            }

            $duration = $startDate->diff($endDate)->days;

            // Categorize by project status
            if ($statusId == 3) { // Completed
                $completedProjectDurations[] = [
                    'name' => $projectName,
                    'duration' => $duration,
                ];
            } elseif ($statusId == 2) { // In Progress
                $inProgressProjectDurations[] = [
                    'name' => $projectName,
                    'duration' => $duration,
                ];
            }

            // Calculate monthly progress for selected project
            if ($selectedProject !== null && $project->id == $selectedProjectId) {
                // Get starting month (project start or 6 months ago, whichever is more recent)
                $startMonth = clone $startDate;
                $startMonth->modify('first day of this month');

                $sixMonthsAgo = (new DateTime())->modify('-5 months')->modify('first day of this month');
                $startingMonth = ($startMonth > $sixMonthsAgo) ? $startMonth : $sixMonthsAgo;

                $currentMonth = new DateTime('first day of this month');
                $interval = new DateInterval('P1M');
                $period = new DatePeriod($startingMonth, $interval, $currentMonth);

                $progressLabels = [];
                $progressValues = [];

                // Calculate project duration once
                $projectEndDate = $endDate;
                $totalDuration = $startDate->diff($projectEndDate)->days;
                if ($totalDuration == 0) {
                    $totalDuration = 1;
                } // Avoid division by zero

                foreach ($period as $month) {
                    $progressLabels[] = $month->format('M Y');
                    $elapsedDays = $startDate->diff($month)->days;
                    $progress = min(100, ($elapsedDays / $totalDuration) * 100);
                    $progressValues[] = round($progress, 1);
                }

                // Add current month
                $progressLabels[] = $currentMonth->format('M Y');
                $elapsedDays = $startDate->diff(new DateTime())->days;
                $progress = min(100, ($elapsedDays / $totalDuration) * 100);
                $progressValues[] = round($progress, 1);

                $monthlyProjectProgress = [
                    'labels' => $progressLabels,
                    'values' => $progressValues,
                ];
            }
        }
    }
}

// Sort duration arrays once after all processing
if (!empty($completedProjectDurations)) {
    usort($completedProjectDurations, function ($a, $b) {
        return $b['duration'] - $a['duration'];
    });
    $completedProjectDurations = array_slice($completedProjectDurations, 0, 5);
}

if (!empty($inProgressProjectDurations)) {
    usort($inProgressProjectDurations, function ($a, $b) {
        return $b['duration'] - $a['duration'];
    });
    $inProgressProjectDurations = array_slice($inProgressProjectDurations, 0, 5);
}

// Sort team member workload
if (!empty($teamMemberWorkload)) {
    uasort($teamMemberWorkload, function ($a, $b) {
        return $b['assigned'] - $a['assigned'];
    });
    $teamMemberWorkload = array_slice($teamMemberWorkload, 0, 5, true);
}
?>

<!-- Charts Container -->
<div class="space-y-6">
    <!-- Top Title Section with Project Selector -->
    <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6">
        <div class="flex flex-wrap justify-between items-center mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    <?php if ($selectedProject !== null): ?>
                        <?= htmlspecialchars($selectedProject->name) ?>
                    <?php else: ?>    
                        Project Analytics
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    <?php if ($selectedProject === null): ?>
                        Data visualizations to help understand project distribution, client relationships, and resource allocation.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Project Selector Dropdown -->
            <div>
                <form action="" method="GET" class="flex space-x-2 items-center">
                    <input type="hidden" name="view" value="charts">
                    <label for="project_id" class="text-sm text-gray-700 dark:text-gray-300">Select Project:</label>
                    <select id="project_id" name="project_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                        <option value="">All Projects</option>
                        <?php if (!empty($allProjects)): foreach ($allProjects as $project): ?>
                            <option value="<?= $project->id ?>" <?= ($selectedProjectId == $project->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project->name) ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                        View
                    </button>
                </form>
            </div>
        </div>
        
        <?php if ($selectedProject !== null): ?>
        <!-- Project Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Status</h3>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    <?= htmlspecialchars($statusLabels[($selectedProject->status_id ?? 1) - 1] ?? 'Unknown') ?>
                </p>
            </div>
            
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-green-800 dark:text-green-300">Tasks</h3>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                    <?= is_countable($selectedProject->tasks ?? []) ? count($selectedProject->tasks) : 0 ?>
                </p>
            </div>
            
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Duration</h3>
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                    <?php
                    $durationText = 'N/A';
            if (isset($selectedProject->start_date) && !empty($selectedProject->start_date)) {
                $startDate = new DateTime($selectedProject->start_date);
                $endDate = isset($selectedProject->end_date) && !empty($selectedProject->end_date) ?
                    new DateTime($selectedProject->end_date) : new DateTime();
                $duration = $startDate->diff($endDate)->days;
                $durationText = $duration . ' days';
            }
            echo $durationText;
            ?>
                </p>
            </div>
            
            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-purple-800 dark:text-purple-300">Completion</h3>
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    <?php
            // Calculate completion based on tasks
            $completionRate = 0;
            if (isset($selectedProject->tasks) && is_countable($selectedProject->tasks) && !empty($selectedProject->tasks)) {
                $totalTasks = count($selectedProject->tasks);
                $completedTasks = 0;

                foreach ($selectedProject->tasks as $task) {
                    if (($task->status_id ?? 0) == 6) { // Completed status
                        $completedTasks++;
                    }
                }

                $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            }
            echo $completionRate . '%';
            ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($selectedProject === null): /* ALL PROJECTS VIEW */ ?>
    <!-- First Row - Status & Budget -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Projects by Status Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Projects by Status</h2>
            
            <?php if (array_sum($statusCounts) > 0): ?>
                <div class="h-64">
                    <canvas id="statusChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No status data available.
                </div>
            <?php endif; ?>
        </div>

        <!-- Budget vs. Spent Chart (using task time estimates) -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Estimated vs. Actual Hours</h2>
            
            <?php if (!empty($projectNames) && !empty($projectBudgets)): ?>
                <div class="h-64">
                    <canvas id="budgetChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No time data available.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Second Row - Project Duration & Client Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Project Duration Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Project Durations (Days)</h2>
            
            <?php if (!empty($completedProjectDurations) || !empty($inProgressProjectDurations)): ?>
                <div class="h-64">
                    <canvas id="durationChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No duration data available.
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Clients Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Top Clients by Project Count</h2>
            
            <?php if (!empty($clientProjectCounts)): ?>
                <div class="h-64">
                    <canvas id="clientChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No client data available.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: /* SINGLE PROJECT VIEW */ ?>
    <!-- First Row - Task Status & Priority -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tasks by Status Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tasks by Status</h2>
            
            <?php if (!empty($taskStatusCounts)): ?>
                <div class="h-64">
                    <canvas id="taskStatusChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No task status data available.
                </div>
            <?php endif; ?>
        </div>

        <!-- Tasks by Priority Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tasks by Priority</h2>
            
            <?php if (!empty($taskPriorityCounts)): ?>
                <div class="h-64">
                    <canvas id="taskPriorityChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No task priority data available.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Second Row - Time Tracking & Completion Trend -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Estimated vs. Actual Hours -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Time Tracking</h2>
            
            <?php if (!empty($projectNames) && !empty($projectBudgets) && !empty($projectSpent)): ?>
                <div class="h-64">
                    <canvas id="timeTrackingChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No time tracking data available.
                </div>
            <?php endif; ?>
        </div>

        <!-- Task Completion Trend -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Task Completion Trend</h2>
            
            <?php if (!empty($taskCompletionTrend)): ?>
                <div class="h-64">
                    <canvas id="completionTrendChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No task completion trend data available.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Third Row - Team Member Workload & Project Progress -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Team Member Workload -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Team Member Workload</h2>
            
            <?php if (!empty($teamMemberWorkload)): ?>
                <div class="h-64">
                    <canvas id="teamWorkloadChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No team workload data available.
                </div>
            <?php endif; ?>
        </div>

        <!-- Monthly Project Progress -->
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Monthly Progress</h2>
            
            <?php if (!empty($monthlyProjectProgress)): ?>
                <div class="h-64">
                    <canvas id="monthlyProgressChart"></canvas>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                    No monthly progress data available.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Load Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Chart.js to load
    if (typeof Chart === 'undefined') {
        const chartScript = document.querySelector('script[src*="chart.js"]');
        if (chartScript) {
            chartScript.onload = initCharts;
        } else {
            // Fallback - try again after a short delay
            setTimeout(initCharts, 500);
        }
    } else {
        initCharts();
    }
});

function initCharts() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded');
        return;
    }
    
    // Set theme-aware defaults based on dark mode state
    const isDarkMode = document.documentElement.classList.contains('dark');
    const fontColor = isDarkMode ? '#E5E7EB' : 'white';
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(88, 88, 88, 0.58)';

    Chart.defaults.color = fontColor;
    Chart.defaults.scale.grid.color = gridColor;

    // Common options
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    boxWidth: 12,
                    color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'white'
                }
            },
            tooltip: {
                backgroundColor: isDarkMode ? '#374151' : 'white',
                titleColor: isDarkMode ? '#F3F4F6' : '#111827',
                bodyColor: isDarkMode ? '#D1D5DB' : '#374151',
                borderColor: isDarkMode ? '#4B5563' : '#E5E7EB',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 6,
                displayColors: true,
                usePointStyle: true
            }
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        }
    };

    // Function to create charts
    function createChart(elementId, config) {
        const ctx = document.getElementById(elementId);
        if (!ctx) return null;
        
        // Add animation if not already defined
        if (!config.options.animation) {
            config.options.animation = {
                duration: 1000,
                easing: 'easeOutQuart'
            };
        }
        
        return new Chart(ctx, config);
    }

    // ALL PROJECTS VIEW CHARTS
    <?php if ($selectedProject === null): ?>
    
    // Status Chart (Pie)
    <?php if (array_sum($statusCounts) > 0): ?>
    createChart('statusChart', {
        type: 'pie',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: <?= json_encode($statusColors) ?>,
                borderColor: isDarkMode ? '#1F2937' : 'white',
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            cutout: '30%',
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Budget vs. Spent Chart (Bar)
    <?php if (!empty($projectNames) && !empty($projectBudgets)): ?>
    createChart('budgetChart', {
        type: 'bar',
        data: {
            labels: <?= json_encode($projectNames) ?>,
            datasets: [
                {
                    label: 'Estimated Hours',
                    data: <?= json_encode($projectBudgets) ?>,
                    backgroundColor: '#3B82F6', // blue
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                },
                {
                    label: 'Actual Hours',
                    data: <?= json_encode($projectSpent) ?>,
                    backgroundColor: '#10B981', // green
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                }
            ]
        },
        options: {
            ...commonOptions,
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                }
            },
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.raw || 0;
                            return `${label}: ${value} hours`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Project Durations Chart
    <?php if (!empty($completedProjectDurations) || !empty($inProgressProjectDurations)): ?>
    createChart('durationChart', {
        type: 'bar',
        data: {
            labels: [
                ...<?= json_encode(array_column($completedProjectDurations, 'name')) ?>,
                ...<?= json_encode(array_column($inProgressProjectDurations, 'name')) ?>
            ],
            datasets: [
                {
                    label: 'Completed Projects',
                    data: [
                        ...<?= json_encode(array_column($completedProjectDurations, 'duration')) ?>,
                        ...Array(<?= count($inProgressProjectDurations) ?>).fill(0)
                    ],
                    backgroundColor: '#10B981', // green
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                },
                {
                    label: 'In-Progress Projects',
                    data: [
                        ...Array(<?= count($completedProjectDurations) ?>).fill(0),
                        ...<?= json_encode(array_column($inProgressProjectDurations, 'duration')) ?>
                    ],
                    backgroundColor: '#F59E0B', // yellow
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                }
            ]
        },
        options: {
            ...commonOptions,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Days'
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Top Clients Chart
    <?php if (!empty($clientProjectCounts)): ?>
    createChart('clientChart', {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($clientProjectCounts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($clientProjectCounts)) ?>,
                backgroundColor: Array.from(
                    { length: <?= count($clientProjectCounts) ?> }, 
                    (_, i) => getChartColor(i)
                ),
                borderColor: isDarkMode ? '#1F2937' : 'white',
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            cutout: '50%',
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} projects (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // SINGLE PROJECT VIEW CHARTS
    <?php else: ?>
    
    // Task Status Chart
    <?php if (!empty($taskStatusCounts)): ?>
    createChart('taskStatusChart', {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($taskStatusCounts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($taskStatusCounts)) ?>,
                backgroundColor: Array.from(
                    { length: <?= count($taskStatusCounts) ?> }, 
                    (_, i) => getChartColor(i)
                ),
                borderColor: isDarkMode ? '#1F2937' : 'white',
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            cutout: '30%',
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} tasks (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Task Priority Chart
    <?php if (!empty($taskPriorityCounts)): ?>
    createChart('taskPriorityChart', {
        type: 'doughnut',
        data: {
            labels: ['None', 'Low', 'Medium', 'High'],
            datasets: [{
                data: [
                    <?= $taskPriorityCounts['none'] ?? 0 ?>,
                    <?= $taskPriorityCounts['low'] ?? 0 ?>,
                    <?= $taskPriorityCounts['medium'] ?? 0 ?>,
                    <?= $taskPriorityCounts['high'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#9CA3AF', // gray
                    '#60A5FA', // light blue
                    '#F59E0B', // yellow
                    '#EF4444'  // red
                ],
                borderColor: isDarkMode ? '#1F2937' : 'white',
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            cutout: '50%',
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label} Priority: ${value} tasks (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Time Tracking Chart
    <?php if (!empty($projectBudgets) && !empty($projectSpent)): ?>
    createChart('timeTrackingChart', {
        type: 'bar',
        data: {
            labels: ['Time Tracking'],
            datasets: [
                {
                    label: 'Estimated Hours',
                    data: [<?= $projectBudgets[0] ?? 0 ?>],
                    backgroundColor: '#3B82F6', // blue
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                },
                {
                    label: 'Actual Hours',
                    data: [<?= $projectSpent[0] ?? 0 ?>],
                    backgroundColor: '#10B981', // green
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                }
            ]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Task Completion Trend Chart
    <?php if (!empty($taskCompletionTrend)): ?>
    createChart('completionTrendChart', {
        type: 'line',
        data: {
            labels: <?= json_encode($taskCompletionTrend['labels']) ?>,
            datasets: [{
                label: 'Completed Tasks',
                data: <?= json_encode($taskCompletionTrend['counts']) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Tasks'
                    },
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Team Workload Chart
    <?php if (!empty($teamMemberWorkload)): ?>
    createChart('teamWorkloadChart', {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($teamMemberWorkload)) ?>,
            datasets: [
                {
                    label: 'Assigned Tasks',
                    data: <?= json_encode(array_map(function ($member) { return $member['assigned']; }, $teamMemberWorkload)) ?>,
                    backgroundColor: '#3B82F6', // blue
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                },
                {
                    label: 'Completed Tasks',
                    data: <?= json_encode(array_map(function ($member) { return $member['completed']; }, $teamMemberWorkload)) ?>,
                    backgroundColor: '#10B981', // green
                    borderColor: isDarkMode ? '#1F2937' : 'white',
                    borderWidth: 1
                }
            ]
        },
        options: {
            ...commonOptions,
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Tasks'
                    },
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Monthly Progress Chart
    <?php if (!empty($monthlyProjectProgress)): ?>
    createChart('monthlyProgressChart', {
        type: 'line',
        data: {
            labels: <?= json_encode($monthlyProjectProgress['labels']) ?>,
            datasets: [{
                label: 'Project Progress',
                data: <?= json_encode($monthlyProjectProgress['values']) ?>,
                borderColor: '#8B5CF6', // purple
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Progress (%)'
                    }
                }
            },
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.raw || 0;
                            return `${label}: ${value}%`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php endif; ?>
}

// Function to get chart color
function getChartColor(index) {
    const colors = [
        '#3B82F6', // blue
        '#F59E0B', // yellow
        '#10B981', // green
        '#8B5CF6', // purple
        '#EF4444', // red
        '#6B7280', // gray
        '#EC4899', // pink
        '#14B8A6', // teal
        '#F97316', // orange
        '#8B5CF6'  // indigo
    ];
    
    return colors[index % colors.length];
}
</script>