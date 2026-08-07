<?php

// file: Controllers/InstallController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExposureProbe;
use App\Services\InstallerService;
use App\Services\PreflightService;
use DateTimeZone;
use Throwable;

/**
 * The guided first-run installer.
 *
 * Deliberately NOT a BaseController. BaseController's constructor builds
 * SettingsService and LoggerService, both of which reach the database, and
 * every middleware in the normal stack does the same - CsrfMiddleware stores
 * tokens in a `csrf_tokens` table. At the point this controller runs there is
 * no configuration, no container, and frequently no database, so it carries
 * its own renderer, its own CSRF token and its own rate limiting.
 *
 * State accumulates in $_SESSION['aureo_install'] across the six steps below.
 * The database password lives there for the duration of the install - that is
 * unavoidable (the credentials must survive several requests before there is
 * anywhere to write them) and is cleared in the complete step, not left to
 * session expiry.
 */
class InstallController
{
    private const SESSION_KEY = 'aureo_install';
    private const CSRF_KEY = 'aureo_install_csrf';
    private const CSRF_FIELD = 'install_csrf';
    private const RATE_KEY = 'aureo_install_db_attempts';

    /** Steps in order. The first is what a bare /install renders. */
    private const STEPS = ['preflight', 'exposure', 'database', 'administrator', 'settings', 'complete'];

    private const MAX_DATABASE_ATTEMPTS = 10;
    private const RATE_WINDOW_SECONDS = 300;

    /**
     * Deliberately stronger than, and worded to match, Validator's
     * 'strong_password' rule (see src/Utils/Validator.php). This step cannot
     * call Validator - it may reach Config - so the check is reimplemented
     * here rather than shared.
     */
    private const PASSWORD_POLICY_MESSAGE = 'Password must be at least 12 chars, 1 uppercase, 1 lowercase, 1 number.';

    public function __construct(
        private readonly string $appRoot,
        private readonly string $basePath,
        private readonly PreflightService $preflight,
        private readonly ExposureProbe $probe,
        private readonly InstallerService $installer
    ) {
    }

    public function handle(string $method, array $segments): void
    {
        $step = $segments[1] ?? 'preflight';

        if (!in_array($step, self::STEPS, true)) {
            $this->redirect($this->url(''));
        }

        $isPost = strtoupper($method) === 'POST';

        if ($isPost) {
            $this->assertCsrf();
        }

        match (true) {
            $step === 'exposure' && $isPost => $this->submitExposure(),
            $step === 'exposure' => $this->stepExposure(),
            $step === 'database' && $isPost => $this->submitDatabase(),
            $step === 'database' => $this->stepDatabase(),
            $step === 'administrator' && $isPost => $this->submitAdministrator(),
            $step === 'administrator' => $this->stepAdministrator(),
            $step === 'settings' && $isPost => $this->submitSettings(),
            $step === 'settings' => $this->stepSettings(),
            $step === 'complete' && $isPost => $this->submitComplete(),
            $step === 'complete' => $this->stepComplete(),
            default => $this->stepPreflight(),
        };
    }

    /** Called directly by public/index.php when InstallGate decides REFUSE. */
    public function refuse(string $reason): void
    {
        $this->present('Install/refused', ['reason' => $reason]);
    }

    // ---------------------------------------------------------------
    // Step 1: preflight
    // ---------------------------------------------------------------

    private function stepPreflight(): void
    {
        $checks = $this->preflight->run($this->appRoot, $this->documentRoot());

        $this->present('Install/preflight', [
            'currentStep' => 'preflight',
            'checks' => $checks,
            'blocked' => PreflightService::hasFailures($checks),
        ]);
    }

    // ---------------------------------------------------------------
    // Step 2: exposure
    // ---------------------------------------------------------------

    private function stepExposure(): void
    {
        $result = $this->runExposureProbe();

        $this->present('Install/exposure', $this->exposureViewData($result));
    }

