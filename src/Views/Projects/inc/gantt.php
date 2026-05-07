<?php
//file: Views/Projects/inc/gantt.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Get current time period filter from query string (default to quarter)
$timePeriod = $_GET['period'] ?? 'quarter';
$viewType = $_GET['gantt_type'] ?? 'projects';
$currentYear = date('Y');

// Get selected project ID (if any)
$selectedProjectId = null;
if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
    $selectedProjectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT);
    // If filter_var returns false, set to null
    if ($selectedProjectId === false) {
        $selectedProjectId = null;
    }
}
$selectedProject = null;

// Load all available projects for the dropdown if not already passed from controller
if (!isset($allProjects) || empty($allProjects)) {
    try {
        $projectModel = new \App\Models\Project();
        $allProjects = $projectModel->getAll(['is_deleted' => 0])['records'];
    } catch (\Exception $e) {
        error_log("Error loading projects for gantt dropdown: " . $e->getMessage());
        $allProjects = [];
    }
}

// If project ID is provided, get the specific project details with full data
if ($selectedProjectId !== null) {
    try {
        $projectModel = new \App\Models\Project();
        $selectedProject = $projectModel->findWithDetails($selectedProjectId);

        // If project not found or deleted, reset selection
        if (!$selectedProject || $selectedProject->is_deleted) {
            $selectedProject = null;
            $selectedProjectId = null;
        }
    } catch (\Exception $e) {
        error_log("Error loading project details for gantt: " . $e->getMessage());
        $selectedProject = null;
        $selectedProjectId = null;
    }
}

// Initialize data arrays
$tasks = [];
$milestones = [];
$sprints = [];

// Get data for the selected view type
if ($selectedProject !== null) {
    // When a specific project is selected
    if ($viewType === 'tasks' && isset($selectedProject->tasks)) {
        $tasks = $selectedProject->tasks;
    } elseif ($viewType === 'milestones' && isset($selectedProject->milestones)) {
        $milestones = $selectedProject->milestones;
    } elseif ($viewType === 'sprints' && isset($selectedProject->sprints)) {
        $sprints = $selectedProject->sprints;

        // For sprints view, we also need to load tasks for each sprint
        if (!empty($sprints)) {
            try {
                $sprintModel = new \App\Models\Sprint();
                foreach ($sprints as $sprint) {
                    if (!isset($sprint->tasks)) {
                        $sprint->tasks = $sprintModel->getSprintTasks($sprint->id);
                    }
                }
            } catch (\Exception $e) {
                error_log("Error loading sprint tasks for gantt: " . $e->getMessage());
                // Continue with sprints but without tasks
            }
        }
    }
} else {
    // When viewing all projects (default)
    $viewType = 'projects'; // Force projects view when no specific project is selected
}

