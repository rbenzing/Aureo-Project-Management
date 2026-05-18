<?php

//file: Views/Layouts/ViewHelpers.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Services\SettingsService;
use App\Utils\Time;

/**
 * Common view helper functions for consistent UI across all views
 */

/**
 * Format time duration for display using settings
 */
function formatTime(?int $seconds, bool $useSettings = true): string
{
    return Time::formatSeconds($seconds, $useSettings);
}

/**
 * Format time duration for display with settings-aware formatting
 */
function formatTimeWithSettings(?int $seconds): string
{
    if ($seconds === null || $seconds == 0) {
        $settingsService = SettingsService::getInstance();
        $unit = $settingsService->getTimeUnitLabel();

        return "0 {$unit}";
    }

    $settingsService = SettingsService::getInstance();
    $converted = $settingsService->convertTime($seconds);

    return $converted['formatted'];
}

/**
 * Format date using configured date format and timezone
 */
function formatDateWithSettings(?string $date): string
{
    if (empty($date)) {
        return 'Not set';
    }

    $settingsService = SettingsService::getInstance();

    return $settingsService->formatDate($date);
}

/**
 * Format due date with relative time using configured timezone
 */
function formatDueDate(?string $dueDate): string
{
    if (empty($dueDate)) {
        return 'No due date';
    }

    try {
        $settingsService = SettingsService::getInstance();
        $timezone = $settingsService->getDefaultTimezone();

        $due = new DateTime($dueDate, new DateTimeZone($timezone));
        $now = new DateTime('now', new DateTimeZone($timezone));
        $diff = $now->diff($due);

        if ($due < $now) {
            return 'Overdue by ' . $diff->format('%a days');
        } elseif ($diff->days == 0) {
            return 'Due today';
        } elseif ($diff->days == 1) {
            return 'Due tomorrow';
        } else {
            return 'Due in ' . $diff->days . ' days';
        }
    } catch (Exception $e) {
        // Fallback to original method if timezone handling fails
        $due = new DateTime($dueDate);
        $now = new DateTime();
        $diff = $now->diff($due);

        if ($due < $now) {
            return 'Overdue by ' . $diff->format('%a days');
        } elseif ($diff->days == 0) {
            return 'Due today';
        } elseif ($diff->days == 1) {
            return 'Due tomorrow';
        } else {
            return 'Due in ' . $diff->days . ' days';
        }
    }
}

/**
 * Format overdue date using configured timezone
 */
function formatOverdueDate(?string $dueDate): string
{
    if (empty($dueDate)) {
        return '';
    }

    try {
        $settingsService = SettingsService::getInstance();
        $timezone = $settingsService->getDefaultTimezone();

        $due = new DateTime($dueDate, new DateTimeZone($timezone));
        $now = new DateTime('now', new DateTimeZone($timezone));
        $diff = $now->diff($due);

        if ($due < $now) {
            return 'Overdue by ' . $diff->format('%a days');
        }

        return '';
    } catch (Exception $e) {
        // Fallback to original method if timezone handling fails
        $due = new DateTime($dueDate);
        $now = new DateTime();
        $diff = $now->diff($due);

        if ($due < $now) {
            return 'Overdue by ' . $diff->format('%a days');
        }

        return '';
    }
}

/**
 * Check if a date is due soon (within 3 days)
 */
function isDueSoon(?string $dueDate): bool
{
    if (empty($dueDate)) {
        return false;
    }

    $due = new DateTime($dueDate);
    $now = new DateTime();
    $diff = $now->diff($due);

    return $due > $now && $diff->days <= 3;
}

/**
 * Centralized Status Helper System
 * Provides consistent status formatting for all entity types
 */

/**
 * Get task status information
 * @param int $statusId
 * @return array ['label' => string, 'color' => string, 'class' => string]
 */