    private function submitExposure(): void
    {
        $result = $this->runExposureProbe();

        if ($result['exposed'] !== []) {
            // Blocked. No acknowledgement can override a readable secret, so
            // re-render rather than advance - there is nothing to confirm.
            $this->present('Install/exposure', $this->exposureViewData($result));

            return;
        }

        $acknowledged = ($_POST['acknowledge'] ?? '') === '1';

        if ($result['verified'] || $acknowledged) {
            $_SESSION[self::SESSION_KEY]['exposure_ok'] = true;
            $this->redirect($this->url('database'));
        }

        // Not verified and not acknowledged: stay put. A redirect (rather
        // than re-rendering directly) avoids a resubmission on reload.
        $this->redirect($this->url('exposure'));
    }

    /** @return array{verified:bool,exposed:list<string>,safe:list<string>,unreachable:list<string>} */
    private function runExposureProbe(): array
    {
        $baseUrl = ExposureProbe::baseUrlFromGlobals($_SERVER, $this->basePath);

        if ($baseUrl === null) {
            // A hostile or absent Host header. Never guess at a URL to probe -
            // report every path unreachable, which requires acknowledgement
            // rather than silently rounding up to "safe".
            return ['verified' => false, 'exposed' => [], 'safe' => [], 'unreachable' => ExposureProbe::PATHS];
        }

        return $this->probe->run($baseUrl);
    }

    /**
     * @param array{verified:bool,exposed:list<string>,safe:list<string>,unreachable:list<string>} $result
     * @return array<string,mixed>
     */
    private function exposureViewData(array $result): array
    {
        $blocked = $result['exposed'] !== [];

        return [
            'currentStep' => 'exposure',
            'exposed' => $result['exposed'],
            'safe' => $result['safe'],
            'unreachable' => $result['unreachable'],
            'verified' => $result['verified'],
            'blocked' => $blocked,
            'needsAcknowledgement' => !$blocked && !$result['verified'],
        ];
    }

    // ---------------------------------------------------------------
    // Step 3: database
    // ---------------------------------------------------------------

    private function stepDatabase(): void
    {
        if (!$this->exposureResolved()) {
            $this->redirect($this->url('exposure'));
        }

        $this->present('Install/database', [
            'currentStep' => 'database',
            'error' => $this->takeFlashError(),
            'dbHost' => $_SESSION[self::SESSION_KEY]['db_host'] ?? '',
            'dbName' => $_SESSION[self::SESSION_KEY]['db_name'] ?? '',
            'dbUser' => $_SESSION[self::SESSION_KEY]['db_user'] ?? '',
        ]);
    }

    private function submitDatabase(): void
    {
        if (!$this->exposureResolved()) {
            $this->redirect($this->url('exposure'));
        }

        if ($this->tooManyDatabaseAttempts()) {
            $this->refuse('Too many attempts. Wait a few minutes and reload this page to try again.');

            return;
        }

        $credentials = [
            'host' => trim((string) ($_POST['db_host'] ?? '')),
            'name' => trim((string) ($_POST['db_name'] ?? '')),
            'user' => trim((string) ($_POST['db_user'] ?? '')),
            'password' => (string) ($_POST['db_password'] ?? ''),
        ];

        if ($credentials['host'] === '' || $credentials['name'] === '' || $credentials['user'] === '') {
            $this->present('Install/database', [
                'currentStep' => 'database',
                'error' => 'Host, database name and username are required.',
                'dbHost' => $credentials['host'],
                'dbName' => $credentials['name'],
                'dbUser' => $credentials['user'],
            ]);

            return;
        }

        try {
            $server = $this->installer->connectToServer($credentials);

            if (!$this->installer->databaseExists($server, $credentials['name'])) {
                $this->installer->createDatabase($server, $credentials['name']);
            }

            // Confirm the target database itself is reachable - connecting to
            // the server alone does not prove the named database is usable.
            $this->installer->connectToDatabase($credentials);

            $usersBefore = $this->installer->countUsers($credentials) ?? 0;
        } catch (Throwable $e) {
            $this->present('Install/database', [
                'currentStep' => 'database',
                'error' => $e->getMessage(),
                'dbHost' => $credentials['host'],
                'dbName' => $credentials['name'],
                'dbUser' => $credentials['user'],
            ]);

            return;
        }

        $_SESSION[self::SESSION_KEY]['db_host'] = $credentials['host'];
        $_SESSION[self::SESSION_KEY]['db_name'] = $credentials['name'];
        $_SESSION[self::SESSION_KEY]['db_user'] = $credentials['user'];
        $_SESSION[self::SESSION_KEY]['db_password'] = $credentials['password'];
        $_SESSION[self::SESSION_KEY]['users_before_install'] = $usersBefore;

        $this->redirect($this->url('administrator'));
    }

