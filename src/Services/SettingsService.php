<?php

//file: Services/SettingsService.php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Settings Service
 *
 * Centralized service for managing application settings with caching
 */
class SettingsService
{
    private Setting $settingModel;
    private static ?array $cache = null;
    private static ?SettingsService $instance = null;

    /**
     * Constructor - Now supports dependency injection
     *
     * @param Setting|null $settingModel Optional Setting model instance
     */
    public function __construct(?Setting $settingModel = null)
    {
        $this->settingModel = $settingModel ?? new Setting();
    }

    /**
     * Get singleton instance (for backward compatibility)
     * @return self
     * @deprecated Use dependency injection instead
     */
    public static function getInstance(): SettingsService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get all settings with caching
     */
    public function getAllSettings(): array
    {
        if (self::$cache === null) {
            self::$cache = $this->settingModel->getAllGrouped();

            // Apply defaults if settings don't exist
            $this->applyDefaults();
        }

        return self::$cache;
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(string $category, string $key, string $default = ''): string
    {
        $settings = $this->getAllSettings();

        return $settings[$category][$key] ?? $default;
    }

    /**
     * Get setting as boolean
     */
    public function getSettingBool(string $category, string $key, bool $default = false): bool
    {
        $value = $this->getSetting($category, $key, $default ? '1' : '0');

        return $value === '1' || $value === 'true';
    }

    /**
     * Get setting as integer
     */
    public function getSettingInt(string $category, string $key, int $default = 0): int
    {
        $value = $this->getSetting($category, $key, (string)$default);

        return (int)$value;
    }

    /**
     * Get general settings
     */
    public function getGeneralSettings(): array
    {
        return [
            'results_per_page' => $this->getSettingInt('general', 'results_per_page', 25),
            'date_format' => $this->getSetting('general', 'date_format', 'Y-m-d'),
            'default_timezone' => $this->getSetting('general', 'default_timezone', 'America/New_York'),
            'autosave_interval' => $this->getSettingInt('general', 'autosave_interval', 0),
            'session_timeout' => $this->getSettingInt('general', 'session_timeout', 3600),
            'time_unit' => $this->getSetting('general', 'time_unit', 'minutes'),
            'time_precision' => $this->getSettingInt('general', 'time_precision', 15),
        ];
    }

    /**
     * Get time interval settings (backward compatibility)
     */
    public function getTimeIntervalSettings(): array
    {
        $generalSettings = $this->getGeneralSettings();

        return [
            'time_unit' => $generalSettings['time_unit'],
            'time_precision' => $generalSettings['time_precision'],
        ];
    }

    /**
     * Get project settings
     */
    public function getProjectSettings(): array
    {
        return [
            'default_task_type' => $this->getSetting('projects', 'default_task_type', 'task'),
            'auto_assign_creator' => $this->getSettingBool('projects', 'auto_assign_creator', true),
            'require_project_for_tasks' => $this->getSettingBool('projects', 'require_project_for_tasks', false),
        ];
    }

    /**
     * Get task settings
     */
    public function getTaskSettings(): array
    {
        return [
            'default_priority' => $this->getSetting('tasks', 'default_priority', 'medium'),
            'auto_estimate_enabled' => $this->getSettingBool('tasks', 'auto_estimate_enabled', false),
            'story_points_enabled' => $this->getSettingBool('tasks', 'story_points_enabled', true),
        ];
    }

    /**
     * Get milestone settings
     */
    public function getMilestoneSettings(): array
    {
        return [
            'auto_create_from_sprints' => $this->getSettingBool('milestones', 'auto_create_from_sprints', false),
            'milestone_notification_days' => $this->getSettingInt('milestones', 'milestone_notification_days', 7),
        ];
    }

    /**
     * Get sprint settings
     */
    public function getSprintSettings(): array
    {
        return [
            'default_sprint_length' => $this->getSettingInt('sprints', 'default_sprint_length', 14),
            'auto_start_next_sprint' => $this->getSettingBool('sprints', 'auto_start_next_sprint', false),
            'sprint_planning_enabled' => $this->getSettingBool('sprints', 'sprint_planning_enabled', true),
            'estimation_method' => $this->getSetting('sprints', 'estimation_method', 'hours'),
            'team_capacity_hours' => $this->getSettingInt('sprints', 'team_capacity_hours', 40),
            'team_capacity_story_points' => $this->getSettingInt('sprints', 'team_capacity_story_points', 20),
            'velocity_tracking_enabled' => $this->getSettingBool('sprints', 'velocity_tracking_enabled', true),
            'burndown_charts_enabled' => $this->getSettingBool('sprints', 'burndown_charts_enabled', true),
            'auto_move_incomplete_tasks' => $this->getSettingBool('sprints', 'auto_move_incomplete_tasks', true),
            'sprint_notifications_enabled' => $this->getSettingBool('sprints', 'sprint_notifications_enabled', true),
            'working_days' => $this->getSetting('sprints', 'working_days', 'monday,tuesday,wednesday,thursday,friday'),
            'retrospective_enabled' => $this->getSettingBool('sprints', 'retrospective_enabled', true),
        ];
    }

    /**
     * Get template settings
     */
    public function getTemplateSettings(): array
    {
        return [
            'project' => [
                'show_quick_templates' => $this->getSettingBool('templates', 'project_show_quick_templates', true),
                'show_custom_templates' => $this->getSettingBool('templates', 'project_show_custom_templates', true),
            ],
            'task' => [
                'show_quick_templates' => $this->getSettingBool('templates', 'task_show_quick_templates', true),
                'show_custom_templates' => $this->getSettingBool('templates', 'task_show_custom_templates', true),
            ],
            'milestone' => [
                'show_quick_templates' => $this->getSettingBool('templates', 'milestone_show_quick_templates', true),
                'show_custom_templates' => $this->getSettingBool('templates', 'milestone_show_custom_templates', true),
            ],
            'sprint' => [
                'show_quick_templates' => $this->getSettingBool('templates', 'sprint_show_quick_templates', true),
                'show_custom_templates' => $this->getSettingBool('templates', 'sprint_show_custom_templates', true),
            ],
        ];
    }

    /**
     * Get security settings
     */
    public function getSecuritySettings(): array
    {
        return [
            'session_samesite' => $this->getSetting('security', 'session_samesite', 'Lax'),
            'validate_session_domain' => $this->getSettingBool('security', 'validate_session_domain', true),
            'regenerate_session_on_auth' => $this->getSettingBool('security', 'regenerate_session_on_auth', true),
            'csrf_protection_enabled' => $this->getSettingBool('security', 'csrf_protection_enabled', true),
            'csrf_ajax_protection' => $this->getSettingBool('security', 'csrf_ajax_protection', true),
            'csrf_token_lifetime' => $this->getSettingInt('security', 'csrf_token_lifetime', 3600),
            'max_input_size' => $this->getSettingInt('security', 'max_input_size', 1048576),
            'strict_input_validation' => $this->getSettingBool('security', 'strict_input_validation', true),
            'html_sanitization' => $this->getSettingBool('security', 'html_sanitization', true),
            'validate_redirects' => $this->getSettingBool('security', 'validate_redirects', true),
            'allowed_redirect_domains' => $this->getSetting('security', 'allowed_redirect_domains', ''),
            'enable_csp' => $this->getSettingBool('security', 'enable_csp', true),
            'csp_policy' => $this->getSetting('security', 'csp_policy', 'moderate'),
            'additional_headers' => $this->getSettingBool('security', 'additional_headers', true),
            'hide_error_details' => $this->getSettingBool('security', 'hide_error_details', true),
            'log_security_events' => $this->getSettingBool('security', 'log_security_events', true),
            'rate_limit_attempts' => $this->getSettingInt('security', 'rate_limit_attempts', 60),
        ];
    }

    /**
     * Get specific security setting
     */
    public function getSecuritySetting(string $key, $default = null)
    {
        $settings = $this->getSecuritySettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Check if a security feature is enabled
     */
    public function isSecurityFeatureEnabled(string $feature): bool
    {
        return $this->getSettingBool('security', $feature, true);
    }

    /**
     * Get allowed redirect domains as array
     */
    public function getAllowedRedirectDomains(): array
    {
        $domains = $this->getSetting('security', 'allowed_redirect_domains', '');
        if (empty($domains)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode("\n", $domains)),
            function ($domain) {
                return !empty($domain) && filter_var('http://' . $domain, FILTER_VALIDATE_URL);
            }
        );
    }

    /**
     * Get Content Security Policy based on settings
     */
    public function getContentSecurityPolicy(): string
    {
        if (!$this->isSecurityFeatureEnabled('enable_csp')) {
            return '';
        }

        $policy = $this->getSetting('security', 'csp_policy', 'moderate');

        switch ($policy) {
            case 'strict':
                return "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';";
            case 'permissive':
                return "default-src 'self' 'unsafe-inline' 'unsafe-eval'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:;";
            case 'moderate':
            default:
                return "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self'; frame-ancestors 'self';";
        }
    }

    /**
     * Clear settings cache
     */
    public function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Apply default settings if they don't exist
     */
    private function applyDefaults(): void
    {
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
        ];

        // Merge with existing settings
        foreach ($defaultSettings as $category => $categoryDefaults) {
            if (!isset(self::$cache[$category])) {
                self::$cache[$category] = [];
            }
            foreach ($categoryDefaults as $key => $defaultValue) {
                if (!isset(self::$cache[$category][$key])) {
                    self::$cache[$category][$key] = $defaultValue;
                }
            }
        }
    }

