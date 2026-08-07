<?php

// file: Services/InstallerService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\InstallGate;
use App\Utils\PasswordHasher;
use PDO;
use Phinx\Config\Config as PhinxConfig;
use Phinx\Migration\Manager;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * The install engine: config target selection, secret generation, database
 * connect/create, user count, Phinx migrations, administrator update, and
 * config/pointer/lock writing.
 *
 * InstallController (the web flow) and bin/setup.php (the CLI flow) both
 * drive this class rather than duplicating any of it, the same way
 * bin/preflight.php shares PreflightService with the web installer's first
 * step.
 */
final class InstallerService
{
    public const POINTER_FILE = 'config/config-path.php';

    public function __construct(private readonly string $appRoot)
    {
    }

    /**
     * Delegates to PreflightService::configTargets($this->appRoot, $documentRoot).
     * Do not reimplement the list: preflight reports "we can write here" and
     * the installer then writes there, so a divergence between the two means
     * preflight passes and the install fails.
     *
     * @return list<string> absolute candidate paths, most preferred first
     */
    public function configTargets(?string $documentRoot): array
    {
        return PreflightService::configTargets($this->appRoot, $documentRoot);
    }

    /**
     * The first candidate whose directory is genuinely writable, or null when
     * none is. PreflightService can only report that state as a check; this
     * is what actually acts on it when the install reaches the write step.
     */
    public function firstWritableTarget(?string $documentRoot): ?string
    {
        foreach ($this->configTargets($documentRoot) as $target) {
            if (is_writable(\dirname($target))) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Connection with no database selected, used to check for / create the
     * target database before it necessarily exists.
     *
     * @param array{host:string,name:string,user:string,password:string} $credentials
     */
    public function connectToServer(array $credentials): PDO
    {
        $address = self::splitHostAndPort($credentials['host']);

        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $address['host'], $address['port']);

        return new PDO($dsn, $credentials['user'], $credentials['password'], $this->pdoOptions());
    }

    /** @param array{host:string,name:string,user:string,password:string} $credentials */
    public function connectToDatabase(array $credentials): PDO
    {
        $address = self::splitHostAndPort($credentials['host']);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $address['host'],
            $address['port'],
            $credentials['name']
        );

        return new PDO($dsn, $credentials['user'], $credentials['password'], $this->pdoOptions());
    }

    public function databaseExists(PDO $server, string $name): bool
    {
        $statement = $server->prepare(
            'SELECT COUNT(*) FROM `information_schema`.`SCHEMATA` WHERE `SCHEMA_NAME` = :schema_name'
        );
        $statement->execute([':schema_name' => $name]);

        return ((int) $statement->fetchColumn()) > 0;
    }