// Helper functions for date calculations
function getDateRange($timePeriod, $currentYear)
{
    $ranges = [];

    switch ($timePeriod) {
        case 'year':
            return [
                ['label' => ($currentYear - 1), 'start' => "{$currentYear}-01-01", 'end' => "{$currentYear}-12-31"],
                ['label' => $currentYear, 'start' => "{$currentYear}-01-01", 'end' => "{$currentYear}-12-31"],
                ['label' => ($currentYear + 1), 'start' => "{$currentYear}-01-01", 'end' => "{$currentYear}-12-31"],
                ['label' => ($currentYear + 2), 'start' => "{$currentYear}-01-01", 'end' => "{$currentYear}-12-31"],
            ];

        case 'quarter':
            return [
                ['label' => "Q1 (Jan-Mar) {$currentYear}", 'start' => "{$currentYear}-01-01", 'end' => "{$currentYear}-03-31"],
                ['label' => "Q2 (Apr-Jun) {$currentYear}", 'start' => "{$currentYear}-04-01", 'end' => "{$currentYear}-06-30"],
                ['label' => "Q3 (Jul-Sep) {$currentYear}", 'start' => "{$currentYear}-07-01", 'end' => "{$currentYear}-09-30"],
                ['label' => "Q4 (Oct-Dec) {$currentYear}", 'start' => "{$currentYear}-10-01", 'end' => "{$currentYear}-12-31"],
            ];

        case 'month':
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            for ($i = 1; $i <= 12; $i++) {
                $monthNum = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $lastDay = date('t', strtotime("{$currentYear}-{$monthNum}-01"));
                $ranges[] = [
                    'label' => "{$months[$i - 1]} {$currentYear}",
                    'start' => "{$currentYear}-{$monthNum}-01",
                    'end' => "{$currentYear}-{$monthNum}-{$lastDay}",
                ];
            }

            return $ranges;

        case 'week':
            $currentMonth = date('n');
            $monthName = date('F');
            $daysInMonth = date('t');
            $weeksInMonth = ceil($daysInMonth / 7);

            for ($i = 1; $i <= $weeksInMonth; $i++) {
                $weekStart = ($i - 1) * 7 + 1;
                $weekEnd = min($weekStart + 6, $daysInMonth);
                $monthNum = str_pad((string)$currentMonth, 2, '0', STR_PAD_LEFT);
                $weekStartPad = str_pad((string)$weekStart, 2, '0', STR_PAD_LEFT);
                $weekEndPad = str_pad((string)$weekEnd, 2, '0', STR_PAD_LEFT);

                $ranges[] = [
                    'label' => "{$monthName} {$weekStart}-{$weekEnd}",
                    'start' => "{$currentYear}-{$monthNum}-{$weekStartPad}",
                    'end' => "{$currentYear}-{$monthNum}-{$weekEndPad}",
                ];
            }

            return $ranges;

        default:
            return $ranges;
    }
}

// Check if a date range overlaps with the given start and end dates
function isInRange($startDate, $endDate, $rangeStart, $rangeEnd)
{
    // Convert to timestamps for comparison
    $start = $startDate ? strtotime($startDate) : strtotime(date('Y-01-01'));
    $end = $endDate ? strtotime($endDate) : strtotime(date('Y-12-31'));
    $periodStart = strtotime($rangeStart);
    $periodEnd = strtotime($rangeEnd);

    return ($start <= $periodEnd && $end >= $periodStart);
}

// Get status color for visualization
function getStatusColor($statusId, $type = 'project')
{
    // Convert to integer to ensure proper comparison
    $statusId = (int) $statusId;

    if ($type === 'project') {
        switch ($statusId) {
            case 1: // Ready
                return 'bg-blue-500';
            case 2: // In Progress
                return 'bg-yellow-500';
            case 3: // Completed
                return 'bg-green-500';
            case 4: // On Hold
                return 'bg-purple-500';
            case 5: // Delayed
                return 'bg-red-500';
            case 6: // Cancelled
                return 'bg-gray-500';
            default:
                return 'bg-gray-500';
        }
    } elseif ($type === 'task') {
        switch ($statusId) {
            case 1: // Open
                return 'bg-blue-500';
            case 2: // In Progress
                return 'bg-yellow-500';
            case 3: // On Hold
                return 'bg-purple-500';
            case 4: // In Review
                return 'bg-orange-500';
            case 5: // Closed
                return 'bg-gray-500';
            case 6: // Completed
                return 'bg-green-500';
            default:
                return 'bg-gray-500';
        }
    } elseif ($type === 'milestone') {
        switch ($statusId) {
            case 1: // Not Started
                return 'bg-blue-500';
            case 2: // In Progress
                return 'bg-yellow-500';
            case 3: // Completed
                return 'bg-green-500';
            default:
                return 'bg-gray-500';
        }
    } elseif ($type === 'sprint') {
        switch ($statusId) {
            case 1: // Planning
                return 'bg-blue-500';
            case 2: // Active
                return 'bg-yellow-500';
            case 3: // Completed
                return 'bg-green-500';
            case 4: // Cancelled
                return 'bg-gray-500';
            case 5: // Delayed
                return 'bg-red-500';
            default:
                return 'bg-gray-500';
        }
    }

    return 'bg-gray-500';
}

