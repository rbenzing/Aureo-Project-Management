<?php

// file: Services/PreflightService.php
declare(strict_types=1);

namespace App\Services;

/**
 * Environment checks run before an installation is allowed to proceed, and by
 * bin/preflight.php for operators who have SSH.
 *
 * Every piece of I/O is injected. That is not ceremony: the checks exist to
 * describe hosts we do not have, so the tests have to be able to describe them
 * too, and a service that called is_writable() directly could only ever be
 * tested against the machine running the suite.
 */
final class PreflightService
{
    public const SEVERITY_PASS = 'pass';
    public const SEVERITY_WARN = 'warn';
    public const SEVERITY_FAIL = 'fail';

    /** Matches the composer.json floor and the config.platform.php pin. */
    public const MINIMUM_PHP = '8.2.0';

    /** Extensions the application cannot boot without. */
    private const REQUIRED_EXTENSIONS = [
        'pdo' => 'PDO',
        'pdo_mysql' => 'PDO MySQL driver',
        'mbstring' => 'Multibyte string',
        'json' => 'JSON',
    ];

    /** Extensions that degrade a feature rather than the application. */
    private const OPTIONAL_EXTENSIONS = [
        'openssl' => 'OpenSSL (needed for SMTP over TLS)',
    ];

    /** @var callable(string):bool */
    private $extensionLoaded;

    /** @var callable(string):bool */
    private $isWritable;

    /** @var callable(string):bool */
    private $pathExists;

    public function __construct(
        private readonly string $phpVersion = PHP_VERSION,
        ?callable $extensionLoaded = null,
        ?callable $isWritable = null,
        ?callable $pathExists = null,
        private readonly string $sessionSavePath = ''
    ) {
        $this->extensionLoaded = $extensionLoaded ?? static fn (string $e): bool => extension_loaded($e);
        $this->isWritable = $isWritable ?? static fn (string $p): bool => is_writable($p);
        $this->pathExists = $pathExists ?? static fn (string $p): bool => file_exists($p);
    }

    /**
     * @return list<array{id:string,label:string,severity:string,detail:string,remedy:string}>
     */
    public function run(string $appRoot, ?string $documentRoot = null): array
    {
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

        $checks = [$this->checkPhpVersion()];

        foreach (self::REQUIRED_EXTENSIONS as $extension => $label) {
            $checks[] = $this->checkExtension($extension, $label, self::SEVERITY_FAIL);
        }
        foreach (self::OPTIONAL_EXTENSIONS as $extension => $label) {
            $checks[] = $this->checkExtension($extension, $label, self::SEVERITY_WARN);
        }

        $checks[] = $this->checkWritableDirectory('writable_log', 'Log directory', $appRoot . '/log');
        $checks[] = $this->checkWritableDirectory('writable_cache', 'Cache directory', $appRoot . '/var/cache');
        $checks[] = $this->checkSessionPath();
        $checks[] = $this->checkConfigTarget($appRoot, $documentRoot);
        $checks[] = $this->checkVendor($appRoot);
        $checks[] = $this->checkAssets($appRoot);
        $checks[] = $this->checkLayout($appRoot, $documentRoot);

        return $checks;
    }

    /** @param list<array{severity:string, ...}> $checks */
    public static function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['severity'] === self::SEVERITY_FAIL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Candidate locations for the generated configuration file, most preferred
     * first. Canonical: InstallerService::configTargets() delegates here, so
     * "where can we write?" has exactly one answer. Preflight needs it without
     * constructing the installer, which is why it lives on this side.
     *
     * @return list<string>
     */
    public static function configTargets(string $appRoot, ?string $documentRoot): array
    {
        $targets = [];

        if ($documentRoot !== null && $documentRoot !== '') {
            $normalised = rtrim(str_replace('\\', '/', $documentRoot), '/');

            // rtrim('/', '/') is '' and dirname('') is '.', which would offer
            // a relative candidate resolved against whatever the CWD happens
            // to be. dirname('/') is '/' and dirname('C:/') is 'C:/'. Writing
            // credentials to the filesystem root, or to an unknown relative
            // directory, is never what we want.
            $parent = $normalised === '' ? '' : \dirname($normalised);

            if (
                $parent !== ''
                && $parent !== '.'
                && $parent !== $normalised
                && $parent !== '/'
                && preg_match('#^[A-Za-z]:/?$#', $parent) !== 1
            ) {
                $targets[] = $parent . '/aureo-config.php';
            }
        }

        $targets[] = rtrim(str_replace('\\', '/', $appRoot), '/') . '/config/config.php';

        return $targets;
    }

