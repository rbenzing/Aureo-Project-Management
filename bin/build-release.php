<?php

declare(strict_types=1);

/**
 * Builds the distributable archive.
 *
 *   php bin/build-release.php [--output=dist]
 *
 * `git archive` supplies the tracked baseline and honours the export-ignore
 * rules in .gitattributes. vendor/ and the compiled stylesheet are added on
 * top: vendor/ is never tracked by git, and the archive must boot on a host
 * with neither Composer nor Node, so both are mandatory in the zip regardless
 * of what git alone would produce.
 *
 * Run `composer install --no-dev --optimize-autoloader` and `npm run build`
 * before this. It refuses rather than guessing if either is missing.
 *
 * Exit codes: 0 ok, 1 refused or failed.
 */

const ROOT = __DIR__ . '/..';

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, 'error: ' . $message . PHP_EOL);
    exit(1);
}

function run(string $command): string
{
    $output = [];
    $status = 0;
    exec($command . ' 2>&1', $output, $status);

    if ($status !== 0) {
        fail($command . ' failed: ' . implode("\n", $output));
    }

    return implode("\n", $output);
}

function readVersion(): string
{
    $path = ROOT . '/VERSION';
    if (!is_file($path)) {
        fail('VERSION file not found at ' . $path . '.');
    }

    $version = trim((string) file_get_contents($path));
    if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
        fail("VERSION does not contain a valid semver value: '{$version}'.");
    }

    return $version;
}

/**
 * A vendor/ built with require-dev still carries phpunit's binary. Shipping
 * that in a release archive would put a test runner and a CS fixer into a
 * production web root - refuse rather than let it slip through. Both
 * extensions are checked because Composer writes vendor/bin/phpunit.bat
 * alongside the extension-less shim on Windows.
 */
function assertProductionVendor(): void
{
    if (!is_file(ROOT . '/vendor/autoload.php')) {
        fail('vendor/autoload.php is missing. Run "composer install --no-dev --optimize-autoloader" first.');
    }

    if (is_file(ROOT . '/vendor/bin/phpunit') || is_file(ROOT . '/vendor/bin/phpunit.bat')) {
        fail('vendor/ contains development dependencies (phpunit is present). Run "composer install --no-dev --optimize-autoloader" and retry.');
    }
}

function assertBuiltAssets(): void
{
    if (!is_file(ROOT . '/public/assets/css/styles.css')) {
        fail('public/assets/css/styles.css is missing. Run "npm run build" first.');
    }
}

/**
 * A path relative to $root, using forward slashes regardless of platform.
 * RecursiveDirectoryIterator::getSubPathName() returns the OS separator, and
 * mixing that with the '/' used to join paths elsewhere in this script would
 * produce inconsistent zip entry names on Windows.
 */
function relativePath(RecursiveIteratorIterator $iterator): string
{
    return str_replace('\\', '/', $iterator->getSubPathName());
}

/**
 * Recursively copies a directory tree, overwriting anything already staged.
 * Used for vendor/ and public/assets/: vendor/ is never tracked by git, and
 * the compiled stylesheet must reflect the build that just ran, not whatever
 * (if anything) git archive happened to carry.
 */
function copyTree(string $from, string $to): void
{
    if (!is_dir($to) && !mkdir($to, 0777, true) && !is_dir($to)) {
        fail('Could not create directory: ' . $to);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $target = $to . '/' . relativePath($iterator);

        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                fail('Could not create directory: ' . $target);
            }

            continue;
        }

        if (!copy($item->getPathname(), $target)) {
            fail('Could not copy ' . $item->getPathname() . ' to ' . $target);
        }
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

/**
 * Every file under $root, relative to it, in a stable order. Feeds both zip
 * branches below so their entry ordering (and therefore, incidentally, the
 * archive's byte layout) does not depend on filesystem iteration order.
 *
 * @return list<string>
 */
function listFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            continue;
        }

        $files[] = relativePath($iterator);
    }

    sort($files);

    return $files;
}

/**
 * ext-zip is deliberately NOT in composer.json's `require` - this script is a
 * maintainer tool, not part of the application's runtime dependencies. Falls
 * back to the `zip` CLI when the extension is unavailable. $sourceRoot must
 * contain nothing but the single "aureo" directory being packaged, so both
 * branches produce the same "aureo/..." entry names.
 */
function zipDirectory(string $sourceRoot, string $zipPath): void
{
    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            fail('Could not create zip archive: ' . $zipPath);
        }

        foreach (listFiles($sourceRoot) as $relative) {
            if (!$zip->addFile($sourceRoot . '/' . $relative, $relative)) {
                fail('Could not add ' . $relative . ' to the archive.');
            }
        }

        $zip->close();

        return;
    }

    out('  ext-zip is not loaded; shelling out to the zip CLI.');
    run('cd ' . escapeshellarg($sourceRoot) . ' && zip -rq ' . escapeshellarg($zipPath) . ' aureo');
}

