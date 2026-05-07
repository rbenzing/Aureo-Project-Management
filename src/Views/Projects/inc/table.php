<?php
//file: Views/Projects/inc/table.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}


// Define status labels and colors
$statusMap = [
    1 => [
        'label' => 'READY',
        'color' => 'bg-blue-600',
    ],
    2 => [
        'label' => 'IN PROGRESS',
        'color' => 'bg-yellow-500',
    ],
    3 => [
        'label' => 'COMPLETED',
        'color' => 'bg-green-500',
    ],
    4 => [
        'label' => 'ON HOLD',
        'color' => 'bg-purple-500',
    ],
    6 => [
        'label' => 'DELAYED',
        'color' => 'bg-red-500',
    ],
    7 => [
        'label' => 'CANCELLED',
        'color' => 'bg-gray-500',
    ],
];

// Get filter parameters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status_id']) ? (int)$_GET['status_id'] : '';
$companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : '';
$viewByFilter = isset($_GET['by']) ? $_GET['by'] : 'tasks';
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'updated_at';
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
?>

<!-- Project Table List -->
<?php if (!empty($projects)): ?>
    <?php
    foreach ($projects as $project):
        // Get status info
        $statusId = $project->status_id ?? 1;
        $statusInfo = $statusMap[$statusId] ?? ['label' => 'UNKNOWN', 'color' => 'bg-gray-500'];

        // Calculate project metrics
        $totalTasks = 0;
        $completedTasks = 0;
        $totalTime = 0;
        $totalBillable = 0;
        $fileCount = 0;

        if (isset($project->tasks) && is_array($project->tasks)) {
            $totalTasks = count($project->tasks);
            foreach ($project->tasks as $task) {
                if (isset($task->status_id) && $task->status_id == 6) { // Completed status is 6
                    $completedTasks++;
                }
                $totalTime += isset($task->time_spent) ? (int)$task->time_spent : 0;

                // Calculate billable amount
                if (
                    isset($task->is_hourly) && $task->is_hourly &&
                    isset($task->hourly_rate) && isset($task->billable_time)
                ) {
                    $billableAmount = ($task->hourly_rate * $task->billable_time) / 3600; // Convert seconds to hours
                    $totalBillable += $billableAmount;
                }
            }
        }
        ?>
        <div class="text-gray-900 dark:text-white">
            <div class="py-2">
                <div class="flex justify-between items-center w-full hover:bg-indigo-800">
                    <div class="flex items-center">
                        <div class="w-1 h-12 <?= $statusInfo['color'] ?> mr-4"></div>
                        <!-- Chevron for toggle -->
                        <button type="button" class="project-toggle mr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 project-chevron transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <h2 class="inline-block text-lg font-medium">
                            <a href="/projects/view/<?= $project->id ?>" class="hover:text-blue-500 dark:hover:text-blue-400"><?= htmlspecialchars($project->name ?? '') ?></a>
                        </h2>
                        <span class="ml-4 px-3 py-1 text-xs rounded-full bg-opacity-20 text-white whitespace-nowrap <?= $statusInfo['color'] ?>">
                            <?= $statusInfo['label'] ?>
                        </span>
                        <?php if (isset($project->company_name) && !empty($project->company_name)): ?>
                            <span class="ml-4 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($project->company_name) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="dropdown relative">
                            <button class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 mr-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                </svg>
                            </button>
                            <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
                                <div class="py-1">
                                    <a href="/projects/view/<?= $project->id ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        View Details
                                    </a>
                                    <?php if (isset($currentUser['permissions']) && in_array('edit_projects', $currentUser['permissions'])): ?>
                                        <a href="/projects/edit/<?= $project->id ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            Edit Project
                                        </a>
                                    <?php endif; ?>
                                    <?php if (isset($currentUser['permissions']) && in_array('create_tasks', $currentUser['permissions'])): ?>
                                        <a href="/tasks/create?project_id=<?= $project->id ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            Add Task
                                        </a>
                                    <?php endif; ?>
                                    <?php if (isset($currentUser['permissions']) && in_array('create_milestones', $currentUser['permissions'])): ?>
                                        <a href="/milestones/create?project_id=<?= $project->id ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            Add Milestone
                                        </a>
                                    <?php endif; ?>
                                    <?php if (isset($currentUser['permissions']) && in_array('delete_projects', $currentUser['permissions'])): ?>
                                        <a href="#" onclick="confirmDelete(<?= $project->id ?>, '<?= htmlspecialchars(addslashes($project->name)) ?>')" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            Delete Project
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg" data-project-details>
                <?php if ($viewByFilter === 'tasks'):
                    include BASE_PATH . '/../src/Views/Projects/inc/table_tasks.php';
                elseif ($viewByFilter === 'sprints' && isset($project->sprints) && !empty($project->sprints)):
                    include BASE_PATH . '/../src/Views/Projects/inc/table_sprints.php';
                elseif ($viewByFilter === 'milestones' && isset($project->milestones) && !empty($project->milestones)):
                    include BASE_PATH . '/../src/Views/Projects/inc/table_milestones.php';
                else:
                    include BASE_PATH . '/../src/Views/Projects/inc/table_projects.php';
                endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="bg-white dark:bg-gray-800 rounded-md shadow-md p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No projects found</h3>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            Get started by creating your first project or adjust your search filters.
        </p>
        <?php if (isset($currentUser['permissions']) && in_array('create_projects', $currentUser['permissions'])): ?>
            <div class="mt-6">
                <a href="/projects/create" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Project
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Pagination -->
<?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="mt-6 flex justify-center">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($page > 1): ?>
                <a href="<?= '/projects/page/' . ($page - 1) ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-l-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    Previous
                </a>
            <?php else: ?>
                <span class="px-4 py-2 text-sm font-medium text-gray-500 bg-white dark:bg-gray-800 dark:text-gray-500 border border-gray-300 dark:border-gray-600 rounded-l-md cursor-not-allowed">
                    Previous
                </span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900 dark:text-blue-200 border border-gray-300 dark:border-gray-600">
                        <?= $i ?>
                    </span>
                <?php else: ?>
                    <a href="<?= '/projects/page/' . $i ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= '/projects/page/' . ($page + 1) ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-r-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    Next
                </a>
            <?php else: ?>
                <span class="px-4 py-2 text-sm font-medium text-gray-500 bg-white dark:bg-gray-800 dark:text-gray-500 border border-gray-300 dark:border-gray-600 rounded-r-md cursor-not-allowed">
                    Next
                </span>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle page-specific dropdowns (excluding header dropdown)
        const dropdowns = document.querySelectorAll('main .dropdown, .content .dropdown, table .dropdown');
        dropdowns.forEach(dropdown => {
            const button = dropdown.querySelector('button');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (button && menu) {
                button.addEventListener('click', function() {
                    let useForce = menu.classList.contains('hidden');
                    menu.classList.toggle('hidden', useForce);
                });

                // Close when clicking outside
                document.addEventListener('click', function(event) {
                    if (!dropdown.contains(event.target)) {
                        menu.classList.add('hidden');
                    }
                });
            }
        });

        // Handle project accordions
        const projectToggles = document.querySelectorAll('.project-toggle');
        const toggleAllBtn = document.getElementById('toggle-all-projects');
        const toggleAllText = toggleAllBtn ? toggleAllBtn.querySelector('.toggle-all-text') : null;

        // Check if we found everything we need
        if (!toggleAllBtn || !toggleAllText) {
            console.error('Toggle all button or text not found');
        }

        let allExpanded = true; // Start with all expanded
        
        // Load saved state from localStorage
        const loadSavedStates = () => {
            const savedStates = JSON.parse(localStorage.getItem('projectExpandedStates') || '{}');
            return savedStates;
        }
        
        // Save state to localStorage
        const saveState = (projectId, isExpanded) => {
            const savedStates = loadSavedStates();
            savedStates[projectId] = isExpanded;
            localStorage.setItem('projectExpandedStates', JSON.stringify(savedStates));
        }

        // Function to toggle a single project
        function toggleProject(toggle) {
            const projectSection = toggle.closest('.text-gray-900');
            const detailsSection = projectSection.querySelector('[data-project-details]');
            const chevron = toggle.querySelector('.project-chevron');
            
            // Get project ID from the detailsSection if possible
            const projectId = projectSection.dataset.projectId || '';

            if (detailsSection) {
                if (detailsSection.classList.contains('hidden')) {
                    // Expanding
                    detailsSection.classList.remove('hidden');
                    chevron.style.transform = 'rotate(0deg)';
                    if (projectId) saveState(projectId, true);
                } else {
                    // Collapsing
                    detailsSection.classList.add('hidden');
                    chevron.style.transform = 'rotate(-90deg)';
                    if (projectId) saveState(projectId, false);
                }
            } else {
                console.error('Details section not found for project', projectSection);
            }
        }

        // Add click handler for each project toggle
        projectToggles.forEach(toggle => {
            // Set initial rotation
            const chevron = toggle.querySelector('.project-chevron');
            if (chevron) {
                chevron.style.transition = 'transform 0.2s ease';
            }

            // Initialize expanded state
            const projectSection = toggle.closest('.text-gray-900');
            const detailsSection = projectSection.querySelector('[data-project-details]');

            if (detailsSection) {
                // Add project ID to section for state persistence
                const projectId = projectSection.querySelector('a[href^="/projects/view/"]')?.href.split('/').pop() || '';
                if (projectId) {
                    projectSection.dataset.projectId = projectId;
                    
                    // Load saved state for this project
                    const savedStates = loadSavedStates();
                    const savedState = savedStates[projectId];
                    
                    // Apply saved state if it exists
                    if (savedState === false) {
                        detailsSection.classList.add('hidden');
                        if (chevron) {
                            chevron.style.transform = 'rotate(-90deg)';
                        }
                    } else {
                        // Default expanded
                        if (chevron) {
                            chevron.style.transform = 'rotate(0deg)';
                        }
                    }
                } else {
                    // No project ID found, default to expanded
                    if (chevron) {
                        chevron.style.transform = 'rotate(0deg)';
                    }
                }
            } else {
                console.error('Could not find details section for project');
            }

            toggle.addEventListener('click', function() {
                toggleProject(this);

                // Update the state of all expanded based on current visibility
                updateToggleAllState();
            });
        });

        // Function to toggle all projects
        function toggleAllProjects() {
            if (allExpanded) {
                // Collapse all
                projectToggles.forEach(toggle => {
                    const projectSection = toggle.closest('.text-gray-900');
                    const detailsSection = projectSection.querySelector('[data-project-details]');
                    const chevron = toggle.querySelector('.project-chevron');
                    const projectId = projectSection.dataset.projectId || '';

                    if (detailsSection && !detailsSection.classList.contains('hidden')) {
                        detailsSection.classList.add('hidden');
                        chevron.style.transform = 'rotate(-90deg)';
                        if (projectId) saveState(projectId, false);
                    }
                });
                toggleAllText.textContent = 'Expand All';
                allExpanded = false;
            } else {
                // Expand all
                projectToggles.forEach(toggle => {
                    const projectSection = toggle.closest('.text-gray-900');
                    const detailsSection = projectSection.querySelector('[data-project-details]');
                    const chevron = toggle.querySelector('.project-chevron');
                    const projectId = projectSection.dataset.projectId || '';

                    if (detailsSection && detailsSection.classList.contains('hidden')) {
                        detailsSection.classList.remove('hidden');
                        chevron.style.transform = 'rotate(0deg)';
                        if (projectId) saveState(projectId, true);
                    }
                });
                toggleAllText.textContent = 'Collapse All';
                allExpanded = true;
            }
        }

        // Function to check if all projects are expanded or collapsed
        function updateToggleAllState() {
            if (!toggleAllText) return;

            let expandedCount = 0;

            projectToggles.forEach(toggle => {
                const projectSection = toggle.closest('.text-gray-900');
                const detailsSection = projectSection.querySelector('[data-project-details]');

                if (detailsSection && !detailsSection.classList.contains('hidden')) {
                    expandedCount++;
                }
            });

            // Update the toggle all button text
            if (expandedCount === 0) {
                toggleAllText.textContent = 'Expand All';
                allExpanded = false;
            } else if (expandedCount === projectToggles.length) {
                toggleAllText.textContent = 'Collapse All';
                allExpanded = true;
            }
        }
        
        // Initial state setup - check if we have any saved states
        function initializeProjectStates() {
            // Update toggle all button based on current state
            updateToggleAllState();
        }

        // Add click handler for toggle all button
        if (toggleAllBtn) {
            toggleAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleAllProjects();
            });
        } else {
            console.error('Toggle all button not found!');
        }
        
        // Initialize project states from saved data
        initializeProjectStates();
    });
</script>