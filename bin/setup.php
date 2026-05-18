<?php

declare(strict_types=1);

/**
 * Interactive installer for Aureo Project Management.
 *
 * Run via: composer setup
 *
 * Steps:
 *   1. Ensure .env exists (copy from .env.example)
 *   2. Prompt for DB credentials (defaults to MySQL out-of-the-box: root@127.0.0.1, no password)
 *   3. Test connection, create database if missing
 *   4. Persist values to .env
 *   5. Run Phinx migrations
 *   6. Optionally import sample-data.sql via PDO
 */

const ROOT = __DIR__ . '/..';

require_once ROOT . '/vendor/autoload.php';

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function err(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
}

function ask(string $label, string $default = '', bool $secret = false): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $label . $suffix . ': ');

    if ($secret && stripos(PHP_OS, 'WIN') !== 0) {
        // POSIX: disable echo
        @shell_exec('stty -echo');
        $raw = fgets(STDIN);
        @shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    } else {
        $raw = fgets(STDIN);
    }

    if ($raw === false) {
        throw new RuntimeException(
            "STDIN closed before input could be read.\n" .
            "If you ran this via `composer setup`, Composer is piping STDIN.\n" .
            "Run directly instead:  php bin/setup.php\n" .
            "Or accept defaults non-interactively:  php bin/setup.php --yes"
        );
    }

    $line = trim($raw);
    return $line === '' ? $default : $line;
}

function confirm(string $label, bool $default = true): bool
{
    $hint = $default ? 'Y/n' : 'y/N';
    $answer = strtolower(ask("{$label} ({$hint})", $default ? 'y' : 'n'));
    return in_array($answer, ['y', 'yes'], true);
}

function envPath(): string
{
    return ROOT . '/.env';
}

function ensureEnvFile(): void
{
    $env = envPath();
    if (!file_exists($env)) {
        if (!file_exists(ROOT . '/.env.example')) {
            throw new RuntimeException('.env.example not found; cannot bootstrap .env');
        }
        copy(ROOT . '/.env.example', $env);
        out('Created .env from .env.example');
    }
}

/**
 * @param array<string, string> $updates
 */
function updateEnv(array $updates): void
{
    $path = envPath();
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    foreach ($updates as $key => $value) {
        $escaped = preg_match('/\s|"|#/', $value) === 1
            ? '"' . str_replace('"', '\"', $value) . '"'
            : $value;
        $line = "{$key}={$escaped}";
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $contents) === 1) {
            $contents = preg_replace($pattern, $line, $contents);
        } else {
            $contents .= PHP_EOL . $line;
        }
    }

    file_put_contents($path, $contents);
}

function connectServer(string $host, int $port, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function createDatabase(PDO $pdo, string $name): void
{
    $quoted = '`' . str_replace('`', '``', $name) . '`';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function runPhinx(): int
{
    $phinx = ROOT . '/vendor/bin/phinx' . (stripos(PHP_OS, 'WIN') === 0 ? '.bat' : '');
    if (!file_exists($phinx)) {
        err('Phinx binary not found; run `composer install` first.');
        return 1;
    }
    $cmd = escapeshellarg($phinx) . ' migrate -c ' . escapeshellarg(ROOT . '/phinx.php');
    passthru($cmd, $code);
    return $code;
}

function importSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read {$file}");
    }
    // Strip line comments starting with -- and block comments
    $pdo->exec($sql);
}

function parseArgs(array $argv): array
{
    $opts = ['yes' => false, 'skip_if_configured' => false, 'sample_data' => null];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--yes' || $arg === '-y') { $opts['yes'] = true; continue; }
        if ($arg === '--skip-if-configured') { $opts['skip_if_configured'] = true; continue; }
        if ($arg === '--no-sample-data') { $opts['sample_data'] = false; continue; }
        if ($arg === '--sample-data') { $opts['sample_data'] = true; continue; }
    }
    return $opts;
}

function looksConfigured(array $env): bool
{
    $name = (string) ($env['DB_NAME'] ?? '');
    $user = (string) ($env['DB_USERNAME'] ?? '');
    $placeholders = ['', 'your_database_name', 'your_database_user'];
    return !in_array($name, $placeholders, true) && !in_array($user, $placeholders, true);
}

// --- Main ---------------------------------------------------------------

