<?php

//file: Controllers/SettingsController.php
declare(strict_types=1);

namespace App\Controllers;

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Middleware\AuthMiddleware;
use App\Models\Setting;
use App\Services\SettingsService;

class SettingsController extends BaseController
{
    private Setting $settingModel;

    public function __construct(?Setting $settingModel = null)
    {
        parent::__construct();
        $this->settingModel = $settingModel ?? new Setting();
    }

    /**
     * Display settings page with tabs
     */
    public function index(string $requestMethod, array $data): void
    {
        // Check authentication and permissions - user needs at least one settings permission
        $this->authMiddleware->hasAnyPermission([
            'view_settings', 'edit_settings', 'edit_security_settings',
            'manage_sprint_settings', 'manage_task_settings',
            'manage_milestone_settings', 'manage_project_settings',
        ]);

        try {
            // Get all settings grouped by category
            $settings = $this->settingModel->getAllGrouped();

            // Set default values if settings don't exist
            $defaultSettings = [
                'general' => [
                    'results_per_page' => '25',
                    'date_format' => 'Y-m-d',
                    'default_timezone' => 'America/New_York',
                    'autosave_interval' => '0',
                    'session_timeout' => '3600',
                    'time_unit' => 'minutes',
                    'time_precision' => '15',
                ],
                'projects' => [
                    'default_task_type' => 'task',
                    'auto_assign_creator' => '1',
                    'require_project_for_tasks' => '0',
                ],
                'tasks' => [
                    'default_priority' => 'medium',
                    'auto_estimate_enabled' => '0',
                    'story_points_enabled' => '1',
                ],
                'milestones' => [
                    'auto_create_from_sprints' => '0',
                    'milestone_notification_days' => '7',
                ],
                'sprints' => [
                    'default_sprint_length' => '14',
                    'auto_start_next_sprint' => '0',
                    'sprint_planning_enabled' => '1',
                    'estimation_method' => 'hours',
                    'team_capacity_hours' => '40',
                    'team_capacity_story_points' => '20',
                    'velocity_tracking_enabled' => '1',
                    'burndown_charts_enabled' => '1',
                    'auto_move_incomplete_tasks' => '1',
                    'sprint_notifications_enabled' => '1',
                    'working_days' => 'monday,tuesday,wednesday,thursday,friday',
                    'retrospective_enabled' => '1',
                ],
                'templates' => [
                    'project_show_quick_templates' => '1',
                    'project_show_custom_templates' => '1',
                    'task_show_quick_templates' => '1',
                    'task_show_custom_templates' => '1',
                    'milestone_show_quick_templates' => '1',
                    'milestone_show_custom_templates' => '1',
                    'sprint_show_quick_templates' => '1',
                    'sprint_show_custom_templates' => '1',
                ],
                'security' => [
                    'session_samesite' => 'Lax',
                    'validate_session_domain' => '1',
                    'regenerate_session_on_auth' => '1',
                    'csrf_protection_enabled' => '1',
                    'csrf_ajax_protection' => '1',
                    'csrf_token_lifetime' => '3600',
                    'max_input_size' => '1048576',
                    'strict_input_validation' => '1',
                    'html_sanitization' => '1',
                    'validate_redirects' => '1',
                    'allowed_redirect_domains' => '',
                    'enable_csp' => '1',
                    'csp_policy' => 'moderate',
                    'additional_headers' => '1',
                    'hide_error_details' => '1',
                    'log_security_events' => '1',
                    'rate_limit_attempts' => '60',
                ],
            ];

            // Merge with existing settings
            foreach ($defaultSettings as $category => $categoryDefaults) {
                if (!isset($settings[$category])) {
                    $settings[$category] = [];
                }
                foreach ($categoryDefaults as $key => $defaultValue) {
                    if (!isset($settings[$category][$key])) {
                        $settings[$category][$key] = $defaultValue;
                    }
                }
            }

            // Load the view
            $this->render('Settings/index');

        } catch (\Exception $e) {
            error_log("Settings index error: " . $e->getMessage());
            $this->redirectWithError('/dashboard', 'An error occurred while loading settings.');
        }
    }

    /**
     * Update settings
     */
    public function update(string $requestMethod, array $data): void
    {
        // Check authentication - user needs at least one settings permission
        $this->authMiddleware->hasAnyPermission([
            'edit_settings', 'edit_security_settings',
            'manage_sprint_settings', 'manage_task_settings',
            'manage_milestone_settings', 'manage_project_settings',
        ]);

        // Validate CSRF token
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->redirectWithError('/settings', 'Invalid security token. Please try again.');
        }

