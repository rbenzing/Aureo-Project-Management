<?php

// file: Core/Config.php
declare(strict_types=1);

namespace App\Core;

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use Dotenv\Dotenv;
use RuntimeException;

class Config
{
    /**
     * Default configuration constants
     */
    private const DEFAULTS = [
        'PAGE_LIMIT' => 10,
        'TIMEZONE' => 'UTC',
        'COMPANY' => 'Aureo',
        'DOMAIN' => 'aureo',
        'SCHEME' => 'http',
        'LOCALE' => 'en_US',
        'CURRENCY_FORMAT' => '%i',
        'DEFAULT_PROJECT_TEMPLATE' => '# Project Overview\nProvide a brief overview of the project.\n\n## Objectives\n- List key objectives\n- What are we trying to accomplish?\n\n## Scope\n- What\'s included\n- What\'s excluded\n\n## Timeline\n- Start date\n- Expected end date\n- Key milestones\n\n## Resources\n- Team members\n- Budget\n- Tools and technologies',
        'DEFAULT_TASK_TEMPLATE' => '# Task Description\nProvide a detailed description of what needs to be done.\n\n## Acceptance Criteria\n- [ ] Criterion 1\n- [ ] Criterion 2\n- [ ] Criterion 3\n\n## Notes\n- Any additional notes or considerations\n- Dependencies or blockers\n\n## Definition of Done\n- [ ] Code is written and tested\n- [ ] Documentation is updated\n- [ ] Code review is completed',
        'DEFAULT_MILESTONE_TEMPLATE' => '# Milestone Overview\nDescribe what this milestone represents and its significance.\n\n## Goals\n- Primary goal of this milestone\n- Secondary objectives\n\n## Deliverables\n- List of expected deliverables\n- Quality criteria\n\n## Success Criteria\n- How will we measure success?\n- Key performance indicators\n\n## Dependencies\n- What needs to be completed before this milestone?\n- External dependencies',
        'DEFAULT_SPRINT_TEMPLATE' => '# Sprint Goal\nDefine the main objective for this sprint.\n\n## Sprint Backlog\n- List of user stories/tasks to be completed\n- Priority order\n\n## Sprint Planning Notes\n- Team capacity\n- Estimated story points\n- Known risks or blockers\n\n## Definition of Done\n- [ ] All planned stories are completed\n- [ ] Code is reviewed and tested\n- [ ] Sprint demo is prepared\n- [ ] Retrospective feedback is collected',

        // Quick Template Options (3 defaults for each type)
        'QUICK_PROJECT_TEMPLATES' => [
            'Basic Project' => '# Project Overview\nBrief description of the project goals and objectives.\n\n## Key Deliverables\n- Deliverable 1\n- Deliverable 2\n- Deliverable 3\n\n## Timeline\n- Start Date: [Date]\n- End Date: [Date]',
            'Software Development' => '# Software Project\nDeveloping [Application Name] to solve [Problem Statement].\n\n## Technical Requirements\n- Frontend: [Technology]\n- Backend: [Technology]\n- Database: [Technology]\n\n## Features\n- [ ] Feature 1\n- [ ] Feature 2\n- [ ] Feature 3',
            'Marketing Campaign' => '# Marketing Campaign\nCampaign to promote [Product/Service] to [Target Audience].\n\n## Campaign Goals\n- Increase brand awareness by [%]\n- Generate [Number] leads\n- Achieve [Metric] conversion rate\n\n## Channels\n- Social Media\n- Email Marketing\n- Content Marketing',
        ],

        'QUICK_TASK_TEMPLATES' => [
            'User Story' => '## User Story\nAs a [type of user], I want [goal] so that [reason].\n\n## Acceptance Criteria\n- [ ] Criterion 1\n- [ ] Criterion 2\n- [ ] Criterion 3\n\n## Definition of Done\n- [ ] Code complete\n- [ ] Tests written and passing\n- [ ] Code reviewed',
            'Bug Report' => '## Bug Description\nBrief description of the issue.\n\n## Steps to Reproduce\n1. Step 1\n2. Step 2\n3. Step 3\n\n## Expected vs Actual\n**Expected:** What should happen\n**Actual:** What actually happens\n\n## Environment\n- Browser/OS: [Details]\n- Version: [Version]',
            'Research Task' => '## Research Objective\nWhat needs to be researched and why.\n\n## Research Questions\n- Question 1\n- Question 2\n- Question 3\n\n## Deliverables\n- [ ] Research findings document\n- [ ] Recommendations\n- [ ] Next steps proposal',
        ],

        'QUICK_MILESTONE_TEMPLATES' => [
            'Release Milestone' => '# Release [Version]\nMajor release milestone with new features and improvements.\n\n## Release Goals\n- Feature 1 completion\n- Performance improvements\n- Bug fixes\n\n## Success Criteria\n- [ ] All planned features implemented\n- [ ] Performance benchmarks met\n- [ ] Quality assurance passed',
            'Project Phase' => '# [Phase Name] Completion\nCompletion of a major project phase.\n\n## Phase Objectives\n- Objective 1\n- Objective 2\n- Objective 3\n\n## Deliverables\n- [ ] Deliverable 1\n- [ ] Deliverable 2\n- [ ] Phase review completed',
            'Compliance Checkpoint' => '# Compliance Review\nEnsuring project meets regulatory and compliance requirements.\n\n## Compliance Areas\n- Security standards\n- Data protection\n- Industry regulations\n\n## Review Items\n- [ ] Security audit completed\n- [ ] Documentation updated\n- [ ] Compliance certification obtained',
        ],

        'QUICK_SPRINT_TEMPLATES' => [
            'Development Sprint' => '# Development Sprint [Number]\nFocus on implementing core features and functionality.\n\n## Sprint Goal\nComplete [specific feature/functionality]\n\n## Capacity\n- Team size: [Number] developers\n- Sprint duration: [Duration]\n- Estimated velocity: [Points]\n\n## Focus Areas\n- Feature development\n- Code quality\n- Testing',
            'Bug Fix Sprint' => '# Bug Fix Sprint\nDedicated sprint to address technical debt and critical bugs.\n\n## Sprint Goal\nResolve high-priority bugs and improve system stability\n\n## Targets\n- [ ] Critical bugs: [Number]\n- [ ] High priority bugs: [Number]\n- [ ] Technical debt items: [Number]\n\n## Success Metrics\n- Bug count reduction\n- System stability improvement',
            'Research Sprint' => '# Research & Discovery Sprint\nExploring new technologies and approaches.\n\n## Sprint Goal\nInvestigate [technology/approach] for [purpose]\n\n## Research Areas\n- Technology evaluation\n- Proof of concept development\n- Feasibility assessment\n\n## Deliverables\n- [ ] Research findings\n- [ ] Prototype/POC\n- [ ] Recommendations',
        ],

        // Template visibility settings (default: quick templates enabled, custom templates enabled)
        'TEMPLATE_SETTINGS' => [
            'project' => [
                'show_quick_templates' => true,
                'show_custom_templates' => true,
            ],
            'task' => [
                'show_quick_templates' => true,
                'show_custom_templates' => true,
            ],
            'milestone' => [
                'show_quick_templates' => true,
                'show_custom_templates' => true,
            ],
            'sprint' => [
                'show_quick_templates' => true,
                'show_custom_templates' => true,
            ],
        ],
    ];