    /**
     * Session-counter rate limiting. SecurityService::checkRateLimit() needs
     * the settings table, which does not exist yet - this is a deliberate
     * deviation from reusing it. Brute force is not really the threat model
     * here: the route only exists on a host with no configured database.
     */
    private function tooManyDatabaseAttempts(): bool
    {
        $now = time();
        $bucket = $_SESSION[self::RATE_KEY] ?? ['count' => 0, 'window_start' => $now];

        if ($now - $bucket['window_start'] > self::RATE_WINDOW_SECONDS) {
            $bucket = ['count' => 0, 'window_start' => $now];
        }

        $bucket['count']++;
        $_SESSION[self::RATE_KEY] = $bucket;

        return $bucket['count'] > self::MAX_DATABASE_ATTEMPTS;
    }

    // ---------------------------------------------------------------
    // Step 4: administrator
    // ---------------------------------------------------------------

    private function stepAdministrator(): void
    {
        if (!$this->databaseResolved()) {
            $this->redirect($this->url('database'));
        }

        $this->present('Install/administrator', [
            'currentStep' => 'administrator',
            'error' => $this->takeFlashError(),
            'email' => $_SESSION[self::SESSION_KEY]['admin_email'] ?? '',
            'firstName' => $_SESSION[self::SESSION_KEY]['admin_first_name'] ?? '',
            'lastName' => $_SESSION[self::SESSION_KEY]['admin_last_name'] ?? '',
        ]);
    }

    private function submitAdministrator(): void
    {
        if (!$this->databaseResolved()) {
            $this->redirect($this->url('database'));
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $error = $this->validateAdministrator($email, $firstName, $lastName, $password, $confirm);

        if ($error !== null) {
            $_SESSION[self::SESSION_KEY]['flash_error'] = $error;
            $_SESSION[self::SESSION_KEY]['admin_email'] = $email;
            $_SESSION[self::SESSION_KEY]['admin_first_name'] = $firstName;
            $_SESSION[self::SESSION_KEY]['admin_last_name'] = $lastName;
            $this->redirect($this->url('administrator'));
        }

        $_SESSION[self::SESSION_KEY]['admin_email'] = $email;
        $_SESSION[self::SESSION_KEY]['admin_first_name'] = $firstName;
        $_SESSION[self::SESSION_KEY]['admin_last_name'] = $lastName;
        $_SESSION[self::SESSION_KEY]['admin_password'] = $password;

        $this->redirect($this->url('settings'));
    }

    private function validateAdministrator(
        string $email,
        string $firstName,
        string $lastName,
        string $password,
        string $confirm
    ): ?string {
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Enter a valid email address.';
        }

        if ($firstName === '' || $lastName === '') {
            return 'First and last name are required.';
        }

        if (!self::isStrongPassword($password)) {
            return self::PASSWORD_POLICY_MESSAGE;
        }

        if ($password !== $confirm) {
            return 'Password and confirmation do not match.';
        }

        return null;
    }

