<?php

// file: Utils/Breadcrumb.php
declare(strict_types=1);

namespace App\Utils;

class Breadcrumb
{
    private static array $breadcrumbs = [];
    private static bool $initialized = false;

    /**
     * Initialize default breadcrumbs map
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Define the base breadcrumb structure for the application
        self::$breadcrumbs = [
            'dashboard' => [
                'name' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'home',
            ],
            'projects' => [
                'name' => 'Projects',
                'url' => '/projects',
                'icon' => 'folder',
            ],
            'projects/create' => [
                'name' => 'Create New Project',
                'url' => '/projects/create',
                'icon' => 'plus',
            ],
            'projects/view' => [
                'name' => 'Project Details',
                'url' => '/projects/view/{id}',
                'icon' => 'eye',
            ],
            'projects/edit' => [
                'name' => 'Edit Project',
                'url' => '/projects/edit/{id}',
                'icon' => 'pencil',
            ],
            'tasks' => [
                'name' => 'All Tasks',
                'url' => '/tasks',
                'icon' => 'check-square',
            ],
            'tasks/backlog' => [
                'name' => 'Backlog',
                'url' => '/tasks/backlog',
                'icon' => 'clipboard-list',
            ],
            'tasks/sprint-planning' => [
                'name' => 'Sprint Planning',
                'url' => '/tasks/sprint-planning',
                'icon' => 'target',
            ],
            'tasks/create' => [
                'name' => 'Create New Task',
                'url' => '/tasks/create',
                'icon' => 'plus',
            ],
            'tasks/view' => [
                'name' => 'Task Details',
                'url' => '/tasks/view/{id}',
                'icon' => 'eye',
            ],
            'tasks/edit' => [
                'name' => 'Edit Task',
                'url' => '/tasks/edit/{id}',
                'icon' => 'pencil',
            ],
            'companies' => [
                'name' => 'Companies',
                'url' => '/companies',
                'icon' => 'briefcase',
            ],
            'companies/create' => [
                'name' => 'Create New Company',
                'url' => '/companies/create',
                'icon' => 'plus',
            ],
            'companies/view' => [
                'name' => 'Company Details',
                'url' => '/companies/view/{id}',
                'icon' => 'eye',
            ],
            'companies/edit' => [
                'name' => 'Edit Company',
                'url' => '/companies/edit/{id}',
                'icon' => 'pencil',
            ],
            'users' => [
                'name' => 'Users',
                'url' => '/users',
                'icon' => 'users',
            ],
            'users/create' => [
                'name' => 'Create New User',
                'url' => '/users/create',
                'icon' => 'user-plus',
            ],
            'users/view' => [
                'name' => 'User Details',
                'url' => '/users/view/{id}',
                'icon' => 'user',
            ],
            'users/edit' => [
                'name' => 'Edit User',
                'url' => '/users/edit/{id}',
                'icon' => 'pencil',
            ],
            'profile' => [
                'name' => 'My Profile',
                'url' => '/profile',
                'icon' => 'user',
            ],
            'roles' => [
                'name' => 'Roles',
                'url' => '/roles',
                'icon' => 'shield',
            ],
            'milestones' => [
                'name' => 'Milestones',
                'url' => '/milestones',
                'icon' => 'flag',
            ],
            'sprints' => [
                'name' => 'Sprints',
                'url' => '/sprints',
                'icon' => 'clock',
            ],
            'sprints/current' => [
                'name' => 'Current Sprint',
                'url' => '/sprints/current',
                'icon' => 'clock',
            ],
            'sprints/planning' => [
                'name' => 'Sprint Planning',
                'url' => '/sprints/planning',
                'icon' => 'target',
            ],
            'sprints/project' => [
                'name' => 'Project Sprints',
                'url' => '/sprints/project/{id}',
                'icon' => 'clock',
            ],
            'sprints/view' => [
                'name' => 'Sprint Details',
                'url' => '/sprints/view/{id}',
                'icon' => 'eye',
            ],
            'sprints/edit' => [
                'name' => 'Edit Sprint',
                'url' => '/sprints/edit/{id}',
                'icon' => 'pencil',
            ],
            'sprints/create' => [
                'name' => 'Create Sprint',
                'url' => '/sprints/create/{project_id}',
                'icon' => 'plus',
            ],
            'sprint-templates' => [
                'name' => 'Sprint Templates',
                'url' => '/sprint-templates',
                'icon' => 'template',
            ],
            'sprint-templates/create' => [
                'name' => 'Create Sprint Template',
                'url' => '/sprint-templates/create',
                'icon' => 'plus',
            ],
            'sprint-templates/edit' => [
                'name' => 'Edit Sprint Template',
                'url' => '/sprint-templates/edit/{id}',
                'icon' => 'pencil',
            ],
        ];

        self::$initialized = true;
    }

    /**
     * Generate breadcrumb path for a given route
     *
     * @param string $route Current route
     * @param array $params Route parameters
     * @return array Breadcrumb path
     */
    public static function generate(string $route, array $params = []): array
    {
        self::init();

        $path = [];
        $routeParts = explode('/', trim($route, '/'));

        // Always start with dashboard
        $path[] = self::$breadcrumbs['dashboard'];

        // Check if the exact route exists
        if (isset(self::$breadcrumbs[$route])) {
            $crumb = self::$breadcrumbs[$route];
            $crumb['url'] = self::replaceUrlParams($crumb['url'], $params);
            $path[] = $crumb;

            return $path;
        }

        // Build breadcrumb path by traversing route parts
        $currentPath = '';
        foreach ($routeParts as $part) {
            $currentPath .= ($currentPath ? '/' : '') . $part;

            if (isset(self::$breadcrumbs[$currentPath])) {
                $crumb = self::$breadcrumbs[$currentPath];
                $crumb['url'] = self::replaceUrlParams($crumb['url'], $params);
                $path[] = $crumb;
            }
        }

        return $path;
    }