try {
    $opts = parseArgs($argv);

    out('=== Aureo Project Management — Setup ===');
    out('');

    ensureEnvFile();

    $existing = parse_ini_file(envPath()) ?: [];

    if ($opts['skip_if_configured'] && looksConfigured($existing)) {
        out('Skipping setup — .env already has DB credentials.');
        out('Re-run `composer setup` if you need to reconfigure.');
        exit(0);
    }

    [$defHost, $defPort] = (function () use ($existing): array {
        $raw = (string) ($existing['DB_HOST'] ?? '127.0.0.1:3306');
        if (str_contains($raw, ':')) {
            [$h, $p] = explode(':', $raw, 2);
            return [$h ?: '127.0.0.1', (int) $p ?: 3306];
        }
        return [$raw ?: '127.0.0.1', 3306];
    })();

    $defUser = (string) ($existing['DB_USERNAME'] ?? 'root');
    if ($defUser === 'your_database_user') {
        $defUser = 'root';
    }
    $defPass = (string) ($existing['DB_PASSWORD'] ?? '');
    if ($defPass === 'your_secure_database_password') {
        $defPass = '';
    }
    $defName = (string) ($existing['DB_NAME'] ?? 'aureo_db');
    if ($defName === 'your_database_name' || $defName === '') {
        $defName = 'aureo_db';
    }

    if ($opts['yes']) {
        out('Running with --yes; using defaults without prompting.');
        [$host, $port, $user, $pass, $name] = [$defHost, $defPort, $defUser, $defPass, $defName];
    } else {
        out('Enter your local MySQL connection details. Press Enter to accept defaults.');
        out('');
        $host = ask('DB host', $defHost);
        $port = (int) ask('DB port', (string) $defPort);
        $user = ask('DB user', $defUser);
        $pass = ask('DB password (leave blank for none)', $defPass, true);
        $name = ask('DB name', $defName);
    }

    out('');
    out("Connecting to mysql://{$user}@{$host}:{$port} ...");

    $pdo = connectServer($host, $port, $user, $pass);
    out('  Connected.');

    out("Ensuring database `{$name}` exists ...");
    createDatabase($pdo, $name);
    out('  Database ready.');

    out('Writing credentials to .env ...');
    $envUpdates = [
        'DB_HOST' => "{$host}:{$port}",
        'DB_USERNAME' => $user,
        'DB_PASSWORD' => $pass,
        'DB_NAME' => $name,
        'DB_CHARSET' => 'utf8mb4',
    ];

    // Default to a dev-friendly profile when running setup interactively.
    // (The Database layer requires a non-empty password when APP_ENV=production,
    // which would block stock local MySQL with no root password.)
    if (($existing['APP_ENV'] ?? 'production') === 'production') {
        $envUpdates['APP_ENV'] = 'local';
        $envUpdates['APP_DEBUG'] = 'true';
    }

    updateEnv($envUpdates);
    out('  .env updated.');

    out('');
    out('Running Phinx migrations ...');
    $code = runPhinx();
    if ($code !== 0) {
        err("Phinx exited with code {$code}");
        exit($code);
    }

    out('');
    out('Set the admin password for admin@aureo.us.');
    if ($opts['yes']) {
        $adminPass = 'password';
        out('  Using default password "password" (--yes).');
    } else {
        $adminPass = ask('Admin password (leave blank to keep "password")', 'password', true);
    }

    $dbPdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $dbPdo->prepare("UPDATE users SET password_hash = :h WHERE email = 'admin@aureo.us'");
    $stmt->execute([':h' => password_hash($adminPass, PASSWORD_ARGON2ID)]);
    out('  Admin password set.');

    out('');
    $shouldImport = $opts['sample_data'];
    if ($shouldImport === null) {
        $shouldImport = $opts['yes']
            ? false
            : confirm('Import sample data (sample-data.sql)?', false);
    }

    if (file_exists(ROOT . '/sample-data.sql') && $shouldImport) {
        out('Importing sample data ...');
        importSqlFile($dbPdo, ROOT . '/sample-data.sql');
        out('  Sample data imported.');
    }

    out('');
    out('Setup complete. Start the dev server with:  composer start');
    out('Then open http://localhost:8000');
} catch (Throwable $e) {
    err('');
    err('Setup failed: ' . $e->getMessage());
    err('Hint: verify mysqld is running and credentials are correct.');
    exit(1);
}