// Calculate the time periods based on selected view
$dateRanges = getDateRange($timePeriod, $currentYear);
?>

<!-- Timeline View -->
<div class="bg-white dark:bg-gray-800 rounded-md shadow-sm p-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 space-y-4 md:space-y-0">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            <?php if ($selectedProject !== null): ?>
                Gantt Chart: <?= htmlspecialchars($selectedProject->name) ?>
            <?php else: ?>
                Projects Gantt Chart
            <?php endif; ?>
        </h2>
        
        <!-- Project selection and filtering -->
        <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">
            <!-- Project Selector Dropdown -->
            <form action="" method="GET" class="inline-flex items-center">
                <input type="hidden" name="view" value="gantt">
                <input type="hidden" name="period" value="<?= $timePeriod ?>">
                <input type="hidden" name="gantt_type" value="<?= $viewType ?>">
                
                <select id="project_id" name="project_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">All Projects</option>
                    <?php if (!empty($allProjects)): foreach ($allProjects as $project): ?>
                        <option value="<?= $project->id ?>" <?= ($selectedProjectId == $project->id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project->name) ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
                <button type="submit" class="ml-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                    View
                </button>
            </form>
            
            <!-- Data Type Selection (only shows when project is selected) -->
            <?php if ($selectedProject !== null): ?>
            <div class="inline-flex rounded-md shadow-sm" role="group">
                <a href="?view=gantt&period=<?= $timePeriod ?>&project_id=<?= $selectedProjectId ?>&gantt_type=projects" class="px-4 py-2 text-sm font-medium rounded-l-lg <?= $viewType === 'projects' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                    Project
                </a>
                <a href="?view=gantt&period=<?= $timePeriod ?>&project_id=<?= $selectedProjectId ?>&gantt_type=tasks" class="px-4 py-2 text-sm font-medium <?= $viewType === 'tasks' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                    Tasks
                </a>
                <a href="?view=gantt&period=<?= $timePeriod ?>&project_id=<?= $selectedProjectId ?>&gantt_type=milestones" class="px-4 py-2 text-sm font-medium <?= $viewType === 'milestones' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                    Milestones
                </a>
                <a href="?view=gantt&period=<?= $timePeriod ?>&project_id=<?= $selectedProjectId ?>&gantt_type=sprints" class="px-4 py-2 text-sm font-medium rounded-r-lg <?= $viewType === 'sprints' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                    Sprints
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Time period filters -->
        <div class="flex space-x-2">
            <a href="?view=gantt&period=year<?= $selectedProjectId ? "&project_id={$selectedProjectId}" : "" ?><?= $selectedProjectId ? "&gantt_type={$viewType}" : "" ?>" class="px-4 py-2 rounded-md text-sm font-medium <?= $timePeriod === 'year' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                Year
            </a>
            <a href="?view=gantt&period=quarter<?= $selectedProjectId ? "&project_id={$selectedProjectId}" : "" ?><?= $selectedProjectId ? "&gantt_type={$viewType}" : "" ?>" class="px-4 py-2 rounded-md text-sm font-medium <?= $timePeriod === 'quarter' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                Quarter
            </a>
            <a href="?view=gantt&period=month<?= $selectedProjectId ? "&project_id={$selectedProjectId}" : "" ?><?= $selectedProjectId ? "&gantt_type={$viewType}" : "" ?>" class="px-4 py-2 rounded-md text-sm font-medium <?= $timePeriod === 'month' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                Month
            </a>
            <a href="?view=gantt&period=week<?= $selectedProjectId ? "&project_id={$selectedProjectId}" : "" ?><?= $selectedProjectId ? "&gantt_type={$viewType}" : "" ?>" class="px-4 py-2 rounded-md text-sm font-medium <?= $timePeriod === 'week' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                Week
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <div class="min-w-[1200px]">
            <!-- Timeline header -->
            <div class="flex border-b border-gray-200 dark:border-gray-700">
                <div class="w-1/4 p-3 font-medium text-gray-700 dark:text-gray-300">
                    <?php if ($viewType === 'projects'): ?>
                        Project
                    <?php elseif ($viewType === 'tasks'): ?>
                        Task
                    <?php elseif ($viewType === 'milestones'): ?>
                        Milestone
                    <?php elseif ($viewType === 'sprints'): ?>
                        Sprint
                    <?php endif; ?>
                </div>

                <?php foreach ($dateRanges as $range): ?>
                    <div class="flex-1 p-3 text-center border-l border-gray-200 dark:border-gray-700 font-medium text-gray-700 dark:text-gray-300">
                        <?= $range['label'] ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- PROJECTS GANTT -->
            <?php if ($viewType === 'projects'): ?>
                <?php
                // Determine which projects to display
                $projectsToDisplay = [];
                if ($selectedProject !== null) {
                    // Show only the selected project
                    $projectsToDisplay = [$selectedProject];
                } else {
                    // Show all projects
                    $projectsToDisplay = $projects ?? [];
                }