    /**
     * Replace URL parameters with actual values
     *
     * @param string $url URL with placeholders
     * @param array $params Route parameters
     * @return string URL with replaced parameters
     */
    private static function replaceUrlParams(string $url, array $params): string
    {
        foreach ($params as $key => $value) {
            // Only scalar params can fill a URL placeholder. Skip arrays/objects
            // instead of casting them — casting an object/array here threw a fatal
            // "could not be converted to string" and broke the whole page.
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $url = str_replace("{{$key}}", (string)$value, $url);
        }

        return $url;
    }

    /**
     * Render breadcrumbs as HTML
     *
     * @param string $route Current route
     * @param array $params Route parameters
     * @return string HTML markup
     */
    public static function render(string $route, array $params = []): string
    {
        $breadcrumbs = self::generate($route, $params);

        $html = '<nav class="flex mb-5" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2">';

        foreach ($breadcrumbs as $index => $crumb) {
            $isLast = $index === count($breadcrumbs) - 1;

            // Sanitize crumb data
            $crumbName = htmlspecialchars((string)($crumb['name'] ?? ''));
            $crumbUrl = htmlspecialchars((string)($crumb['url'] ?? ''));
            $crumbIcon = $crumb['icon'] ?? '';

            if ($index === 0) {
                // First item (usually Dashboard)
                $html .= '<li class="inline-flex items-center">
                            ' . self::getIcon($crumbIcon) . '
                            <a href="' . $crumbUrl . '" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                ' . $crumbName . '
                            </a>
                        </li>';
            } elseif ($isLast) {
                // Current page (last item)
                $html .= '<li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">' . $crumbName . '</span>
                                ' . ($crumb['copy_url'] ?? false ? self::getCopyIcon($crumb['copy_url']) : '') . '
                            </div>
                        </li>';
            } else {
                // Middle items
                $html .= '<li>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="' . $crumbUrl . '" class="ml-1 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white md:ml-2">' . $crumbName . '</a>
                            </div>
                        </li>';
            }
        }

        $html .= '</ol></nav>';

        return $html;
    }

    /**
     * Render task-specific breadcrumbs with parent task support
     *
     * @param object $task Task object with details
     * @param string $route Current route (tasks/view or tasks/edit)
     * @return string HTML markup
     */
    public static function renderTaskBreadcrumb($task, string $route): string
    {
        self::init();

        $breadcrumbs = [];

        // Always start with dashboard
        $breadcrumbs[] = self::$breadcrumbs['dashboard'];

        // Add "All Tasks" link
        $breadcrumbs[] = [
            'name' => 'All Tasks',
            'url' => '/tasks',
            'icon' => 'check-square',
        ];

        // Add parent task if this is a subtask
        if ($task->is_subtask && $task->parent_task_id) {
            try {
                $taskModel = new \App\Models\Task();
                $parentTask = $taskModel->find($task->parent_task_id);
                if ($parentTask && !$parentTask->is_deleted) {
                    $breadcrumbs[] = [
                        'name' => 'Task #' . $parentTask->id,
                        'url' => '/tasks/view/' . $parentTask->id,
                        'icon' => '',
                    ];
                }
            } catch (\Exception $e) {
                // If we can't fetch parent task, continue without it
                error_log("Error fetching parent task: " . $e->getMessage());
            }
        }

        // Add current task
        $taskName = 'Task #' . $task->id;
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $currentUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $breadcrumbs[] = [
            'name' => $taskName,
            'url' => '',
            'icon' => '',
            'copy_url' => $currentUrl,
        ];

        // Render the breadcrumbs
        $html = '<nav class="flex mb-5" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2">';

        foreach ($breadcrumbs as $index => $crumb) {
            $isLast = $index === count($breadcrumbs) - 1;

            // Sanitize crumb data
            $crumbName = htmlspecialchars((string)($crumb['name'] ?? ''));
            $crumbUrl = htmlspecialchars((string)($crumb['url'] ?? ''));
            $crumbIcon = $crumb['icon'] ?? '';

            if ($index === 0) {
                // First item (Dashboard)
                $html .= '<li class="inline-flex items-center">
                            ' . self::getIcon($crumbIcon) . '
                            <a href="' . $crumbUrl . '" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                ' . $crumbName . '
                            </a>
                        </li>';
            } elseif ($isLast) {
                // Current page (last item) - Task ID with copy icon
                $html .= '<li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">' . $crumbName . '</span>
                                ' . self::getCopyIcon($crumb['copy_url']) . '
                            </div>
                        </li>';
            } else {
                // Middle items
                $html .= '<li>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="' . $crumbUrl . '" class="ml-1 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white md:ml-2">' . $crumbName . '</a>
                            </div>
                        </li>';
            }
        }

        $html .= '</ol></nav>';

        return $html;
    }