    /**
     * Convert time based on settings
     */
    public function convertTime(int $seconds, ?string $targetUnit = null): array
    {
        $timeSettings = $this->getTimeIntervalSettings();
        $unit = $targetUnit ?? $timeSettings['time_unit'];

        switch ($unit) {
            case 'seconds':
                return ['value' => $seconds, 'unit' => 'seconds', 'formatted' => $seconds . 's'];
            case 'hours':
                $hours = round($seconds / 3600, 2);

                return ['value' => $hours, 'unit' => 'hours', 'formatted' => $hours . 'h'];
            case 'days':
                $days = round($seconds / 86400, 2);

                return ['value' => $days, 'unit' => 'days', 'formatted' => $days . 'd'];
            case 'minutes':
            default:
                $minutes = round($seconds / 60, 2);

                return ['value' => $minutes, 'unit' => 'minutes', 'formatted' => $minutes . 'm'];
        }
    }

    /**
     * Get time step value based on precision setting
     */
    public function getTimeStep(): string
    {
        $timeSettings = $this->getTimeIntervalSettings();
        $unit = $timeSettings['time_unit'];
        $precision = $timeSettings['time_precision'];

        switch ($unit) {
            case 'seconds':
                return (string)$precision;
            case 'hours':
                return (string)($precision / 60);
            case 'days':
                return (string)($precision / 1440);
            case 'minutes':
            default:
                return (string)$precision;
        }
    }