    /**
     * A database name cannot be a bound parameter in CREATE DATABASE - PDO's
     * native prepares only accept placeholders where a *value* is expected,
     * not where an *identifier* is, and MySQL's grammar agrees. Validating
     * against a strict allowlist before interpolating is therefore not a
     * belt-and-suspenders addition to parameterisation; it is the only
     * defence this call has.
     */
    public function createDatabase(PDO $server, string $name): void
    {
        if (preg_match('/^[A-Za-z0-9_$]+$/', $name) !== 1) {
            throw new RuntimeException('Refusing to create a database with an unsafe name: ' . $name);
        }

        $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Rows in `users`, or null when that cannot be established.
     *
     * Null is load-bearing, and it means "unknown", not "empty". Every
     * failure lands here: server unreachable, rotated credentials, exhausted
     * connections, `users` not yet created. InstallGate::decide() refuses the
     * install route on null whenever a configuration already resolves, so
     * widening what is caught here only ever makes the gate more cautious -
     * never less. Do not "improve" this by letting exceptions escape either:
     * this runs on the code path every single request takes, not just
     * /install.
     *
     * @param array{host:string,name:string,user:string,password:string} $credentials
     */
    public function countUsers(array $credentials): ?int
    {
        try {
            $statement = $this->connectToDatabase($credentials)->query('SELECT COUNT(*) FROM `users`');

            if ($statement === false) {
                return null;
            }

            return (int) $statement->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Phinx through its PHP API rather than vendor/bin/phinx. Shared hosting
     * routinely disables proc_open and shell_exec, so shelling out is the one
     * approach guaranteed to fail on the hosts this installer exists for.
     *
     * @param array{host:string,name:string,user:string,password:string} $credentials
     * @return string buffered migration output, for display
     */
    public function runMigrations(array $credentials): string
    {
        $address = self::splitHostAndPort($credentials['host']);

        $paths = ['migrations' => $this->appRoot . '/db/migrations'];
        if (is_dir($this->appRoot . '/db/seeds')) {
            $paths['seeds'] = $this->appRoot . '/db/seeds';
        }

        $config = new PhinxConfig([
            'paths' => $paths,
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'install',
                'install' => [
                    'adapter' => 'mysql',
                    'host' => $address['host'],
                    'port' => $address['port'],
                    'name' => $credentials['name'],
                    'user' => $credentials['user'],
                    'pass' => $credentials['password'],
                    'charset' => 'utf8mb4',
                ],
            ],
        ]);

        $output = new BufferedOutput();
        (new Manager($config, new ArrayInput([]), $output))->migrate('install');

        return $output->fetch();
    }

    /**
     * The canonical migration seeds users row 1 with role_id 1, which it wired
     * to all 55 permissions. Updating that row keeps the wiring; inserting a
     * second user would leave the seeded admin@aureo.us account live with its
     * default password of "password".
     */
    public function updateAdministrator(
        PDO $db,
        string $email,
        string $firstName,
        string $lastName,
        string $password
    ): void {
        $statement = $db->prepare(
            'UPDATE `users`
                SET `email` = :email, `first_name` = :first_name, `last_name` = :last_name,
                    `password_hash` = :password_hash, `is_active` = 1, `is_deleted` = 0
              WHERE `id` = 1'
        );
        $statement->execute([
            ':email' => $email,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':password_hash' => self::hashPassword($password),
        ]);

        if ($statement->rowCount() > 0) {
            return;
        }

        // rowCount() is 0 both when row 1 is absent and when the submitted
        // values happen to match what is already stored, so confirm before
        // inserting rather than trusting the count.
        $exists = $db->query('SELECT COUNT(*) FROM `users` WHERE `id` = 1');
        if ($exists !== false && (int) $exists->fetchColumn() > 0) {
            return;
        }

        $insert = $db->prepare(
            'INSERT INTO `users` (`id`, `guid`, `company_id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `is_active`, `is_deleted`)
             VALUES (1, UUID(), NULL, 1, :insert_first_name, :insert_last_name, :insert_email, :insert_password_hash, 1, 0)'
        );
        $insert->execute([
            ':insert_first_name' => $firstName,
            ':insert_last_name' => $lastName,
            ':insert_email' => $email,
            ':insert_password_hash' => self::hashPassword($password),
        ]);
    }

    /**
     * A PHP file returning an array, which is what ConfigLoader rungs 3 and 4
     * consume via "return require $path". var_export handles quoting; no value
     * is ever interpolated into the source.
     *
     * @param array<string,string> $values
     */
    public function renderConfig(array $values): string
    {
        $lines = [
            '<?php',
            '',
            '// Generated by the Aureo installer. This file contains credentials - keep it out of version control.',
            '',
            'return [',
        ];

        foreach ($values as $key => $value) {
            $lines[] = '    ' . var_export((string) $key, true) . ' => ' . var_export($value, true) . ',';
        }

        $lines[] = '];';

        return implode("\n", $lines) . "\n";
    }

    /** @param array<string,string> $values */
    public function writeConfig(string $targetPath, array $values): void
    {
        $this->putFile($targetPath, $this->renderConfig($values));
    }

    public function writePointer(string $targetPath): void
    {
        $this->putFile(
            $this->appRoot . '/' . self::POINTER_FILE,
            "<?php\n\n// Written by the Aureo installer: where the configuration file lives.\n\nreturn "
            . var_export($targetPath, true) . ";\n"
        );
    }

    /**
     * The lock is the single most important control on this route: an
     * installer that can be re-run is a remote takeover. Contents are for
     * humans only - InstallGate cares solely that the file exists.
     */
    public function writeLock(string $version): void
    {
        $this->putFile(
            $this->lockPath(),
            "Aureo {$version} installed.\n"
            . "Delete this file only if you intend to reinstall from scratch, which will overwrite the configuration.\n"
        );
    }

    public function lockPath(): string
    {
        return $this->appRoot . '/' . InstallGate::LOCK_FILE;
    }

    /**
     * DB_HOST carries an optional port, as "localhost:3306". Split on the LAST
     * colon and only when what follows is numeric: "localhost:sock" is a host,
     * not a port, and splitting it would silently produce port 0.
     *
     * @return array{host:string,port:int}
     */
    public static function splitHostAndPort(string $dbHost): array
    {
        $position = strrpos($dbHost, ':');

        if ($position !== false) {
            $suffix = substr($dbHost, $position + 1);
            if ($suffix !== '' && ctype_digit($suffix)) {
                return ['host' => substr($dbHost, 0, $position), 'port' => (int) $suffix];
            }
        }

        return ['host' => $dbHost, 'port' => 3306];
    }

    public static function generatePepper(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @see PasswordHasher for why the algorithm is chosen rather than named.
     *
     * Kept as a delegation: the installer is one of several callers, and
     * hashing policy is not the installer's to own.
     */
    public static function preferredPasswordAlgorithm(): string|int|null
    {
        return PasswordHasher::algorithm();
    }

    public static function hashPassword(string $plain): string
    {
        return PasswordHasher::hash($plain);
    }

    /**
     * Assembles the values ConfigLoader::load() and Config::validateEnvironment()
     * both require, plus the operator's answers from the settings step.
     *
     * ConfigLoader::REQUIRED is the live, public source of truth for the
     * first set. Config::REQUIRED_ENV lists the identical five keys
     * (APP_DEBUG, DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD - verified by
     * reading src/Core/Config.php) but is declared `private`, so it cannot be
     * read from here without either reflection or widening that class's
     * visibility - both out of scope for this service. Should the two ever
     * diverge, ConfigLoader::REQUIRED must be updated to match, because it is
     * the one this method - and ConfigLoader::load() itself - can actually
     * see.
     *
     * @param array<string,string> $answers
     * @return array<string,string>
     */
    public function buildConfigValues(array $answers): array
    {
        $scheme = $answers['scheme'] ?? 'https';

        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_HOST' => $answers['db_host'] ?? '',
            'DB_NAME' => $answers['db_name'] ?? '',
            'DB_USERNAME' => $answers['db_user'] ?? '',
            'DB_PASSWORD' => $answers['db_password'] ?? '',
            'DB_CHARSET' => 'utf8mb4',
            'APP_DOMAIN' => $answers['domain'] ?? '',
            'APP_SCHEME' => $scheme,
            'APP_TIMEZONE' => $answers['timezone'] ?? 'UTC',
            'APP_COMPANY' => $answers['company'] ?? '',
            'SESSION_SECURE' => $scheme === 'https' ? 'true' : 'false',
            // Read by nothing today (see PASSWORD_PEPPER in .env.example and
            // the ConfigLoader doc comment) - generated anyway because it is
            // cheap and forward-compatible. Must never be described as
            // securing anything in UI copy or documentation.
            'PASSWORD_PEPPER' => self::generatePepper(),
        ];
    }

    /** @return array<int,mixed> */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private function putFile(string $path, string $contents): void
    {
        $directory = \dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create directory: ' . $directory);
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write: ' . $path);
        }

        // Best effort. chmod is a no-op on Windows, which is fine: the files
        // that matter are also denied by .htaccess and web.config.
        chmod($path, 0600);
    }
}