    /** @return array{id:string,label:string,severity:string,detail:string,remedy:string} */
    private function checkPhpVersion(): array
    {
        $ok = version_compare($this->phpVersion, self::MINIMUM_PHP, '>=');

        return [
            'id' => 'php_version',
            'label' => 'PHP version',
            'severity' => $ok ? self::SEVERITY_PASS : self::SEVERITY_FAIL,
            'detail' => 'Running PHP ' . $this->phpVersion . '; ' . self::MINIMUM_PHP . ' or newer is required.',
            'remedy' => $ok ? '' : 'Switch the site to PHP ' . self::MINIMUM_PHP . ' or newer in your hosting control panel.',
        ];
    }

    /** @return array{id:string,label:string,severity:string,detail:string,remedy:string} */
    private function checkExtension(string $extension, string $label, string $failureSeverity): array
    {
        $loaded = ($this->extensionLoaded)($extension);

        return [
            'id' => 'ext_' . $extension,
            'label' => $label . ' extension',
            'severity' => $loaded ? self::SEVERITY_PASS : $failureSeverity,
            'detail' => $loaded ? $extension . ' is loaded.' : $extension . ' is not loaded.',
            'remedy' => $loaded ? '' : 'Enable the ' . $extension . ' extension in php.ini, or ask your host to.',
        ];
    }

    /**
     * A directory that does not exist yet is acceptable as long as its parent
     * is writable - the installer creates it. Only an unwritable parent is
     * genuinely fatal, and treating "absent" as fatal would fail every fresh
     * archive extraction, where log/ and var/cache/ are empty and so are not
     * carried by git.
     *
     * @return array{id:string,label:string,severity:string,detail:string,remedy:string}
     */
    private function checkWritableDirectory(string $id, string $label, string $path): array
    {
        $exists = ($this->pathExists)($path);
        $target = $exists ? $path : \dirname($path);
        $ok = ($this->isWritable)($target);

        return [
            'id' => $id,
            'label' => $label,
            'severity' => $ok ? self::SEVERITY_PASS : self::SEVERITY_FAIL,
            'detail' => $exists
                ? $path . ' ' . ($ok ? 'is writable.' : 'is not writable.')
                : $path . ' does not exist; its parent ' . ($ok ? 'is writable, so it will be created.' : 'is not writable.'),
            'remedy' => $ok ? '' : 'Grant the web server write access to ' . $target . ' (commonly chmod 0775).',
        ];
    }

    /** @return array{id:string,label:string,severity:string,detail:string,remedy:string} */
    private function checkSessionPath(): array
    {
        if ($this->sessionSavePath === '') {
            return [
                'id' => 'session_path',
                'label' => 'Session storage',
                'severity' => self::SEVERITY_WARN,
                'detail' => 'session.save_path is empty, so PHP uses its compiled-in default. It cannot be verified from here.',
                'remedy' => 'If the installer loses your answers between steps, set session.save_path to a writable directory.',
            ];
        }

        $ok = ($this->isWritable)($this->sessionSavePath);

        return [
            'id' => 'session_path',
            'label' => 'Session storage',
            'severity' => $ok ? self::SEVERITY_PASS : self::SEVERITY_FAIL,
            'detail' => $this->sessionSavePath . ' ' . ($ok ? 'is writable.' : 'is not writable.'),
            'remedy' => $ok ? '' : 'The installer carries your answers between steps in the session. Make ' . $this->sessionSavePath . ' writable.',
        ];
    }

