<?php
//file: Views/Projects/view.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;
use App\Utils\Time;

// Include view helpers for permission functions and formatting
require_once BASE_PATH . '/../src/views/Layouts/ViewHelpers.php';

// Using centralized status helper system
$statusInfo = getProjectStatusInfo($project->status_id);

// Calculate progress for tasks
$totalTasks = count($project->tasks ?? []);
$completedTasks = 0;
$totalTime = 0;

if (isset($project->tasks) && !empty($project->tasks)) {
    foreach ($project->tasks as $task) {
        if (isset($task->status_id) && $task->status_id == 6) { // Completed status
            $completedTasks++;
        }
        $totalTime += isset($task->time_spent) ? (int)$task->time_spent : 0;
    }
}

$taskProgressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

// Get description view mode from URL parameter, default to rendered
$viewMode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'rendered';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project->name) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
    <style>
        #rendered-description * {
            color: #fff;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>

        <!-- Page Header with Breadcrumb and Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <!-- Breadcrumb Section -->
            <div class="flex-1">
                <?php include BASE_PATH . '/../src/Views/Layouts/breadcrumb.php'; ?>
            </div>

            <!-- Action Buttons Section -->
            <div class="flex-shrink-0 flex space-x-3">
                <?php if (isset($currentUser['permissions']) && in_array('edit_projects', $currentUser['permissions'])): ?>
                    <a href="/projects/edit/<?= $project->id ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        Edit Project
                    </a>
                <?php endif; ?>
                <?php if (isset($currentUser['permissions']) && in_array('create_tasks', $currentUser['permissions'])): ?>
                    <a href="/tasks/create?project_id=<?= $project->id ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add Task
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Project Header -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mr-3"><?= htmlspecialchars($project->name) ?></h1>
                    <button class="favorite-star text-gray-400 hover:text-yellow-400 transition-colors mr-3"
                            data-type="project"
                            data-item-id="<?= $project->id ?>"
                            data-title="<?= htmlspecialchars($project->name) ?>"
                            data-icon="📁"
                            title="Add to favorites">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </button>
                    <?= renderStatusPill($statusInfo['label'], $statusInfo['color'], 'md') ?>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    <?php if (isset($project->company_name)): ?>
                        <?= htmlspecialchars($project->company_name) ?> •
                    <?php endif; ?>
                    Created <?= date('M j, Y', strtotime($project->created_at)) ?>
                </p>
            </div>
        </div>

        <!-- Project Details Grid - 1/4 for overview, 3/4 for content -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Project Overview Column - Left side, 1/4 width -->
            <div class="md:col-span-1">
                <!-- Project Details Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Project Details</h3>
                        <button type="button" class="section-toggle" data-target="project-details">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="project-details" class="p-4 space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Owner:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= isset($project->owner_firstname) ? htmlspecialchars($project->owner_firstname . ' ' . $project->owner_lastname) : 'Not assigned' ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Status:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= $statusInfo['label'] ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Start Date:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= isset($project->start_date) && !empty($project->start_date) ? date('M j, Y', strtotime($project->start_date)) : 'Not set' ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">End Date:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= isset($project->end_date) && !empty($project->end_date) ? date('M j, Y', strtotime($project->end_date)) : 'Not set' ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Tasks:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= $completedTasks ?> / <?= $totalTasks ?> completed
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Time logged:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= Time::formatSeconds($totalTime) ?>
                            </div>
                            
                            <div class="text-gray-500 dark:text-gray-400 font-medium">Progress:</div>
                            <div class="text-gray-900 dark:text-white">
                                <?= $taskProgressPercentage ?>%
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div class="mt-2">
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $taskProgressPercentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Team Members Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Team Members</h3>
                        <button type="button" class="section-toggle" data-target="team-members">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="team-members" class="p-4">
                        <?php if (isset($project->team_members) && !empty($project->team_members)): ?>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($project->team_members as $member): ?>
                                    <li class="py-2">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <span class="text-gray-600 dark:text-gray-300 font-medium text-sm">
                                                    <?= substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1) ?>
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    <?= isset($member->role_name) ? htmlspecialchars($member->role_name) : 'Team Member' ?>
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No team members assigned.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Project Sprints -->
                <?php if (isset($project->sprints) && !empty($project->sprints)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Sprints</h3>
                        <button type="button" class="section-toggle" data-target="project-sprints">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <div id="project-sprints" class="p-4">
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($project->sprints as $sprint): ?>
                                <?php
                                    $sprintStatus = getGlobalSprintStatusClass($sprint->status_id);
                                ?>
                                <li class="py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <a href="/sprints/view/<?= $sprint->id ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                <?= htmlspecialchars($sprint->name) ?>
                                            </a>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                <?php if (isset($sprint->start_date) && isset($sprint->end_date)): ?>
                                                    <?= date('M j', strtotime($sprint->start_date)) ?> - <?= date('M j, Y', strtotime($sprint->end_date)) ?>
                                                <?php else: ?>
                                                    Dates not set
                                                <?php endif; ?>
                                                • <?= $sprint->task_count ?? 0 ?> tasks
                                            </p>
                                        </div>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $sprintStatus ?>">
                                            <?= isset($sprint->status_name) ? htmlspecialchars($sprint->status_name) : 'Unknown' ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Project Content Column - Right side, 3/4 width -->
            <div class="md:col-span-3">
                <!-- Project Description with View Mode Toggle -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Description</h3>
                        <div class="flex items-center space-x-4">
                            <!-- Toggle Switch for View Mode -->
                            <div class="flex items-center">
                                <span class="text-xs mr-2 text-gray-600 dark:text-gray-400">Raw</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="view-mode-toggle" class="sr-only peer" <?= $viewMode === 'rendered' ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                                <span class="text-xs ml-2 text-gray-600 dark:text-gray-400">Rendered</span>
                            </div>
                            
                            <!-- Toggle Collapse Button -->
                            <button type="button" class="section-toggle" data-target="project-description">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div id="project-description" class="p-4">
                        <?php if (isset($project->description) && !empty($project->description)): ?>
                            <!-- Raw view (pre tag) -->
                            <pre id="raw-description" class="whitespace-pre-wrap text-white font-mono text-sm bg-gray-50 dark:bg-gray-900 p-4 rounded-md overflow-auto <?= $viewMode === 'rendered' ? 'hidden' : '' ?>"><?= htmlspecialchars($project->description) ?></pre>
                            
                            <!-- Rendered view (with formatting) -->
                            <div id="rendered-description" class="prose prose-sm max-w-none dark:prose-dark text-white <?= $viewMode === 'raw' ? 'hidden' : '' ?>">
                                <?= nl2br(htmlspecialchars($project->description)) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">No description available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Project Tasks with Hierarchy -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Tasks, Epics & Milestones</h3>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400"><?= $completedTasks ?>/<?= $totalTasks ?> completed</span>
                            <button type="button" class="section-toggle" data-target="project-tasks">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div id="project-tasks">
                        <?php include BASE_PATH . '/../src/Views/Projects/inc/table_tasks_hierarchical.php'; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>
    
    <!-- Include Marked.js for Markdown preview -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- JavaScript for collapsible sections and view mode toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($project->description) && !empty($project->description)): ?>
            // Render the template content
            const previewDiv = document.getElementById('rendered-description');
            const descriptionContent = <?= json_encode($project->description) ?>;
            previewDiv.innerHTML = marked.parse(descriptionContent);
            <?php endif; ?>

            // Setup collapsible sections
            const toggles = document.querySelectorAll('.section-toggle');
            
            toggles.forEach(toggle => {
                const targetId = toggle.getAttribute('data-target');
                const targetElement = document.getElementById(targetId);
                
                // Store initial state
                if (!sessionStorage.getItem(targetId)) {
                    sessionStorage.setItem(targetId, 'open');
                }
                
                // Apply initial state
                const initialState = sessionStorage.getItem(targetId);
                if (initialState === 'closed') {
                    targetElement.classList.add('hidden');
                    toggle.querySelector('svg').classList.add('rotate-180');
                }
                
                toggle.addEventListener('click', function() {
                    // Toggle visibility
                    targetElement.classList.toggle('hidden');
                    
                    // Toggle rotation of arrow icon
                    const icon = toggle.querySelector('svg');
                    icon.classList.toggle('rotate-180');
                    
                    // Store state
                    const newState = targetElement.classList.contains('hidden') ? 'closed' : 'open';
                    sessionStorage.setItem(targetId, newState);
                });
            });
            
            // Setup view mode toggle
            const viewModeToggle = document.getElementById('view-mode-toggle');
            const rawDescription = document.getElementById('raw-description');
            const renderedDescription = document.getElementById('rendered-description');
            
            if (viewModeToggle && rawDescription && renderedDescription) {
                // Store view mode preference
                if (!localStorage.getItem('description_view_mode')) {
                    localStorage.setItem('description_view_mode', 'rendered');
                }
                
                // Set initial state based on URL or localStorage
                const urlParams = new URLSearchParams(window.location.search);
                const viewMode = urlParams.get('view_mode') || localStorage.getItem('description_view_mode');
                
                if (viewMode === 'raw') {
                    viewModeToggle.checked = false;
                    rawDescription.classList.remove('hidden');
                    renderedDescription.classList.add('hidden');
                } else {
                    viewModeToggle.checked = true;
                    rawDescription.classList.add('hidden');
                    renderedDescription.classList.remove('hidden');
                }
                
                // Handle toggle changes
                viewModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // Rendered view
                        rawDescription.classList.add('hidden');
                        renderedDescription.classList.remove('hidden');
                        localStorage.setItem('description_view_mode', 'rendered');
                        
                        // Update URL without page reload
                        const url = new URL(window.location);
                        url.searchParams.set('view_mode', 'rendered');
                        window.history.pushState({}, '', url);
                    } else {
                        // Raw view
                        rawDescription.classList.remove('hidden');
                        renderedDescription.classList.add('hidden');
                        localStorage.setItem('description_view_mode', 'raw');
                        
                        // Update URL without page reload
                        const url = new URL(window.location);
                        url.searchParams.set('view_mode', 'raw');
                        window.history.pushState({}, '', url);
                    }
                });
            }
        });
    </script>
</body>
</html>