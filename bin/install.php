<?php

declare(strict_types=1);

/**
 * Post-install orchestrator.
 *
 * Runs automatically after `composer install` (and on-demand via `composer
 * post-install`). Each step is idempotent and skips itself if already done.
 *
 *   1. npm install      (skip if node_modules/ exists)
 *   2. npm run build    (skip if public/assets/css/styles.css exists and is fresh)
 *   3. composer setup --skip-if-configured  (skip if .env already has real DB creds)
 *
 * If npm isn't on PATH, those steps are skipped with a warning rather than
 * failing the whole install.
 */

const ROOT = __DIR__ . '/..';

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function warn(string $msg): void { fwrite(STDOUT, '  ! ' . $msg . PHP_EOL); }
function err(string $msg): void { fwrite(STDERR, $msg . PHP_EOL); }
function step(string $msg): void { out(''); out('=== ' . $msg . ' ==='); }

function which(string $bin): ?string
{
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'command -v';
    $out = [];
    exec("{$cmd} {$bin} 2>" . (stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null'), $out, $code);
    return ($code === 0 && !empty($out)) ? trim($out[0]) : null;
}

function run(string $cmd, ?string $cwd = null): int
{
    if ($cwd !== null) {
        $prev = getcwd();
        chdir($cwd);
    }
    passthru($cmd, $code);
    if (isset($prev)) {
        chdir($prev);
    }
    return $code;
}

function stepNpmInstall(): void
{
    step('Step 1/3 — Frontend dependencies (npm install)');

    if (is_dir(ROOT . '/node_modules')) {
        out('  node_modules/ already exists, skipping.');
        return;
    }
    if (which('npm') === null) {
        warn('npm not found on PATH. Skipping. Install Node.js, then run `npm install && npm run build`.');
        return;
    }
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'npm.cmd install' : 'npm install';
    $code = run($cmd, ROOT);
    if ($code !== 0) {
        warn("npm install exited with code {$code}. Continuing anyway.");
    }
}

function stepNpmBuild(): void
{
    step('Step 2/3 — Build CSS (npm run build)');

    $css = ROOT . '/public/assets/css/styles.css';
    if (file_exists($css) && filesize($css) > 0) {
        out('  CSS already built, skipping. (Run `npm run build` to rebuild.)');
        return;
    }
    if (!is_dir(ROOT . '/node_modules')) {
        warn('node_modules/ missing, skipping build.');
        return;
    }
    if (which('npm') === null) {
        warn('npm not found on PATH. Skipping.');
        return;
    }
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'npm.cmd run build' : 'npm run build';
    $code = run($cmd, ROOT);
    if ($code !== 0) {
        warn("npm run build exited with code {$code}. Continuing anyway.");
    }
}

// --- main ---------------------------------------------------------------

out('');
out('Aureo Project Management — post-install');

stepNpmInstall();
stepNpmBuild();

step('Next: database setup');
out('Composer pipes STDIN, which breaks interactive prompts.');
out('Run the setup script directly in your terminal:');
out('');
out('  php bin/setup.php');
out('');
out('Then:');
out('  composer start    (http://localhost:8000)');
out('  composer pma      (http://localhost:8081)');
out('');