    /**
     * Render sprint-specific breadcrumbs with project and sprint context
     *
     * @param object $sprint Sprint object with details
     * @param object|null $project Project object (optional)
     * @param string $route Current route (sprints/view, sprints/edit, etc.)
     * @return string HTML markup
     */
    public static function renderSprintBreadcrumb($sprint, $project = null, string $route = ''): string
    {
        self::init();

        $breadcrumbs = [];

        // Always start with dashboard
        $breadcrumbs[] = self::$breadcrumbs['dashboard'];

        // Add project context if available
        if ($project) {
            $breadcrumbs[] = [
                'name' => 'Projects',
                'url' => '/projects',
                'icon' => 'folder',
            ];
            $breadcrumbs[] = [
                'name' => $project->name,
                'url' => '/projects/view/' . $project->id,
                'icon' => '',
            ];
            $breadcrumbs[] = [
                'name' => 'Sprints',
                'url' => '/sprints/project/' . $project->id,
                'icon' => 'clock',
            ];
        } else {
            // Add general sprints link
            $breadcrumbs[] = [
                'name' => 'Sprints',
                'url' => '/sprints',
                'icon' => 'clock',
            ];
        }

        // Add current sprint
        $sprintName = $sprint->name ?? 'Sprint #' . $sprint->id;
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $currentUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $breadcrumbs[] = [
            'name' => $sprintName,
            'url' => '',
            'icon' => '',
            'copy_url' => $currentUrl,
        ];

        // Render the breadcrumbs
        $html = '<nav class="flex mb-5" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2">';

        foreach ($breadcrumbs as $index => $crumb) {
            $isLast = $index === count($breadcrumbs) - 1;

            // Sanitize crumb data
            $crumbName = htmlspecialchars((string)($crumb['name'] ?? ''));
            $crumbUrl = htmlspecialchars((string)($crumb['url'] ?? ''));
            $crumbIcon = $crumb['icon'] ?? '';

            if ($index === 0) {
                // First item (Dashboard)
                $html .= '<li class="inline-flex items-center">
                            ' . self::getIcon($crumbIcon) . '
                            <a href="' . $crumbUrl . '" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                ' . $crumbName . '
                            </a>
                        </li>';
            } elseif ($isLast) {
                // Current page (last item) - Sprint name with copy icon
                $html .= '<li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">' . $crumbName . '</span>
                                ' . self::getCopyIcon($crumb['copy_url']) . '
                            </div>
                        </li>';
            } else {
                // Middle items
                $html .= '<li>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="' . $crumbUrl . '" class="ml-1 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white md:ml-2">' . $crumbName . '</a>
                            </div>
                        </li>';
            }
        }

        $html .= '</ol></nav>';

        return $html;
    }

    /**
     * Get SVG icon for breadcrumb
     *
     * @param string $icon Icon name
     * @return string SVG markup
     */
    private static function getIcon(string $icon): string
    {
        $icons = [
            'home' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>',
            'folder' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>',
            'plus' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path></svg>',
            'eye' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path></svg>',
            'pencil' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>',
            'check-square' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
            'briefcase' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"></path><path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15a24.98 24.98 0 01-8-1.308z"></path></svg>',
            'users' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path></svg>',
            'user-plus' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"></path></svg>',
            'user' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>',
            'shield' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
            'flag' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"></path></svg>',
            'clock' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>',
            'target' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>',
            'template' => '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"></path></svg>',
        ];

        return $icons[$icon] ?? '';
    }

    /**
     * Get copy icon for breadcrumb
     *
     * @param string $url URL to copy to clipboard
     * @return string Copy icon HTML markup
     */
    private static function getCopyIcon(string $url): string
    {
        return '<button type="button"
                    onclick="copyToClipboard(\'' . htmlspecialchars($url, ENT_QUOTES) . '\')"
                    class="ml-2 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded transition-colors"
                    title="Copy URL to clipboard">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </button>';
    }
}