    /**
     * At least 12 characters with lower, upper and digit. Mirrors the wording
     * of Validator's 'strong_password' rule (8 chars, +special char) without
     * matching it exactly - deliberately stronger for the one account that
     * gets every permission by default - and without calling Validator, which
     * may reach Config.
     */
    private static function isStrongPassword(string $password): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{12,}$/', $password) === 1;
    }

    // ---------------------------------------------------------------
    // Step 5: settings
    // ---------------------------------------------------------------

    private function stepSettings(): void
    {
        if (!$this->administratorResolved()) {
            $this->redirect($this->url('administrator'));
        }

        $this->present('Install/settings', [
            'currentStep' => 'settings',
            'error' => $this->takeFlashError(),
            'domain' => $_SESSION[self::SESSION_KEY]['domain'] ?? $this->defaultDomain(),
            'scheme' => $_SESSION[self::SESSION_KEY]['scheme'] ?? $this->defaultScheme(),
            'timezone' => $_SESSION[self::SESSION_KEY]['timezone'] ?? 'UTC',
            'company' => $_SESSION[self::SESSION_KEY]['company'] ?? '',
        ]);
    }

    private function submitSettings(): void
    {
        if (!$this->administratorResolved()) {
            $this->redirect($this->url('administrator'));
        }

        $domain = trim((string) ($_POST['domain'] ?? ''));
        $scheme = ($_POST['scheme'] ?? '') === 'http' ? 'http' : 'https';
        $timezone = (string) ($_POST['timezone'] ?? '');
        $company = trim((string) ($_POST['company'] ?? ''));

        if ($domain === '' || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $_SESSION[self::SESSION_KEY]['flash_error'] = 'Enter a domain and choose a valid timezone.';
            $_SESSION[self::SESSION_KEY]['domain'] = $domain;
            $_SESSION[self::SESSION_KEY]['scheme'] = $scheme;
            $_SESSION[self::SESSION_KEY]['company'] = $company;
            $this->redirect($this->url('settings'));
        }

        $_SESSION[self::SESSION_KEY]['domain'] = $domain;
        $_SESSION[self::SESSION_KEY]['scheme'] = $scheme;
        $_SESSION[self::SESSION_KEY]['timezone'] = $timezone;
        $_SESSION[self::SESSION_KEY]['company'] = $company !== '' ? $company : 'Aureo';

        $this->redirect($this->url('complete'));
    }

    private function defaultDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return is_string($host) ? $host : '';
    }

    private function defaultScheme(): string
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return (is_string($https) && $https !== '' && strtolower($https) !== 'off') ? 'https' : 'http';
    }

    // ---------------------------------------------------------------
    // Step 6: complete
    // ---------------------------------------------------------------

    private function stepComplete(): void
    {
        if (!$this->settingsResolved()) {
            $this->redirect($this->url('settings'));
        }

        $this->present('Install/complete', [
            'currentStep' => 'complete',
            'error' => $this->takeFlashError(),
            'done' => false,
        ]);
    }

    /**
     * The only step that writes anything to disk. Nothing above this point -
     * the database, administrator and settings steps - mutates anything
     * outside $_SESSION. Migrations and the administrator update run BEFORE
     * writeLock(), so a mid-flight failure leaves the installer usable rather
     * than locking a half-configured site.
     */
    private function submitComplete(): void
    {
        if (!$this->settingsResolved()) {
            $this->redirect($this->url('settings'));
        }

        $session = $_SESSION[self::SESSION_KEY] ?? [];

        $credentials = [
            'host' => (string) ($session['db_host'] ?? ''),
            'name' => (string) ($session['db_name'] ?? ''),
            'user' => (string) ($session['db_user'] ?? ''),
            'password' => (string) ($session['db_password'] ?? ''),
        ];

        try {
            $this->installer->runMigrations($credentials);

            $db = $this->installer->connectToDatabase($credentials);
            $this->installer->updateAdministrator(
                $db,
                (string) ($session['admin_email'] ?? ''),
                (string) ($session['admin_first_name'] ?? ''),
                (string) ($session['admin_last_name'] ?? ''),
                (string) ($session['admin_password'] ?? '')
            );

            $configValues = $this->installer->buildConfigValues([
                'db_host' => $credentials['host'],
                'db_name' => $credentials['name'],
                'db_user' => $credentials['user'],
                'db_password' => $credentials['password'],
                'domain' => (string) ($session['domain'] ?? ''),
                'scheme' => (string) ($session['scheme'] ?? 'https'),
                'timezone' => (string) ($session['timezone'] ?? 'UTC'),
                'company' => (string) ($session['company'] ?? 'Aureo'),
            ]);

            $target = $this->installer->firstWritableTarget($this->documentRoot());

            if ($target === null) {
                throw new \RuntimeException('No writable location was found for the configuration file.');
            }

            $this->installer->writeConfig($target, $configValues);

            // The pointer is only needed when the config landed somewhere
            // ConfigLoader would not otherwise find it (its default in-tree
            // candidate needs no pointer at all).
            if ($target !== $this->appRoot . '/config/config.php') {
                $this->installer->writePointer($target);
            }

            $this->installer->writeLock($this->version());
        } catch (Throwable $e) {
            $this->present('Install/complete', [
                'currentStep' => 'complete',
                'error' => $e->getMessage(),
                'done' => false,
            ]);

            return;
        }

        // The database password - and everything else the operator typed -
        // must not outlive the install.
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::CSRF_KEY]);

        $this->present('Install/complete', [
            'currentStep' => 'complete',
            'error' => null,
            'done' => true,
            'loginUrl' => $this->basePath . '/login',
        ]);
    }

    private function version(): string
    {
        $path = $this->appRoot . '/VERSION';

        return is_file($path) ? trim((string) file_get_contents($path)) : 'unknown';
    }

    // ---------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------

    private function exposureResolved(): bool
    {
        return ($_SESSION[self::SESSION_KEY]['exposure_ok'] ?? false) === true;
    }

    private function databaseResolved(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['db_name']) && $_SESSION[self::SESSION_KEY]['db_name'] !== '';
    }

    private function administratorResolved(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['admin_email']) && $_SESSION[self::SESSION_KEY]['admin_email'] !== '';
    }

    private function settingsResolved(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['domain']) && $_SESSION[self::SESSION_KEY]['domain'] !== '';
    }

    private function takeFlashError(): ?string
    {
        $error = $_SESSION[self::SESSION_KEY]['flash_error'] ?? null;
        unset($_SESSION[self::SESSION_KEY]['flash_error']);

        return $error;
    }

    private function documentRoot(): ?string
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;

        return is_string($documentRoot) && $documentRoot !== '' ? $documentRoot : null;
    }

    // ---------------------------------------------------------------
    // CSRF, rendering, redirects
    // ---------------------------------------------------------------

    /**
     * Own token, own comparison. renderCSRFToken() from FormComponents.php is
     * not used - it reads $_SESSION['csrf_token'], which belongs to
     * CsrfMiddleware and does not exist here.
     */
    private function csrfToken(): string
    {
        $token = $_SESSION[self::CSRF_KEY] ?? '';

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return $token;
    }

    private function assertCsrf(): void
    {
        $token = $_SESSION[self::CSRF_KEY] ?? '';
        $submitted = (string) ($_POST[self::CSRF_FIELD] ?? '');

        if (!is_string($token) || $token === '' || !hash_equals($token, $submitted)) {
            $this->redirect($this->url(''));
        }
    }

    private function url(string $step): string
    {
        return $this->basePath . '/install' . ($step === '' ? '' : '/' . $step);
    }

    /**
     * Adds the data every step view needs and hands off to the overridable
     * render(). Kept separate from render() itself - rather than inlined
     * there - because a testable subclass overrides render() wholesale to
     * capture output instead of emitting it (see
     * tests/Unit/Controllers/InstallControllerTest.php), and csrf/assetBase
     * still have to reach $data in that scenario for the test to assert on.
     */
    private function present(string $view, array $data = []): void
    {
        $data['csrf'] = $this->csrfToken();
        $data['assetBase'] = $this->basePath;
        $data['steps'] = self::STEPS;
        $data['currentStep'] = $data['currentStep'] ?? 'preflight';

        $this->render($view, $data);
    }

    protected function render(string $view, array $data = []): void
    {
        extract($data);

        // No ViewHelpers.php: asset() needs Config, and Config is exactly what
        // does not exist yet. Views take $assetBase and build URLs themselves.
        include BASE_PATH . "/../src/Views/{$view}.php";
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