    /**
     * Get time unit label
     */
    public function getTimeUnitLabel(): string
    {
        $timeSettings = $this->getTimeIntervalSettings();
        $unit = $timeSettings['time_unit'];

        switch ($unit) {
            case 'seconds':
                return 'seconds';
            case 'hours':
                return 'hours';
            case 'days':
                return 'days';
            case 'minutes':
            default:
                return 'minutes';
        }
    }

    /**
     * Get results per page setting
     */
    public function getResultsPerPage(): int
    {
        return $this->getSettingInt('general', 'results_per_page', 25);
    }

    /**
     * Get date format setting
     */
    public function getDateFormat(): string
    {
        return $this->getSetting('general', 'date_format', 'Y-m-d');
    }

    /**
     * Get default timezone setting
     */
    public function getDefaultTimezone(): string
    {
        return $this->getSetting('general', 'default_timezone', 'America/New_York');
    }

    /**
     * Get autosave interval setting
     */
    public function getAutosaveInterval(): int
    {
        return $this->getSettingInt('general', 'autosave_interval', 0);
    }

    /**
     * Get session timeout setting
     */
    public function getSessionTimeout(): int
    {
        return $this->getSettingInt('general', 'session_timeout', 3600);
    }

    /**
     * Format date using the configured date format and timezone
     */
    public function formatDate($date): string
    {
        if (empty($date)) {
            return '';
        }

        $format = $this->getDateFormat();
        $timezone = $this->getDefaultTimezone();

        try {
            if ($date instanceof \DateTime) {
                // Set timezone if not already set
                if ($date->getTimezone()->getName() === 'UTC') {
                    $date->setTimezone(new \DateTimeZone($timezone));
                }

                return $date->format($format);
            }

            if (is_string($date)) {
                $dateObj = new \DateTime($date);
                // Convert to configured timezone
                $dateObj->setTimezone(new \DateTimeZone($timezone));

                return $dateObj->format($format);
            }

            return (string)$date;
        } catch (\Exception $e) {
            // Fallback to original date if timezone conversion fails
            error_log("Date formatting error: " . $e->getMessage());

            return (string)$date;
        }
    }

    /**
     * Get current date/time in configured timezone and format
     */
    public function getCurrentDateTime(): string
    {
        $format = $this->getDateFormat();
        $timezone = $this->getDefaultTimezone();

        try {
            $now = new \DateTime('now', new \DateTimeZone($timezone));

            return $now->format($format);
        } catch (\Exception $e) {
            error_log("Current date/time error: " . $e->getMessage());

            return date($format);
        }
    }
}