function getTaskStatusInfo(int $statusId): array
{
    $statusMap = [
        1 => ['label' => 'Open', 'color' => 'bg-blue-600'],
        2 => ['label' => 'In Progress', 'color' => 'bg-yellow-500'],
        3 => ['label' => 'On Hold', 'color' => 'bg-purple-500'],
        4 => ['label' => 'In Review', 'color' => 'bg-indigo-500'],
        5 => ['label' => 'Closed', 'color' => 'bg-gray-500'],
        6 => ['label' => 'Completed', 'color' => 'bg-green-500'],
        7 => ['label' => 'Cancelled', 'color' => 'bg-red-500'],
    ];

    $status = $statusMap[$statusId] ?? ['label' => 'Unknown', 'color' => 'bg-gray-500'];
    $status['class'] = $status['color'] . ' bg-opacity-20 text-white';

    return $status;
}

/**
 * Get project status information
 * @param int $statusId
 * @return array ['label' => string, 'color' => string, 'class' => string]
 */
function getProjectStatusInfo(int $statusId): array
{
    $statusMap = [
        1 => ['label' => 'Ready', 'color' => 'bg-blue-600'],
        2 => ['label' => 'In Progress', 'color' => 'bg-yellow-500'],
        3 => ['label' => 'Completed', 'color' => 'bg-green-500'],
        4 => ['label' => 'On Hold', 'color' => 'bg-purple-500'],
        6 => ['label' => 'Delayed', 'color' => 'bg-red-500'],
        7 => ['label' => 'Cancelled', 'color' => 'bg-gray-500'],
    ];

    $status = $statusMap[$statusId] ?? ['label' => 'Unknown', 'color' => 'bg-gray-500'];
    $status['class'] = $status['color'] . ' bg-opacity-20 text-white';

    return $status;
}

/**
 * Get sprint status information
 * @param int $statusId
 * @return array ['label' => string, 'color' => string, 'class' => string]
 */
function getSprintStatusInfo(int $statusId): array
{
    $statusMap = [
        1 => ['label' => 'Planning', 'color' => 'bg-blue-600'],
        2 => ['label' => 'Active', 'color' => 'bg-green-500'],
        3 => ['label' => 'Delayed', 'color' => 'bg-red-500'],
        4 => ['label' => 'Completed', 'color' => 'bg-gray-500'],
        5 => ['label' => 'Cancelled', 'color' => 'bg-gray-500'],
    ];

    $status = $statusMap[$statusId] ?? ['label' => 'Unknown', 'color' => 'bg-gray-500'];
    $status['class'] = $status['color'] . ' bg-opacity-20 text-white';

    return $status;
}

/**
 * Get milestone status information
 * @param int $statusId
 * @return array ['label' => string, 'color' => string, 'class' => string]
 */
function getMilestoneStatusInfo(int $statusId): array
{
    $statusMap = [
        1 => ['label' => 'Open', 'color' => 'bg-blue-600'],
        2 => ['label' => 'In Progress', 'color' => 'bg-yellow-500'],
        3 => ['label' => 'Completed', 'color' => 'bg-green-500'],
        4 => ['label' => 'On Hold', 'color' => 'bg-purple-500'],
        5 => ['label' => 'Cancelled', 'color' => 'bg-red-500'],
    ];

    $status = $statusMap[$statusId] ?? ['label' => 'Unknown', 'color' => 'bg-gray-500'];
    $status['class'] = $status['color'] . ' bg-opacity-20 text-white';

    return $status;
}

/**
 * Render a status pill with consistent styling
 * @param string $label Status label
 * @param string $colorClass Color class (e.g., 'bg-blue-600')
 * @param string $size Size variant: 'sm', 'md', 'lg'
 * @return string HTML for status pill
 */
function renderStatusPill(string $label, string $colorClass, string $size = 'md'): string
{
    $sizeClasses = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-3 py-1 text-sm',
        'lg' => 'px-4 py-2 text-base',
    ];

    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

    return sprintf(
        '<span class="%s %s bg-opacity-20 text-white font-medium rounded-full whitespace-nowrap">%s</span>',
        $sizeClass,
        $colorClass,
        htmlspecialchars($label)
    );
}

/**
 * Legacy function for backward compatibility - Updated for consistency
 */