?>

                <?php if (!empty($projectsToDisplay)): ?>
                    <?php foreach ($projectsToDisplay as $project): ?>
                        <div class="flex border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="w-1/4 p-3">
                                <a href="/projects/view/<?= $project->id ?>" class="group">
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                        <?= htmlspecialchars($project->name) ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= htmlspecialchars($project->company_name ?? '') ?>
                                    </div>
                                </a>
                            </div>

                            <?php foreach ($dateRanges as $range):
                                $inRange = isInRange($project->start_date ?? null, $project->end_date ?? null, $range['start'], $range['end']);
                                $statusColor = getStatusColor($project->status_id ?? 0, 'project');
                                ?>
                                <div class="flex-1 p-3 border-l border-gray-200 dark:border-gray-700 relative">
                                    <?php if ($inRange): ?>
                                        <a href="/projects/view/<?= $project->id ?>" class="block">
                                            <div class="h-6 <?= $statusColor ?> rounded-md hover:opacity-80 transition-opacity tooltip" data-tooltip="<?= htmlspecialchars($project->name) ?>"></div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-400">
                        <?php if ($selectedProject !== null): ?>
                            Project not found or has no data to display.
                        <?php else: ?>
                            No projects found. Create your first project to visualize the gantt.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <!-- TASKS GANTT -->
            <?php elseif ($viewType === 'tasks'): ?>
                <?php if (!empty($tasks)): ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="flex border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="w-1/4 p-3">
                                <a href="/tasks/view/<?= $task->id ?>" class="group">
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                        <?= htmlspecialchars($task->title) ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= ucfirst(htmlspecialchars($task->priority ?? 'none')) ?> Priority
                                    </div>
                                </a>
                            </div>
                            
                            <?php foreach ($dateRanges as $range):
                                $inRange = isInRange($task->start_date ?? null, $task->due_date ?? null, $range['start'], $range['end']);
                                $statusColor = getStatusColor($task->status_id ?? 0, 'task');
                                ?>
                                <div class="flex-1 p-3 border-l border-gray-200 dark:border-gray-700 relative">
                                    <?php if ($inRange): ?>
                                        <a href="/tasks/view/<?= $task->id ?>" class="block">
                                            <div class="h-6 <?= $statusColor ?> rounded-md hover:opacity-80 transition-opacity tooltip" data-tooltip="<?= htmlspecialchars($task->title) ?>"></div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-400">
                        No tasks found for the selected project.
                    </div>
                <?php endif; ?>
                
            <!-- MILESTONES GANTT -->
            <?php elseif ($viewType === 'milestones'): ?>
                <?php if (!empty($milestones)): ?>
                    <?php foreach ($milestones as $milestone): ?>
                        <div class="flex border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="w-1/4 p-3">
                                <a href="/milestones/view/<?= $milestone->id ?>" class="group">
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                        <?= htmlspecialchars($milestone->title) ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= ucfirst(htmlspecialchars($milestone->milestone_type ?? 'milestone')) ?>
                                    </div>
                                </a>
                            </div>
                            
                            <?php foreach ($dateRanges as $range):
                                $inRange = isInRange($milestone->start_date ?? null, $milestone->due_date ?? null, $range['start'], $range['end']);
                                $statusColor = getStatusColor($milestone->status_id ?? 0, 'milestone');
                                ?>
                                <div class="flex-1 p-3 border-l border-gray-200 dark:border-gray-700 relative">
                                    <?php if ($inRange): ?>
                                        <a href="/milestones/view/<?= $milestone->id ?>" class="block">
                                            <div class="h-6 <?= $statusColor ?> rounded-md hover:opacity-80 transition-opacity tooltip" data-tooltip="<?= htmlspecialchars($milestone->title) ?>"></div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-400">
                        No milestones found for the selected project.
                    </div>
                <?php endif; ?>
                
            <!-- SPRINTS GANTT -->
            <?php elseif ($viewType === 'sprints'): ?>
                <?php if (!empty($sprints)): ?>
                    <?php foreach ($sprints as $sprint): ?>
                        <div class="flex border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="w-1/4 p-3">
                                <a href="/sprints/view/<?= $sprint->id ?>" class="group">
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                        <?= htmlspecialchars($sprint->name) ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= htmlspecialchars($sprint->status_name ?? '') ?>
                                    </div>
                                </a>
                            </div>
                            
                            <?php foreach ($dateRanges as $range):
                                $inRange = isInRange($sprint->start_date ?? null, $sprint->end_date ?? null, $range['start'], $range['end']);
                                $statusColor = getStatusColor($sprint->status_id ?? 0, 'sprint');
                                ?>
                                <div class="flex-1 p-3 border-l border-gray-200 dark:border-gray-700 relative">
                                    <?php if ($inRange): ?>
                                        <a href="/sprints/view/<?= $sprint->id ?>" class="block">
                                            <div class="h-6 <?= $statusColor ?> rounded-md hover:opacity-80 transition-opacity tooltip" data-tooltip="<?= htmlspecialchars($sprint->name) ?>"></div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (isset($sprint->tasks) && !empty($sprint->tasks)): ?>
                            <!-- Sprint Tasks Subgrid -->
                            <?php foreach ($sprint->tasks as $task): ?>
                                <div class="flex border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 bg-gray-50 dark:bg-gray-900/20 transition-colors">
                                    <div class="w-1/4 p-3 pl-8">
                                        <a href="/tasks/view/<?= $task->id ?>" class="group">
                                            <div class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                                <?= htmlspecialchars($task->title) ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                Sprint Task
                                            </div>
                                        </a>
                                    </div>
                                    
                                    <?php foreach ($dateRanges as $range):
                                        $inRange = isInRange($task->start_date ?? null, $task->due_date ?? null, $range['start'], $range['end']);
                                        $statusColor = getStatusColor($task->status_id ?? 0, 'task');
                                        ?>
                                        <div class="flex-1 p-3 border-l border-gray-200 dark:border-gray-700 relative">
                                            <?php if ($inRange): ?>
                                                <a href="/tasks/view/<?= $task->id ?>" class="block">
                                                    <div class="h-4 <?= $statusColor ?> rounded-md hover:opacity-80 transition-opacity tooltip" data-tooltip="<?= htmlspecialchars($task->title) ?>"></div>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-400">
                        No sprints found for the selected project.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add tooltip styles -->
<style>
    .tooltip {
        position: relative;
    }
    
    .tooltip:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 100%;
        margin-bottom: 5px;
        background-color: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 10;
    }
    
    .tooltip:hover::before {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 100%;
        border-width: 5px;
        border-style: solid;
        border-color: rgba(0, 0, 0, 0.8) transparent transparent transparent;
        z-index: 10;
    }
</style>