<?php
//file: Views/Settings/index.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Core\Config;

// Include form components
require_once BASE_PATH . '/../src/views/Layouts/FormComponents.php';

// Load form data from session if available (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Load errors from session if available
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

// Page title
$pageTitle = 'Settings';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(Config::get('company_name', 'Aureo')) ?></title>
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Header -->
    <?php include BASE_PATH . '/../src/Views/Layouts/header.php'; ?>

    <!-- Sidebar -->
    <?php include BASE_PATH . '/../src/Views/Layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 flex-grow">
        <?php include BASE_PATH . '/../src/Views/Layouts/notifications.php'; ?>
                <!-- Page Header -->
                <div class="pb-6 flex justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Settings</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Configure application settings for projects, tasks, milestones, and sprints.
                        </p>
                    </div>
                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-3">
                        <a href="/dashboard" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" form="settingsForm" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Settings
                        </button>
                    </div>
                </div>

                <!-- Settings Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <form id="settingsForm" method="POST" action="/settings/update" class="space-y-0">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <!-- Tab Navigation -->
                        <div class="border-b border-gray-200 dark:border-gray-700">
                            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm active" data-tab="general">
                                    General
                                </button>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="security">
                                    Security
                                </button>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="templates">
                                    Templates
                                </button>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="projects">
                                    Projects
                                </button>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="tasks">
                                    Tasks
                                </button>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="milestones">
                                    Milestones
                                </button>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="sprints">
                                    Sprints
                                </button>
                                <?php if (!empty($isDevEnv)): ?>
                                <button type="button" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="developer">
                                    Developer
                                </button>
                                <?php endif; ?>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="p-6">
                            <!-- General Tab -->
                            <div id="general" class="tab-content">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">General Settings</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Results Per Page -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'general[results_per_page]',
                                            'label' => 'Results Per Page',
                                            'value' => $settings['general']['results_per_page'] ?? '25',
                                            'options' => [
                                                '10' => '10',
                                                '25' => '25',
                                                '50' => '50',
                                                '100' => '100',
                                            ],
                                            'help_text' => 'Number of items to display per page in lists',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />',
                                            'error' => $errors['general.results_per_page'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Date Format -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'general[date_format]',
                                            'label' => 'Date Format',
                                            'value' => $settings['general']['date_format'] ?? 'Y-m-d',
                                            'options' => [
                                                'Y-m-d' => date('Y-m-d') . ' (YYYY-MM-DD)',
                                                'm/d/Y' => date('m/d/Y') . ' (MM/DD/YYYY)',
                                                'd/m/Y' => date('d/m/Y') . ' (DD/MM/YYYY)',
                                                'M j, Y' => date('M j, Y') . ' (Mon DD, YYYY)',
                                                'F j, Y' => date('F j, Y') . ' (Month DD, YYYY)',
                                            ],
                                            'help_text' => 'Default date format for display throughout the application',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                                            'error' => $errors['general.date_format'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Default Timezone -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'general[default_timezone]',
                                            'label' => 'Default Timezone',
                                            'value' => $settings['general']['default_timezone'] ?? 'America/New_York',
                                            'options' => [
                                                'America/New_York' => 'Eastern Time (ET)',
                                                'America/Chicago' => 'Central Time (CT)',
                                                'America/Denver' => 'Mountain Time (MT)',
                                                'America/Los_Angeles' => 'Pacific Time (PT)',
                                                'UTC' => 'UTC',
                                                'Europe/London' => 'London (GMT)',
                                                'Europe/Paris' => 'Paris (CET)',
                                                'Asia/Tokyo' => 'Tokyo (JST)',
                                                'Australia/Sydney' => 'Sydney (AEST)',
                                            ],
                                            'help_text' => 'Default timezone for new users and date/time display',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                            'error' => $errors['general.default_timezone'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Auto-save Interval -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'general[autosave_interval]',
                                            'label' => 'Auto-save Interval',
                                            'value' => $settings['general']['autosave_interval'] ?? '0',
                                            'options' => [
                                                '0' => 'Disabled',
                                                '15' => '15 seconds',
                                                '30' => '30 seconds',
                                                '60' => '1 minute',
                                                '120' => '2 minutes',
                                                '300' => '5 minutes',
                                            ],
                                            'help_text' => 'Automatically save form data at specified intervals (Feature coming soon)',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />',
                                            'error' => $errors['general.autosave_interval'] ?? '',
                                            'disabled' => true,
                                        ]) ?>
                                    </div>

                                    <!-- Session Timeout -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'general[session_timeout]',
                                            'label' => 'Session Timeout',
                                            'value' => $settings['general']['session_timeout'] ?? '3600',
                                            'options' => [
                                                '1800' => '30 minutes',
                                                '3600' => '1 hour',
                                                '7200' => '2 hours',
                                                '14400' => '4 hours',
                                                '28800' => '8 hours',
                                                '86400' => '24 hours',
                                            ],
                                            'help_text' => 'Automatically log out users after period of inactivity',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />',
                                            'error' => $errors['general.session_timeout'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Time Unit -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'general[time_unit]',
                                            'label' => 'Time Unit',
                                            'value' => $settings['general']['time_unit'] ?? 'minutes',
                                            'options' => [
                                                'seconds' => 'Seconds',
                                                'minutes' => 'Minutes',
                                                'hours' => 'Hours',
                                                'days' => 'Days',
                                            ],
                                            'help_text' => 'Default unit for time tracking and display',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                            'error' => $errors['general.time_unit'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Time Precision -->
                                    <div>
                                        <?= renderTextInput([
                                            'name' => 'general[time_precision]',
                                            'type' => 'number',
                                            'label' => 'Time Precision',
                                            'value' => $settings['general']['time_precision'] ?? '15',
                                            'min' => '1',
                                            'max' => '60',
                                            'help_text' => 'Increment/precision for time inputs (e.g., 15 for 15-minute intervals)',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />',
                                            'error' => $errors['general.time_precision'] ?? '',
                                        ]) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Tab -->
                            <div id="security" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Security Settings</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                    Configure security features to protect your application from common vulnerabilities.
                                </p>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <!-- Session Security -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Session Security</h4>

                                        <!-- Session SameSite Policy -->
                                        <div>
                                            <?= renderSelect([
                                                'name' => 'security[session_samesite]',
                                                'label' => 'Session SameSite Policy',
                                                'value' => $settings['security']['session_samesite'] ?? 'Lax',
                                                'options' => [
                                                    'Strict' => 'Strict (Maximum security)',
                                                    'Lax' => 'Lax (Balanced security)',
                                                    'None' => 'None (Least secure)',
                                                ],
                                                'help_text' => 'Controls when cookies are sent with cross-site requests. Strict provides maximum CSRF protection.',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />',
                                                'error' => $errors['security.session_samesite'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Session Domain Validation -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[validate_session_domain]',
                                                'label' => 'Validate session domain',
                                                'checked' => ($settings['security']['validate_session_domain'] ?? '1') === '1',
                                                'help_text' => 'Validate session domain against allowed hosts to prevent session fixation attacks',
                                                'error' => $errors['security.validate_session_domain'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Session Regeneration -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[regenerate_session_on_auth]',
                                                'label' => 'Regenerate session on authentication',
                                                'checked' => ($settings['security']['regenerate_session_on_auth'] ?? '1') === '1',
                                                'help_text' => 'Regenerate session ID on login/logout to prevent session fixation',
                                                'error' => $errors['security.regenerate_session_on_auth'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- CSRF Protection -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">CSRF Protection</h4>

                                        <!-- CSRF Token Validation -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[csrf_protection_enabled]',
                                                'label' => 'Enable CSRF protection',
                                                'checked' => ($settings['security']['csrf_protection_enabled'] ?? '1') === '1',
                                                'help_text' => 'Protect against Cross-Site Request Forgery attacks',
                                                'error' => $errors['security.csrf_protection_enabled'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- CSRF for AJAX -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[csrf_ajax_protection]',
                                                'label' => 'CSRF protection for AJAX requests',
                                                'checked' => ($settings['security']['csrf_ajax_protection'] ?? '1') === '1',
                                                'help_text' => 'Require CSRF tokens for AJAX/fetch requests',
                                                'error' => $errors['security.csrf_ajax_protection'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- CSRF Token Lifetime -->
                                        <div>
                                            <?= renderSelect([
                                                'name' => 'security[csrf_token_lifetime]',
                                                'label' => 'CSRF Token Lifetime',
                                                'value' => $settings['security']['csrf_token_lifetime'] ?? '3600',
                                                'options' => [
                                                    '1800' => '30 minutes',
                                                    '3600' => '1 hour',
                                                    '7200' => '2 hours',
                                                    '14400' => '4 hours',
                                                ],
                                                'help_text' => 'How long CSRF tokens remain valid',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                'error' => $errors['security.csrf_token_lifetime'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Input Validation -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Input Validation</h4>

                                        <!-- Max Input Size -->
                                        <div>
                                            <?= renderSelect([
                                                'name' => 'security[max_input_size]',
                                                'label' => 'Maximum Input Size',
                                                'value' => $settings['security']['max_input_size'] ?? '1048576',
                                                'options' => [
                                                    '262144' => '256 KB',
                                                    '524288' => '512 KB',
                                                    '1048576' => '1 MB',
                                                    '2097152' => '2 MB',
                                                    '5242880' => '5 MB',
                                                ],
                                                'help_text' => 'Maximum size for JSON/POST input to prevent DoS attacks',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
                                                'error' => $errors['security.max_input_size'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Strict Input Validation -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[strict_input_validation]',
                                                'label' => 'Strict input validation',
                                                'checked' => ($settings['security']['strict_input_validation'] ?? '1') === '1',
                                                'help_text' => 'Apply strict validation rules to all user inputs',
                                                'error' => $errors['security.strict_input_validation'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- HTML Sanitization -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[html_sanitization]',
                                                'label' => 'HTML sanitization',
                                                'checked' => ($settings['security']['html_sanitization'] ?? '1') === '1',
                                                'help_text' => 'Sanitize HTML content to prevent XSS attacks',
                                                'error' => $errors['security.html_sanitization'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Redirect Security -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Redirect Security</h4>

                                        <!-- Validate Redirects -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[validate_redirects]',
                                                'label' => 'Validate redirect URLs',
                                                'checked' => ($settings['security']['validate_redirects'] ?? '1') === '1',
                                                'help_text' => 'Validate redirect URLs to prevent open redirect attacks',
                                                'error' => $errors['security.validate_redirects'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Allowed Redirect Domains -->
                                        <div>
                                            <?= renderTextarea([
                                                'name' => 'security[allowed_redirect_domains]',
                                                'label' => 'Allowed Redirect Domains',
                                                'value' => $settings['security']['allowed_redirect_domains'] ?? '',
                                                'placeholder' => 'example.com' . "\n" . 'subdomain.example.com' . "\n" . 'app.example.com',
                                                'rows' => 4,
                                                'help_text' => 'One domain per line. Leave empty to allow only same-domain redirects.',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />',
                                                'error' => $errors['security.allowed_redirect_domains'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Security Headers -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Security Headers</h4>

                                        <!-- Content Security Policy -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[enable_csp]',
                                                'label' => 'Enable Content Security Policy',
                                                'checked' => ($settings['security']['enable_csp'] ?? '1') === '1',
                                                'help_text' => 'Add CSP headers to prevent XSS and data injection attacks',
                                                'error' => $errors['security.enable_csp'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- CSP Policy -->
                                        <div>
                                            <?= renderSelect([
                                                'name' => 'security[csp_policy]',
                                                'label' => 'CSP Policy Level',
                                                'value' => $settings['security']['csp_policy'] ?? 'moderate',
                                                'options' => [
                                                    'strict' => 'Strict (Maximum security)',
                                                    'moderate' => 'Moderate (Balanced)',
                                                    'permissive' => 'Permissive (Development)',
                                                ],
                                                'help_text' => 'Content Security Policy strictness level',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />',
                                                'error' => $errors['security.csp_policy'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Additional Security Headers -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[additional_headers]',
                                                'label' => 'Additional security headers',
                                                'checked' => ($settings['security']['additional_headers'] ?? '1') === '1',
                                                'help_text' => 'Include HSTS, Referrer-Policy, and other security headers',
                                                'error' => $errors['security.additional_headers'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Error Handling -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Error Handling</h4>

                                        <!-- Hide Error Details -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[hide_error_details]',
                                                'label' => 'Hide error details in production',
                                                'checked' => ($settings['security']['hide_error_details'] ?? '1') === '1',
                                                'help_text' => 'Hide detailed error messages from users to prevent information disclosure',
                                                'error' => $errors['security.hide_error_details'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Log Security Events -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'security[log_security_events]',
                                                'label' => 'Log security events',
                                                'checked' => ($settings['security']['log_security_events'] ?? '1') === '1',
                                                'help_text' => 'Log failed login attempts, CSRF violations, and other security events',
                                                'error' => $errors['security.log_security_events'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Rate Limiting -->
                                        <div>
                                            <?= renderSelect([
                                                'name' => 'security[rate_limit_attempts]',
                                                'label' => 'Rate Limit (attempts per minute)',
                                                'value' => $settings['security']['rate_limit_attempts'] ?? '60',
                                                'options' => [
                                                    '30' => '30 (Strict)',
                                                    '60' => '60 (Moderate)',
                                                    '120' => '120 (Permissive)',
                                                    '0' => 'Disabled',
                                                ],
                                                'help_text' => 'Maximum requests per minute per IP address',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                'error' => $errors['security.rate_limit_attempts'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Projects Tab -->
                            <div id="projects" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Project Settings</h3>
                                <div class="space-y-6">
                                    <!-- Default Task Type -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'projects[default_task_type]',
                                            'label' => 'Default Task Type',
                                            'value' => $settings['projects']['default_task_type'] ?? 'task',
                                            'options' => [
                                                'task' => 'Task',
                                                'story' => 'User Story',
                                                'bug' => 'Bug',
                                                'epic' => 'Epic',
                                            ],
                                            'help_text' => 'Default task type when creating new tasks',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
                                            'error' => $errors['projects.default_task_type'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Auto Assign Creator -->
                                    <div>
                                        <?= renderCheckbox([
                                            'name' => 'projects[auto_assign_creator]',
                                            'label' => 'Auto-assign task creator',
                                            'checked' => ($settings['projects']['auto_assign_creator'] ?? '1') === '1',
                                            'help_text' => 'Automatically assign the task creator as the assignee',
                                            'error' => $errors['projects.auto_assign_creator'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Require Project for Tasks -->
                                    <div>
                                        <?= renderCheckbox([
                                            'name' => 'projects[require_project_for_tasks]',
                                            'label' => 'Require project for tasks',
                                            'checked' => ($settings['projects']['require_project_for_tasks'] ?? '0') === '1',
                                            'help_text' => 'Make project selection mandatory when creating tasks',
                                            'error' => $errors['projects.require_project_for_tasks'] ?? '',
                                        ]) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Tasks Tab -->
                            <div id="tasks" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Task Settings</h3>
                                <div class="space-y-6">
                                    <!-- Default Priority -->
                                    <div>
                                        <?= renderSelect([
                                            'name' => 'tasks[default_priority]',
                                            'label' => 'Default Priority',
                                            'value' => $settings['tasks']['default_priority'] ?? 'medium',
                                            'options' => [
                                                'none' => 'None',
                                                'low' => 'Low',
                                                'medium' => 'Medium',
                                                'high' => 'High',
                                            ],
                                            'help_text' => 'Default priority level for new tasks',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />',
                                            'error' => $errors['tasks.default_priority'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Auto Estimate Enabled -->
                                    <div>
                                        <?= renderCheckbox([
                                            'name' => 'tasks[auto_estimate_enabled]',
                                            'label' => 'Enable automatic time estimation',
                                            'checked' => ($settings['tasks']['auto_estimate_enabled'] ?? '0') === '1',
                                            'help_text' => 'Automatically suggest time estimates based on similar completed tasks',
                                            'error' => $errors['tasks.auto_estimate_enabled'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Story Points Enabled -->
                                    <div>
                                        <?= renderCheckbox([
                                            'name' => 'tasks[story_points_enabled]',
                                            'label' => 'Enable story points',
                                            'checked' => ($settings['tasks']['story_points_enabled'] ?? '1') === '1',
                                            'help_text' => 'Enable Fibonacci-based story points for agile estimation',
                                            'error' => $errors['tasks.story_points_enabled'] ?? '',
                                        ]) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Milestones Tab -->
                            <div id="milestones" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Milestone Settings</h3>
                                <div class="space-y-6">
                                    <!-- Auto Create from Sprints -->
                                    <div>
                                        <?= renderCheckbox([
                                            'name' => 'milestones[auto_create_from_sprints]',
                                            'label' => 'Auto-create milestones from sprints',
                                            'checked' => ($settings['milestones']['auto_create_from_sprints'] ?? '0') === '1',
                                            'help_text' => 'Automatically create milestones when sprints are completed',
                                            'error' => $errors['milestones.auto_create_from_sprints'] ?? '',
                                        ]) ?>
                                    </div>

                                    <!-- Milestone Notification Days -->
                                    <div>
                                        <?= renderTextInput([
                                            'name' => 'milestones[milestone_notification_days]',
                                            'type' => 'number',
                                            'label' => 'Notification Days',
                                            'value' => $settings['milestones']['milestone_notification_days'] ?? '7',
                                            'min' => '1',
                                            'max' => '30',
                                            'help_text' => 'Days before milestone due date to send notifications',
                                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM16 3H4v2h12V3zM4 7h12v2H4V7zM4 11h12v2H4v-2z" />',
                                            'error' => $errors['milestones.milestone_notification_days'] ?? '',
                                        ]) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Sprints Tab -->
                            <div id="sprints" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Sprint Settings</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                    Configure sprint behavior, SCRUM workflows, and team capacity planning for optimal project management.
                                </p>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <!-- Sprint Configuration -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Sprint Configuration</h4>

                                        <!-- Default Sprint Length -->
                                        <div>
                                            <?= renderTextInput([
                                                'name' => 'sprints[default_sprint_length]',
                                                'type' => 'number',
                                                'label' => 'Default Sprint Length (days)',
                                                'value' => $settings['sprints']['default_sprint_length'] ?? '14',
                                                'min' => '1',
                                                'max' => '30',
                                                'help_text' => 'Default length for new sprints (1-4 weeks recommended)',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                                                'error' => $errors['sprints.default_sprint_length'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Estimation Method -->
                                        <div>
                                            <?= renderSelect([
                                                'name' => 'sprints[estimation_method]',
                                                'label' => 'Estimation Method',
                                                'value' => $settings['sprints']['estimation_method'] ?? 'hours',
                                                'options' => [
                                                    'hours' => 'Hours (Time-based)',
                                                    'story_points' => 'Story Points (Fibonacci)',
                                                ],
                                                'help_text' => 'Default estimation method for sprint planning and capacity',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />',
                                                'error' => $errors['sprints.estimation_method'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Working Days -->
                                        <div>
                                            <?= renderTextInput([
                                                'name' => 'sprints[working_days]',
                                                'label' => 'Working Days',
                                                'value' => $settings['sprints']['working_days'] ?? 'monday,tuesday,wednesday,thursday,friday',
                                                'help_text' => 'Comma-separated list of working days (e.g., monday,tuesday,wednesday,thursday,friday)',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                                                'error' => $errors['sprints.working_days'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Team Capacity -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Team Capacity</h4>

                                        <!-- Team Capacity Hours -->
                                        <div>
                                            <?= renderTextInput([
                                                'name' => 'sprints[team_capacity_hours]',
                                                'type' => 'number',
                                                'label' => 'Default Team Capacity (hours)',
                                                'value' => $settings['sprints']['team_capacity_hours'] ?? '40',
                                                'min' => '1',
                                                'max' => '200',
                                                'help_text' => 'Default team capacity in hours per sprint',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
                                                'error' => $errors['sprints.team_capacity_hours'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Team Capacity Story Points -->
                                        <div>
                                            <?= renderTextInput([
                                                'name' => 'sprints[team_capacity_story_points]',
                                                'type' => 'number',
                                                'label' => 'Default Team Capacity (story points)',
                                                'value' => $settings['sprints']['team_capacity_story_points'] ?? '20',
                                                'min' => '1',
                                                'max' => '100',
                                                'help_text' => 'Default team capacity in story points per sprint',
                                                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />',
                                                'error' => $errors['sprints.team_capacity_story_points'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Sprint Workflow -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Sprint Workflow</h4>

                                        <!-- Sprint Planning Enabled -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[sprint_planning_enabled]',
                                                'label' => 'Enable sprint planning',
                                                'checked' => ($settings['sprints']['sprint_planning_enabled'] ?? '1') === '1',
                                                'help_text' => 'Enable sprint planning features and workflows',
                                                'error' => $errors['sprints.sprint_planning_enabled'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Auto Start Next Sprint -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[auto_start_next_sprint]',
                                                'label' => 'Auto-start next sprint',
                                                'checked' => ($settings['sprints']['auto_start_next_sprint'] ?? '0') === '1',
                                                'help_text' => 'Automatically start the next sprint when current sprint ends',
                                                'error' => $errors['sprints.auto_start_next_sprint'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Auto Move Incomplete Tasks -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[auto_move_incomplete_tasks]',
                                                'label' => 'Auto-move incomplete tasks',
                                                'checked' => ($settings['sprints']['auto_move_incomplete_tasks'] ?? '1') === '1',
                                                'help_text' => 'Automatically move incomplete tasks to next sprint',
                                                'error' => $errors['sprints.auto_move_incomplete_tasks'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Sprint Notifications -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[sprint_notifications_enabled]',
                                                'label' => 'Sprint notifications',
                                                'checked' => ($settings['sprints']['sprint_notifications_enabled'] ?? '1') === '1',
                                                'help_text' => 'Send notifications for sprint events (start, end, delays)',
                                                'error' => $errors['sprints.sprint_notifications_enabled'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>

                                    <!-- Analytics & Tracking -->
                                    <div class="space-y-6">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-2">Analytics & Tracking</h4>

                                        <!-- Velocity Tracking -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[velocity_tracking_enabled]',
                                                'label' => 'Enable velocity tracking',
                                                'checked' => ($settings['sprints']['velocity_tracking_enabled'] ?? '1') === '1',
                                                'help_text' => 'Track team velocity across sprints for better planning',
                                                'error' => $errors['sprints.velocity_tracking_enabled'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Burndown Charts -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[burndown_charts_enabled]',
                                                'label' => 'Enable burndown charts',
                                                'checked' => ($settings['sprints']['burndown_charts_enabled'] ?? '1') === '1',
                                                'help_text' => 'Generate burndown charts for sprint progress visualization',
                                                'error' => $errors['sprints.burndown_charts_enabled'] ?? '',
                                            ]) ?>
                                        </div>

                                        <!-- Retrospective -->
                                        <div>
                                            <?= renderCheckbox([
                                                'name' => 'sprints[retrospective_enabled]',
                                                'label' => 'Enable retrospectives',
                                                'checked' => ($settings['sprints']['retrospective_enabled'] ?? '1') === '1',
                                                'help_text' => 'Enable sprint retrospective features for continuous improvement',
                                                'error' => $errors['sprints.retrospective_enabled'] ?? '',
                                            ]) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Templates Tab -->
                            <div id="templates" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Template Settings</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                    Control which template options are available when creating projects, tasks, milestones, and sprints.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <!-- Project Templates -->
                                    <div class="space-y-4">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200">Project Templates</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[project_show_quick_templates]',
                                                    'label' => 'Show quick templates',
                                                    'checked' => ($settings['templates']['project_show_quick_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show pre-built quick templates for projects',
                                                    'error' => $errors['templates.project_show_quick_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[project_show_custom_templates]',
                                                    'label' => 'Show custom templates',
                                                    'checked' => ($settings['templates']['project_show_custom_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show user-created custom templates for projects',
                                                    'error' => $errors['templates.project_show_custom_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Task Templates -->
                                    <div class="space-y-4">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200">Task Templates</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[task_show_quick_templates]',
                                                    'label' => 'Show quick templates',
                                                    'checked' => ($settings['templates']['task_show_quick_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show pre-built quick templates for tasks',
                                                    'error' => $errors['templates.task_show_quick_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[task_show_custom_templates]',
                                                    'label' => 'Show custom templates',
                                                    'checked' => ($settings['templates']['task_show_custom_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show user-created custom templates for tasks',
                                                    'error' => $errors['templates.task_show_custom_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Milestone Templates -->
                                    <div class="space-y-4">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200">Milestone Templates</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[milestone_show_quick_templates]',
                                                    'label' => 'Show quick templates',
                                                    'checked' => ($settings['templates']['milestone_show_quick_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show pre-built quick templates for milestones',
                                                    'error' => $errors['templates.milestone_show_quick_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[milestone_show_custom_templates]',
                                                    'label' => 'Show custom templates',
                                                    'checked' => ($settings['templates']['milestone_show_custom_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show user-created custom templates for milestones',
                                                    'error' => $errors['templates.milestone_show_custom_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sprint Templates -->
                                    <div class="space-y-4">
                                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-200">Sprint Templates</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[sprint_show_quick_templates]',
                                                    'label' => 'Show quick templates',
                                                    'checked' => ($settings['templates']['sprint_show_quick_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show pre-built quick templates for sprints',
                                                    'error' => $errors['templates.sprint_show_quick_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                            <div>
                                                <?= renderCheckbox([
                                                    'name' => 'templates[sprint_show_custom_templates]',
                                                    'label' => 'Show custom templates',
                                                    'checked' => ($settings['templates']['sprint_show_custom_templates'] ?? '1') === '1',
                                                    'help_text' => 'Show user-created custom templates for sprints',
                                                    'error' => $errors['templates.sprint_show_custom_templates'] ?? '',
                                                ]) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($isDevEnv)): ?>
                            <!-- Developer Tab -->
                            <div id="developer" class="tab-content hidden">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-1">Developer Tools</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Local development helpers. This tab is only visible when <code>APP_ENV</code> isn't <code>production</code>.</p>

                                <!-- phpMyAdmin status card -->
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5 bg-gray-50 dark:bg-gray-900/40">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-3">
                                                <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">phpMyAdmin</h4>
                                                <span id="pmaStatusBadge"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                    Checking…
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                Browse the database GUI without needing XAMPP/Apache. URL:
                                                <code class="font-mono"><?= htmlspecialchars($pmaUrl ?? 'http://localhost:8081') ?></code>
                                            </p>
                                            <p id="pmaHint" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                Not running? Start it with <code class="font-mono px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700">composer pma</code> in a terminal.
                                            </p>
                                        </div>
                                        <a id="pmaOpenLink"
                                            href="<?= htmlspecialchars($pmaUrl ?? 'http://localhost:8081') ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="shrink-0 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                            Open
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7m0 0v7m0-7L10 14M5 5h4M5 5v14h14v-4"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
    </main>

    <!-- Footer -->
    <?php include BASE_PATH . '/../src/Views/Layouts/footer.php'; ?>

    <!-- Settings Tab JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Remove active class from all buttons
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active', 'border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                        btn.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
                    });

                    // Add active class to clicked button
                    this.classList.add('active', 'border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                    this.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');

                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });

                    // Show target tab content
                    const targetContent = document.getElementById(targetTab);
                    if (targetContent) {
                        targetContent.classList.remove('hidden');
                    }
                });
            });

            // Set initial active tab
            const firstTab = tabButtons[0];
            if (firstTab) {
                firstTab.click();
            }

            // Developer tab: phpMyAdmin status probe
            const pmaBadge = document.getElementById('pmaStatusBadge');
            const pmaHint = document.getElementById('pmaHint');
            const pmaLink = document.getElementById('pmaOpenLink');
            if (pmaBadge && pmaLink) {
                const renderStatus = (running) => {
                    pmaBadge.innerHTML = running
                        ? '<span class="w-2 h-2 rounded-full bg-green-500"></span> Running'
                        : '<span class="w-2 h-2 rounded-full bg-red-500"></span> Stopped';
                    pmaBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ' + (running
                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                        : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300');
                    pmaLink.classList.toggle('pointer-events-none', !running);
                    pmaLink.classList.toggle('opacity-50', !running);
                    if (pmaHint) pmaHint.classList.toggle('hidden', running);
                };

                const checkPma = () => {
                    fetch('/api/dev/pma-status', { cache: 'no-store' })
                        .then(r => r.ok ? r.json() : null)
                        .then(data => { if (data) renderStatus(!!data.running); })
                        .catch(() => renderStatus(false));
                };
                checkPma();
                setInterval(checkPma, 5000);
            }
        });
    </script>
</body>
</html>