function getTaskStatusClass(int $statusId): string
{
    $status = getTaskStatusInfo($statusId);

    return $status['class'];
}

/**
 * Legacy functions - use the new centralized status info functions instead
 * These are kept for backward compatibility but should be replaced
 */
function getProjectStatusClass(int $statusId): string
{
    $statusInfo = getProjectStatusInfo($statusId);

    return $statusInfo['class'];
}

function getMilestoneStatusClass(int $statusId): string
{
    $statusInfo = getMilestoneStatusInfo($statusId);

    return $statusInfo['class'];
}

function getGlobalSprintStatusClass(int $statusId): string
{
    $statusInfo = getSprintStatusInfo($statusId);

    return $statusInfo['class'];
}

/**
 * Get priority badge classes
 */
function getPriorityClass(string $priority): string
{
    $priorityClasses = [
        'none' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'low' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'high' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    ];

    return $priorityClasses[$priority] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

/**
 * Generate pagination HTML
 */
function renderPagination(array $pagination, array $filters = []): string
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $queryString = http_build_query(array_filter($filters));
    $baseUrl = $queryString ? '&' . $queryString : '';

    $html = '<div class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6 mt-6 rounded-lg shadow">';

    // Mobile pagination
    $html .= '<div class="flex-1 flex justify-between sm:hidden">';
    if ($pagination['current_page'] > 1) {
        $html .= '<a href="?page=' . ($pagination['current_page'] - 1) . $baseUrl . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Previous</a>';
    }
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<a href="?page=' . ($pagination['current_page'] + 1) . $baseUrl . '" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Next</a>';
    }
    $html .= '</div>';

    // Desktop pagination
    $html .= '<div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">';
    $html .= '<div><p class="text-sm text-gray-700 dark:text-gray-300">Showing <span class="font-medium">' . $pagination['start'] . '</span> to <span class="font-medium">' . $pagination['end'] . '</span> of <span class="font-medium">' . $pagination['total'] . '</span> results</p></div>';

    $html .= '<div><nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">';

    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $isActive = $i === $pagination['current_page'];
        $classes = $isActive
            ? 'z-10 bg-indigo-50 dark:bg-indigo-900 border-indigo-500 text-indigo-600 dark:text-indigo-400'
            : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600';

        $html .= '<a href="?page=' . $i . $baseUrl . '" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $classes . '">' . $i . '</a>';
    }

    $html .= '</nav></div></div></div>';

    return $html;
}

/**
 * Generate search and filter form
 */
function renderSearchFilters(array $options = []): string
{
    $defaults = [
        'search_placeholder' => 'Search...',
        'show_status_filter' => true,
        'show_priority_filter' => false,
        'show_project_filter' => false,
        'show_user_filter' => false,
        'additional_filters' => [],
    ];

    $options = array_merge($defaults, $options);

    $html = '<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">';
    $html .= '<form method="GET" class="flex flex-wrap items-center gap-4">';

    // Search input
    $html .= '<div class="flex-1 min-w-64">';
    $html .= '<label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>';
    $html .= '<input type="text" name="search" id="search" value="' . htmlspecialchars($_GET['search'] ?? '') . '" placeholder="' . htmlspecialchars($options['search_placeholder']) . '" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100">';
    $html .= '</div>';

    // Status filter
    if ($options['show_status_filter']) {
        $html .= '<div class="flex-1 min-w-48">';
        $html .= '<label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>';
        $html .= '<select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100">';
        $html .= '<option value="">All Statuses</option>';
        // Status options would be populated by the controller
        $html .= '</select>';
        $html .= '</div>';
    }

    // Priority filter
    if ($options['show_priority_filter']) {
        $html .= '<div class="flex-1 min-w-48">';
        $html .= '<label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>';
        $html .= '<select name="priority" id="priority" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100">';
        $html .= '<option value="">All Priorities</option>';
        $html .= '<option value="high"' . (($_GET['priority'] ?? '') === 'high' ? ' selected' : '') . '>High</option>';
        $html .= '<option value="medium"' . (($_GET['priority'] ?? '') === 'medium' ? ' selected' : '') . '>Medium</option>';
        $html .= '<option value="low"' . (($_GET['priority'] ?? '') === 'low' ? ' selected' : '') . '>Low</option>';
        $html .= '<option value="none"' . (($_GET['priority'] ?? '') === 'none' ? ' selected' : '') . '>None</option>';
        $html .= '</select>';
        $html .= '</div>';
    }

    // Submit button
    $html .= '<div class="flex items-end">';
    $html .= '<button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm">Filter</button>';
    $html .= '</div>';

    $html .= '</form>';
    $html .= '</div>';

    return $html;
}