    /**
     * Required environment variables
     */
    private const REQUIRED_ENV = [
        'APP_DEBUG',
        'DB_HOST',
        'DB_NAME',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /**
     * Application configuration storage
     * @var array
     */
    private static array $config = [];

    /**
     * Track if environment is loaded
     * @var bool
     */
    private static bool $isInitialized = false;

    /**
     * Initialize configuration with environment-specific settings
     * @throws RuntimeException
     */
    public static function init(): void
    {
        if (self::$isInitialized) {
            return;
        }

        self::loadEnvironment();
        self::validateEnvironment();
        self::initializeSettings();

        self::$isInitialized = true;
    }

    /**
     * Load environment variables
     * @throws RuntimeException
     */
    private static function loadEnvironment(): void
    {
        $envPath = dirname(BASE_PATH);
        if (!file_exists($envPath . '/.env')) {
            throw new RuntimeException('.env file not found at ' . $envPath . '/.env');
        }

        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->load();
    }

    /**
     * Validate required environment variables
     * @throws RuntimeException
     */
    private static function validateEnvironment(): void
    {
        $missing = [];
        foreach (self::REQUIRED_ENV as $var) {
            if (!isset($_ENV[$var])) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException(
                'Missing required environment variables: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Initialize application settings
     */
    private static function initializeSettings(): void
    {
        // Set timezone - try to use General settings first, fallback to environment
        try {
            // Check if SettingsService is available and database is initialized
            if (class_exists('\App\Services\SettingsService')) {
                $settingsService = \App\Services\SettingsService::getInstance();
                $timezone = $settingsService->getDefaultTimezone();
                date_default_timezone_set($timezone);
            } else {
                // Fallback to environment variable
                date_default_timezone_set(self::get('TIMEZONE', self::DEFAULTS['TIMEZONE']));
            }
        } catch (\Exception $e) {
            // Fallback to environment variable if settings service fails
            date_default_timezone_set(self::get('TIMEZONE', self::DEFAULTS['TIMEZONE']));
        }

        // Set locale
        $locale = self::get('LOCALE', self::DEFAULTS['LOCALE']);
        setlocale(LC_ALL, $locale . '.UTF-8');

        // Get pagination limit from settings if available, otherwise use environment
        $maxPages = (int) self::get('PAGE_LIMIT', self::DEFAULTS['PAGE_LIMIT']);

        try {
            if (class_exists('\App\Services\SettingsService')) {
                $settingsService = \App\Services\SettingsService::getInstance();
                $maxPages = $settingsService->getResultsPerPage();
            }
        } catch (\Exception $e) {
            // Use environment/default value if settings service fails
        }

        // Initialize configuration
        self::$config = [
            'debug' => self::getEnvBoolean('APP_DEBUG', false),
            'max_pages' => $maxPages,
            'timezone' => self::get('TIMEZONE', self::DEFAULTS['TIMEZONE']),
            'domain' => self::get('DOMAIN', self::DEFAULTS['DOMAIN']),
            'company_name' => self::get('COMPANY', self::DEFAULTS['COMPANY']),
            'scheme' => self::get('SCHEME', self::DEFAULTS['SCHEME']),
            'locale' => $locale,
            'currency_format' => self::get('CURRENCY_FORMAT', self::DEFAULTS['CURRENCY_FORMAT']),
            'base_url' => sprintf(
                '%s://%s',
                self::get('SCHEME', self::DEFAULTS['SCHEME']),
                self::get('DOMAIN', self::DEFAULTS['DOMAIN'])
            ),
        ];
    }

    /**
     * Get a configuration value
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? self::$config[strtolower($key)] ?? self::DEFAULTS[$key] ?? $default;
    }

    /**
     * Set a configuration value
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public static function set(string $key, mixed $value): void
    {
        self::$config[strtolower($key)] = $value;
    }

    /**
     * Check if a configuration key exists
     * @param string $key Configuration key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset(self::$config[strtolower($key)]) || isset($_ENV[$key]);
    }

    /**
     * Get boolean value from environment variable
     * @param string $key Environment variable key
     * @param bool $default Default value
     * @return bool
     */
    private static function getEnvBoolean(string $key, bool $default = false): bool
    {
        $value = $_ENV[$key] ?? $default;
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Check if application is in debug mode
     * @return bool
     */
    public static function isDebug(): bool
    {
        // Check config first, then fall back to APP_DEBUG env variable
        $debug = self::get('debug', null);
        if ($debug !== null) {
            return (bool) $debug;
        }

        // Check APP_DEBUG environment variable directly (for tests)
        return self::getEnvBoolean('APP_DEBUG', false);
    }

    /**
     * Check if application is in production mode
     * @return bool
     */
    public static function isProduction(): bool
    {
        return !self::isDebug();
    }

    /**
     * Get debug-aware error message
     * @param \Exception $e The exception
     * @param string $context Context information (e.g., 'TaskController::update')
     * @param string $userMessage User-friendly message for production
     * @param string|null $entityId Optional entity ID for context
     * @return string Debug-aware error message
     */
    public static function getErrorMessage(\Exception $e, string $context, string $userMessage, ?string $entityId = null): string
    {
        // Always log detailed error for developers
        error_log("Exception in {$context}: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        if ($entityId) {
            error_log("Entity ID: " . $entityId);
        }

        // Return debug-aware message
        if (self::isDebug()) {
            $debugInfo = ' (File: ' . basename($e->getFile()) . ':' . $e->getLine();
            if ($entityId) {
                $debugInfo .= ', ID: ' . $entityId;
            }
            $debugInfo .= ')';

            return 'DEBUG: ' . $e->getMessage() . $debugInfo;
        } else {
            return $userMessage;
        }
    }

    /**
     * Get all configuration values
     * @return array
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * Get SMTP host configuration
     * @return string
     */
    public static function getSmtpHost(): string
    {
        return self::get('SMTP_HOST', 'localhost');
    }

    /**
     * Get SMTP username configuration
     * @return string
     */
    public static function getSmtpUsername(): string
    {
        return self::get('SMTP_USERNAME', '');
    }

    /**
     * Get SMTP password configuration
     * @return string
     */
    public static function getSmtpPassword(): string
    {
        return self::get('SMTP_PASSWORD', '');
    }

    /**
     * Get SMTP port configuration
     * @return int
     */
    public static function getSmtpPort(): int
    {
        return (int) self::get('SMTP_PORT', 587);
    }

    /**
     * Get SMTP debug level configuration
     * @return int
     */
    public static function getSmtpDebug(): int
    {
        return (int) self::get('SMTP_DEBUG', 0);
    }

    /**
     * Get email from address
     * @return string
     */
    public static function getEmailFrom(): string
    {
        return self::get('EMAIL_FROM', 'noreply@example.com');
    }

    /**
     * Get email from name
     * @return string
     */
    public static function getEmailFromName(): string
    {
        return self::get('EMAIL_FROM_NAME', 'Application');
    }
}
