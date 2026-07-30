<?php

declare(strict_types=1);

/**
 * Read or bump the project version.
 *
 *   composer version                 -> print the current version
 *   composer version:patch           -> 1.0.1 -> 1.0.2
 *   composer version:minor           -> 1.0.1 -> 1.1.0
 *   composer version:major           -> 1.0.1 -> 2.0.0
 *   php bin/version.php 2.3.4        -> set an explicit version
 *
 * The VERSION file is the single source of truth; package.json is kept in step
 * so npm and the PHP side never disagree. composer.json deliberately carries no
 * version field — Composer infers it from the git tag, and a stale hardcoded
 * value there is worse than none.
 *
 * This script only edits files. It does not commit or tag: doing that silently
 * would sweep up whatever else happens to be staged. It prints the exact
 * commands instead.
 *
 * Exit codes: 0 ok, 1 usage error, 2 IO error.
 */

const ROOT = __DIR__ . '/..';
const VERSION_FILE = ROOT . '/VERSION';
const PACKAGE_JSON = ROOT . '/package.json';

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function fail(string $msg, int $code = 1): never
{
    fwrite(STDERR, $msg . PHP_EOL);

    exit($code);
}

function readVersion(): string
{
    if (!is_file(VERSION_FILE)) {
        fail('VERSION file not found at ' . VERSION_FILE, 2);
    }

    $raw = trim((string) file_get_contents(VERSION_FILE));
    if (!preg_match('/^\d+\.\d+\.\d+$/', $raw)) {
        fail("VERSION does not contain a valid semver value: '{$raw}'", 2);
    }

    return $raw;
}

/**
 * @return array{0:int,1:int,2:int}
 */
function parse(string $version): array
{
    [$major, $minor, $patch] = array_map('intval', explode('.', $version));

    return [$major, $minor, $patch];
}

function bump(string $current, string $part): string
{
    [$major, $minor, $patch] = parse($current);

    return match ($part) {
        'major' => sprintf('%d.0.0', $major + 1),
        'minor' => sprintf('%d.%d.0', $major, $minor + 1),
        'patch' => sprintf('%d.%d.%d', $major, $minor, $patch + 1),
        default => fail("Unknown bump type '{$part}'. Use major, minor, patch, or an explicit X.Y.Z."),
    };
}

/**
 * Rewrite only the version value in package.json.
 *
 * Deliberately a targeted replacement rather than json_decode/json_encode, which
 * would reflow the whole file and produce a diff nobody asked for.
 */
function updatePackageJson(string $next): void
{
    if (!is_file(PACKAGE_JSON)) {
        out('  note: package.json not found, skipping');

        return;
    }

    $json = (string) file_get_contents(PACKAGE_JSON);
    $updated = preg_replace(
        '/("version"\s*:\s*")\d+\.\d+\.\d+(")/',
        '${1}' . $next . '${2}',
        $json,
        1,
        $count
    );

    if ($updated === null || $count !== 1) {
        fail('Could not locate a single "version" field in package.json; left untouched.', 2);
    }

    file_put_contents(PACKAGE_JSON, $updated);
}

$arg = $argv[1] ?? null;
$current = readVersion();

if ($arg === null) {
    out($current);

    exit(0);
}

$next = preg_match('/^\d+\.\d+\.\d+$/', $arg) === 1 ? $arg : bump($current, $arg);

if ($next === $current) {
    fail("Version is already {$current}; nothing to do.");
}

file_put_contents(VERSION_FILE, $next . PHP_EOL);
updatePackageJson($next);

out('');
out("  version {$current} -> {$next}");
out('');
out('  Updated: VERSION, package.json');
out('');
out('  To release:');
out('    git add VERSION package.json CHANGELOG.md');
out("    git commit -m \"chore(release): {$next}\"");
out("    git tag -a {$next} -m \"Release {$next}\"");
out('    git push --follow-tags');
out('');

exit(0);