/**
 * Generate action buttons for list views
 */
function renderActionButtons(string $entityType, int $id, array $permissions = []): string
{
    $html = '<div class="flex items-center space-x-2">';

    // View button
    $html .= '<a href="/' . $entityType . '/view/' . $id . '" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">View</a>';

    // Edit button
    if (empty($permissions) || in_array('edit', $permissions)) {
        $html .= '<a href="/' . $entityType . '/edit/' . $id . '" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 text-sm">Edit</a>';
    }

    // Delete button
    if (empty($permissions) || in_array('delete', $permissions)) {
        $html .= '<button onclick="deleteEntity(\'' . $entityType . '\', ' . $id . ')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 text-sm">Delete</button>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Check if user has permission
 */
function hasUserPermission(string $permission): bool
{
    $userPermissions = $_SESSION['user']['permissions'] ?? [];

    return in_array($permission, $userPermissions, true);
}

/**
 * Check if user has any of the given permissions
 */
function hasAnyUserPermission(array $permissions): bool
{
    $userPermissions = $_SESSION['user']['permissions'] ?? [];
    foreach ($permissions as $permission) {
        if (in_array($permission, $userPermissions, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if user has all of the given permissions
 */
function hasAllUserPermissions(array $permissions): bool
{
    $userPermissions = $_SESSION['user']['permissions'] ?? [];
    foreach ($permissions as $permission) {
        if (!in_array($permission, $userPermissions, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Generate timer controls for tasks
 */
function renderTimerControls(int $taskId): string
{
    // Check if user has time tracking permissions
    if (!hasUserPermission('view_time_tracking') && !hasUserPermission('create_time_tracking')) {
        return '';
    }

    // Check if this specific task has an active timer
    $activeTimer = $_SESSION['active_timer'] ?? null;
    $isTimerActiveForThisTask = isset($activeTimer) && $activeTimer['task_id'] == $taskId;

    if ($isTimerActiveForThisTask) {
        return '<form action="/tasks/stop-timer/' . $taskId . '" method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken ?? '') . '">
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                        </svg>
                        Stop
                    </button>
                </form>';
    } elseif (!isset($activeTimer)) { // Only show start timer if no timer is running at all
        return '<form action="/tasks/start-timer/' . $taskId . '" method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken ?? '') . '">
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-sm flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Start
                    </button>
                </form>';
    }

    // Return empty string if another timer is running
    return '';
}

/**
 * Generate breadcrumb navigation
 */
function renderBreadcrumbs(array $breadcrumbs): string
{
    if (empty($breadcrumbs)) {
        return '';
    }

    $html = '<nav class="flex mb-6" aria-label="Breadcrumb">';
    $html .= '<ol class="inline-flex items-center space-x-1 md:space-x-3">';

    foreach ($breadcrumbs as $index => $crumb) {
        $isLast = $index === count($breadcrumbs) - 1;

        $html .= '<li class="inline-flex items-center">';

        if ($index > 0) {
            $html .= '<svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>';
        }

        if ($isLast) {
            $html .= '<span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">' . htmlspecialchars($crumb['title']) . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($crumb['url']) . '" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">';
            if (isset($crumb['icon'])) {
                $html .= $crumb['icon'];
            }
            $html .= htmlspecialchars($crumb['title']) . '</a>';
        }

        $html .= '</li>';
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}