    /** @return array{id:string,label:string,severity:string,detail:string,remedy:string} */
    private function checkConfigTarget(string $appRoot, ?string $documentRoot): array
    {
        foreach (self::configTargets($appRoot, $documentRoot) as $target) {
            $directory = \dirname($target);
            if (($this->isWritable)($directory)) {
                return [
                    'id' => 'config_target',
                    'label' => 'Configuration location',
                    'severity' => self::SEVERITY_PASS,
                    'detail' => 'Configuration will be written to ' . $target . '.',
                    'remedy' => '',
                ];
            }
        }

        return [
            'id' => 'config_target',
            'label' => 'Configuration location',
            'severity' => self::SEVERITY_FAIL,
            'detail' => 'None of the candidate locations is writable: '
                . implode(', ', self::configTargets($appRoot, $documentRoot)) . '.',
            'remedy' => 'Grant the web server write access to one of those directories.',
        ];
    }

    /** @return array{id:string,label:string,severity:string,detail:string,remedy:string} */
    private function checkVendor(string $appRoot): array
    {
        $path = $appRoot . '/vendor/autoload.php';
        $ok = ($this->pathExists)($path);

        return [
            'id' => 'vendor',
            'label' => 'Composer dependencies',
            'severity' => $ok ? self::SEVERITY_PASS : self::SEVERITY_FAIL,
            'detail' => $ok ? 'vendor/autoload.php is present.' : 'vendor/autoload.php is missing.',
            'remedy' => $ok ? '' : 'Use the release archive, which bundles vendor/, or run "composer install --no-dev".',
        ];
    }

    /** @return array{id:string,label:string,severity:string,detail:string,remedy:string} */
    private function checkAssets(string $appRoot): array
    {
        $path = $appRoot . '/public/assets/css/styles.css';
        $ok = ($this->pathExists)($path);

        return [
            'id' => 'assets',
            'label' => 'Compiled stylesheet',
            'severity' => $ok ? self::SEVERITY_PASS : self::SEVERITY_FAIL,
            'detail' => $ok ? 'public/assets/css/styles.css is present.' : 'public/assets/css/styles.css is missing.',
            'remedy' => $ok ? '' : 'Use the release archive, which bundles the compiled stylesheet, or run "npm install && npm run build".',
        ];
    }

    /**
     * Reports which deployment layout is in effect. Only the recommended one
     * is a clean pass: the drop-in layout depends on .htaccess or web.config
     * being honoured, and nginx honours neither.
     *
     * @return array{id:string,label:string,severity:string,detail:string,remedy:string}
     */
    private function checkLayout(string $appRoot, ?string $documentRoot): array
    {
        if ($documentRoot === null || $documentRoot === '') {
            return [
                'id' => 'layout',
                'label' => 'Deployment layout',
                'severity' => self::SEVERITY_WARN,
                'detail' => 'The document root is not known (this is normal on the command line).',
                'remedy' => 'Run the web installer to have the layout verified.',
            ];
        }

        $normalised = rtrim(str_replace('\\', '/', $documentRoot), '/');

        if ($normalised === $appRoot . '/public') {
            return [
                'id' => 'layout',
                'label' => 'Deployment layout',
                'severity' => self::SEVERITY_PASS,
                'detail' => 'Recommended layout: the document root is public/, so nothing outside it is reachable over HTTP.',
                'remedy' => '',
            ];
        }

        if ($normalised === $appRoot) {
            return [
                'id' => 'layout',
                'label' => 'Deployment layout',
                'severity' => self::SEVERITY_WARN,
                'detail' => 'Drop-in layout: the document root is the application root, so application files are only hidden by .htaccess or web.config.',
                'remedy' => 'Point the document root at public/ if your host allows it. On nginx, apply the deny rules from docs/DEPLOYMENT.md.',
            ];
        }

        return [
            'id' => 'layout',
            'label' => 'Deployment layout',
            'severity' => self::SEVERITY_WARN,
            'detail' => 'Unrecognised layout: document root ' . $normalised . ' is neither ' . $appRoot . ' nor ' . $appRoot . '/public.',
            'remedy' => 'Subdirectory installations are not supported. Point the document root at the application root or at public/.',
        ];
    }
}