        try {
            // Process each category of settings with permission checks
            $categoryPermissions = [
                'general' => 'edit_settings',
                'security' => 'edit_security_settings',
                'projects' => 'manage_project_settings',
                'tasks' => 'manage_task_settings',
                'milestones' => 'manage_milestone_settings',
                'sprints' => 'manage_sprint_settings',
                'templates' => 'edit_settings',
            ];

            foreach ($categoryPermissions as $category => $permission) {
                if (isset($data[$category]) && is_array($data[$category])) {
                    // Check if user has permission for this category
                    $userPermissions = $_SESSION['user']['permissions'] ?? [];
                    if (!in_array($permission, $userPermissions, true)) {
                        continue; // Skip this category if no permission
                    }

                    foreach ($data[$category] as $key => $value) {
                        // Sanitize the value
                        $sanitizedValue = $this->sanitizeSettingValue($key, $value);

                        // Update or create the setting
                        $this->settingModel->updateSetting($category, $key, $sanitizedValue);
                    }
                }
            }

            // Clear settings cache to ensure new values are used
            $settingsService = SettingsService::getInstance();
            $settingsService->clearCache();

            $_SESSION['success'] = 'Settings updated successfully.';

        } catch (\Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating settings.';
        }

        $this->redirect('/settings');
    }

    /**
     * Sanitize setting values based on their type
     */
    private function sanitizeSettingValue(string $key, mixed $value): string
    {
        // Convert value to string and trim
        $value = trim((string)$value);

        // Specific validation based on setting key
        switch ($key) {
            case 'time_unit':
                return in_array($value, ['minutes', 'seconds', 'hours', 'days']) ? $value : 'minutes';

            case 'time_precision':
                $precision = (int)$value;

                return (string)max(1, min(60, $precision)); // Between 1 and 60

            case 'default_task_type':
                return in_array($value, ['task', 'story', 'bug', 'epic']) ? $value : 'task';

            case 'default_priority':
                return in_array($value, ['none', 'low', 'medium', 'high']) ? $value : 'medium';

            case 'auto_assign_creator':
            case 'require_project_for_tasks':
            case 'auto_estimate_enabled':
            case 'story_points_enabled':
            case 'auto_create_from_sprints':
            case 'auto_start_next_sprint':
            case 'sprint_planning_enabled':
            case 'velocity_tracking_enabled':
            case 'burndown_charts_enabled':
            case 'auto_move_incomplete_tasks':
            case 'sprint_notifications_enabled':
            case 'retrospective_enabled':
            case 'project_show_quick_templates':
            case 'project_show_custom_templates':
            case 'task_show_quick_templates':
            case 'task_show_custom_templates':
            case 'milestone_show_quick_templates':
            case 'milestone_show_custom_templates':
            case 'sprint_show_quick_templates':
            case 'sprint_show_custom_templates':
                // Security boolean settings
            case 'validate_session_domain':
            case 'regenerate_session_on_auth':
            case 'csrf_protection_enabled':
            case 'csrf_ajax_protection':
            case 'strict_input_validation':
            case 'html_sanitization':
            case 'validate_redirects':
            case 'enable_csp':
            case 'additional_headers':
            case 'hide_error_details':
            case 'log_security_events':
                return in_array($value, ['0', '1']) ? $value : '0';

            case 'milestone_notification_days':
                $days = (int)$value;

                return (string)max(1, min(30, $days)); // Between 1 and 30 days

            case 'default_sprint_length':
                $length = (int)$value;

                return (string)max(1, min(30, $length)); // Between 1 and 30 days

            case 'estimation_method':
                return in_array($value, ['hours', 'story_points']) ? $value : 'hours';

            case 'team_capacity_hours':
                $capacity = (int)$value;

                return (string)max(1, min(200, $capacity)); // Between 1 and 200 hours

            case 'team_capacity_story_points':
                $capacity = (int)$value;

                return (string)max(1, min(100, $capacity)); // Between 1 and 100 story points

            case 'working_days':
                $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                $selectedDays = array_filter(
                    explode(',', strtolower($value)),
                    function ($day) use ($validDays) {
                        return in_array(trim($day), $validDays);
                    }
                );

                return implode(',', $selectedDays) ?: 'monday,tuesday,wednesday,thursday,friday';

                // Security numeric settings
            case 'csrf_token_lifetime':
                $lifetime = (int)$value;

                return (string)max(1800, min(86400, $lifetime)); // Between 30 minutes and 24 hours

            case 'max_input_size':
                $size = (int)$value;

                return (string)max(262144, min(10485760, $size)); // Between 256KB and 10MB

            case 'rate_limit_attempts':
                $attempts = (int)$value;

                return (string)max(0, min(1000, $attempts)); // Between 0 and 1000

                // Security string settings
            case 'session_samesite':
                return in_array($value, ['Strict', 'Lax', 'None']) ? $value : 'Lax';

            case 'csp_policy':
                return in_array($value, ['strict', 'moderate', 'permissive']) ? $value : 'moderate';

            case 'allowed_redirect_domains':
                // Validate and sanitize domain list
                $domains = array_filter(
                    array_map('trim', explode("\n", $value)),
                    function ($domain) {
                        return !empty($domain) &&
                               preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain) &&
                               filter_var('http://' . $domain, FILTER_VALIDATE_URL);
                    }
                );

                return implode("\n", $domains);

            default:
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }


}