// --- main -----------------------------------------------------------------

$outputDir = ROOT . '/dist';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $value = substr($arg, 9);
        $outputDir = preg_match('#^([A-Za-z]:)?[\\\\/]#', $value) === 1 ? $value : ROOT . '/' . $value;

        continue;
    }

    fail("Unknown argument: {$arg}");
}

$version = readVersion();
out('Aureo release build');
out('  version: ' . $version);

assertProductionVendor();
assertBuiltAssets();

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fail('Could not create output directory: ' . $outputDir);
}

// Collapses ROOT's literal "bin/.." segment into a clean absolute path so
// every message printed below reads sensibly instead of echoing the ".."
// component back at the operator.
$outputDir = realpath($outputDir) ?: $outputDir;

$archiveName = 'aureo-' . $version;
$staging = rtrim(sys_get_temp_dir(), '/\\') . '/aureo-build-' . bin2hex(random_bytes(6));
$stagingApp = $staging . '/aureo';

if (!mkdir($stagingApp, 0777, true) && !is_dir($stagingApp)) {
    fail('Could not create staging directory: ' . $stagingApp);
}

out('Exporting tracked files (git archive, honouring .gitattributes export-ignore)...');
$tarPath = $staging . '/baseline.tar';
// --worktree-attributes: `git archive HEAD` otherwise resolves export-ignore
// only from the .gitattributes blob already committed at HEAD, not the one
// sitting in the working tree. Verified against this repository: on the
// commit that first added .gitattributes, omitting this flag let tests/,
// .github/ and phpunit.xml straight into the archive, because HEAD did not
// contain .gitattributes yet at the moment this script needed it to.
run(sprintf(
    'git -C %s archive --worktree-attributes --format=tar --prefix=aureo/ --output=%s HEAD',
    escapeshellarg(ROOT),
    escapeshellarg($tarPath)
));

$phar = new PharData($tarPath);
$phar->extractTo($staging, null, true);
// PharData keeps the tar's file handle open until the object is destroyed;
// on Windows that leaves the file locked, so unlink() silently fails (and
// listFiles() below would then pick up baseline.tar as if it belonged in
// the release) unless the reference is dropped first.
unset($phar);
if (!unlink($tarPath)) {
    fail('Could not remove the intermediate archive: ' . $tarPath);
}

out('Adding vendor/ (production dependencies only)...');
copyTree(ROOT . '/vendor', $stagingApp . '/vendor');

out('Adding compiled assets...');
copyTree(ROOT . '/public/assets', $stagingApp . '/public/assets');

// Neither directory is carried by git (both are gitignored runtime output),
// so a fresh extraction would otherwise be missing them until first write -
// which is exactly the state PreflightService's writable-directory checks
// are designed to catch, but there is no reason to make every install trip
// that check when an empty, correctly-permissioned directory is free to ship.
out('Creating empty runtime directories (log/, var/cache/)...');
foreach (['log', 'var/cache'] as $dir) {
    $path = $stagingApp . '/' . $dir;
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        fail('Could not create directory: ' . $path);
    }

    file_put_contents($path . '/.gitkeep', '');
}

$zipPath = $outputDir . '/' . $archiveName . '.zip';
out('Compressing to ' . $zipPath . ' ...');
zipDirectory($staging, $zipPath);

removeTree($staging);

$size = filesize($zipPath);
if ($size === false) {
    fail('Could not stat the built archive: ' . $zipPath);
}

$hash = hash_file('sha256', $zipPath);
if ($hash === false) {
    fail('Could not hash the built archive: ' . $zipPath);
}

$sha256Path = $zipPath . '.sha256';
// Two spaces then a bare "\n" - the exact format `sha256sum -c` expects.
// PHP_EOL would write "\r\n" when this script runs on Windows, and
// sha256sum only strips the trailing "\n", leaving a stray "\r" glued onto
// the filename it looks for - it then reports the file "could not be read"
// because "aureo-<version>.zip\r" does not exist. Verified on this machine.
file_put_contents($sha256Path, $hash . '  ' . basename($zipPath) . "\n");

out('');
out('Built: ' . $zipPath);
out('  size:     ' . number_format($size) . ' bytes (' . number_format($size / (1024 * 1024), 2) . ' MiB)');
out('  sha256:   ' . $hash);
out('  checksum: ' . $sha256Path);

exit(0);
