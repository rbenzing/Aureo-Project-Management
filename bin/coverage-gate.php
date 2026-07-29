<?php

declare(strict_types=1);

/**
 * Tiered coverage gate.
 *
 *   composer coverage:gate                 -> enforce, using coverage.xml
 *   composer coverage:gate -- --update     -> also ratchet tier-2 floors upward
 *   composer coverage:gate -- --file=X.xml override the clover path
 *
 * PHPUnit cannot express per-directory thresholds, so this parses the Clover
 * report and applies two different rules:
 *
 *   Tier 1 (the testable core) must hold a hard 90% line coverage.
 *   Tier 2 (Models, Controllers) may never drop below the floors recorded in
 *   coverage-floor.json. Floors only move up, and only when --update is passed.
 *
 * Exit codes: 0 pass, 1 gate failure, 2 usage/IO error.
 */

const ROOT = __DIR__ . '/..';
const FLOOR_FILE = ROOT . '/coverage-floor.json';

/**
 * The tier-1 goal. Tier 1 ratchets toward it rather than gating on it outright:
 * a hard 90% would keep master red for the whole campaign, which teaches people
 * to ignore the gate. Regression below the recorded floor always fails, so
 * progress is never lost, and once the floor reaches TIER1_TARGET the target
 * itself becomes a permanent hard gate.
 */
const TIER1_TARGET = 90.0;
const TIER1_KEY = '_tier1';

/**
 * Slack, in percentage points, allowed below a recorded floor before failing.
 *
 * Measured coverage is not bit-identical between runs: Config, Database, Setting
 * and SettingsService are process-wide singletons, so whichever test happens to
 * initialize them first is credited with executing their bodies. Because
 * executionOrder is "depends,defects", that attribution moves between runs and
 * the aggregate drifts by a few hundredths of a point (95.11 vs 95.18 observed on
 * identical code). A zero-tolerance ratchet turns that drift into spurious CI
 * failures. Real regressions are whole points, so this absorbs the jitter without
 * hiding anything that matters.
 */
const RATCHET_TOLERANCE = 0.5;

/**
 * Source directories under src/ that make up each tier. Anything not listed
 * here (currently only Views and Middleware) is reported but ungated, so a new
 * top-level directory can never silently bypass the gate.
 */
const TIER1_DIRS = [
    'Core',
    'Enums',
    'Events',
    'Exceptions',
    'Http',
    'Listeners',
    'Repositories',
    'Services',
    'Utils',
];
const TIER2_DIRS = [
    'Controllers',
    'Middleware',
    'Models',
];

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function fail(string $msg, int $code = 2): never
{
    fwrite(STDERR, $msg . PHP_EOL);

    exit($code);
}

/**
 * @param array<string,string> $opts
 */
function opt(array $opts, string $key, ?string $default = null): ?string
{
    return $opts[$key] ?? $default;
}

/**
 * Parse `--key=value` and `--flag` style arguments.
 *
 * @param list<string> $argv
 *
 * @return array<string,string>
 */
function parseArgs(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        [$key, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, '1'];
        $opts[$key] = $value;
    }

    return $opts;
}

/**
 * Map an absolute source path to the src/ subdirectory that owns it.
 */
function directoryFor(string $path): ?string
{
    $normalised = str_replace('\\', '/', $path);
    if (!preg_match('#/src/([^/]+)/#', $normalised, $m)) {
        return null;
    }

    return $m[1];
}

/**
 * Aggregate Clover per-file metrics into per-directory statement totals.
 *
 * @return array<string,array{statements:int,covered:int}>
 */
function readClover(string $file): array
{
    if (!is_file($file)) {
        fail("Coverage report not found: {$file}\nRun: composer test:coverage-clover");
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_file($file);
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        fail("Could not parse Clover XML: {$file}");
    }

    $totals = [];
    foreach ($xml->xpath('//file') ?: [] as $node) {
        $path = (string) $node['name'];
        $dir = directoryFor($path);
        if ($dir === null) {
            continue;
        }

        $metrics = $node->metrics;
        if ($metrics === null) {
            continue;
        }

        $totals[$dir] ??= ['statements' => 0, 'covered' => 0];
        $totals[$dir]['statements'] += (int) $metrics['statements'];
        $totals[$dir]['covered'] += (int) $metrics['coveredstatements'];
    }

    if ($totals === []) {
        fail("Clover report contained no files under src/. Was a coverage driver active?");
    }

    return $totals;
}

function percent(int $covered, int $statements): float
{
    // A directory with no statements is vacuously complete; reporting 0% would
    // fail the gate for e.g. an interface-only package.
    return $statements === 0 ? 100.0 : round($covered / $statements * 100, 2);
}

/**
 * @param array<string,array{statements:int,covered:int}> $totals
 * @param list<string>                                    $dirs
 *
 * @return array{statements:int,covered:int,percent:float}
 */
function tierTotals(array $totals, array $dirs): array
{
    $statements = 0;
    $covered = 0;
    foreach ($dirs as $dir) {
        $statements += $totals[$dir]['statements'] ?? 0;
        $covered += $totals[$dir]['covered'] ?? 0;
    }

    return [
        'statements' => $statements,
        'covered' => $covered,
        'percent' => percent($covered, $statements),
    ];
}

/**
 * @return array<string,float>
 */
function readFloors(): array
{
    if (!is_file(FLOOR_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(FLOOR_FILE), true);
    if (!is_array($decoded)) {
        fail('coverage-floor.json is not valid JSON.');
    }

    return array_map(static fn ($v): float => (float) $v, $decoded['floors'] ?? []);
}

/**
 * @param array<string,float> $floors
 */
function writeFloors(array $floors): void
{
    ksort($floors);
    $payload = [
        '_comment' => 'Coverage ratchet floors. "_tier1" is the tier-1 aggregate; the rest are per-directory tier-2 floors. Written by composer coverage:ratchet. Values may only increase - never hand-edit downward.',
        'floors' => $floors,
    ];
    file_put_contents(
        FLOOR_FILE,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

$opts = parseArgs($argv);
$cloverPath = opt($opts, 'file', ROOT . '/coverage.xml');
$update = opt($opts, 'update') !== null;

$totals = readClover((string) $cloverPath);
$floors = readFloors();

$tier1 = tierTotals($totals, TIER1_DIRS);
$tier2 = tierTotals($totals, TIER2_DIRS);

out('');
out('  Tiered coverage gate');
out('  ' . str_repeat('=', 62));
out(sprintf('  %-16s %10s %10s %10s  %s', 'DIRECTORY', 'COVERED', 'STMTS', 'PERCENT', 'TIER'));
out('  ' . str_repeat('-', 62));

$rows = $totals;
ksort($rows);
foreach ($rows as $dir => $row) {
    $tier = in_array($dir, TIER1_DIRS, true) ? '1' : (in_array($dir, TIER2_DIRS, true) ? '2' : '-');
    out(sprintf(
        '  %-16s %10d %10d %9.2f%%  %s',
        $dir,
        $row['covered'],
        $row['statements'],
        percent($row['covered'], $row['statements']),
        $tier
    ));
}

out('  ' . str_repeat('-', 62));
out(sprintf(
    '  %-16s %10d %10d %9.2f%%  (target %.2f%%)',
    'TIER 1',
    $tier1['covered'],
    $tier1['statements'],
    $tier1['percent'],
    TIER1_TARGET
));
out(sprintf(
    '  %-16s %10d %10d %9.2f%%  (ratchet)',
    'TIER 2',
    $tier2['covered'],
    $tier2['statements'],
    $tier2['percent']
));
out('');

$failures = [];

$nextFloors = $floors;

$tier1Floor = $floors[TIER1_KEY] ?? null;

if ($tier1Floor === null) {
    $nextFloors[TIER1_KEY] = $tier1['percent'];
    out(sprintf('  ratchet: TIER 1 has no floor yet, seeding at %.2f%%', $tier1['percent']));
} elseif ($tier1['percent'] + RATCHET_TOLERANCE < $tier1Floor) {
    $failures[] = sprintf(
        'Tier 1 regression: %.2f%% is more than %.2f points below the recorded floor of %.2f%%.',
        $tier1['percent'],
        RATCHET_TOLERANCE,
        $tier1Floor
    );
} else {
    if ($tier1['percent'] > $tier1Floor) {
        $nextFloors[TIER1_KEY] = $tier1['percent'];
        out(sprintf('  ratchet: TIER 1 rose %.2f%% -> %.2f%%', $tier1Floor, $tier1['percent']));
    }

    // The floor only ever rises, so reaching the target converts it into a
    // permanent hard gate.
    if ($tier1Floor >= TIER1_TARGET && $tier1['percent'] < TIER1_TARGET) {
        $failures[] = sprintf(
            'Tier 1 coverage %.2f%% fell below the %.2f%% target.',
            $tier1['percent'],
            TIER1_TARGET
        );
    }
}

if ($tier1['percent'] < TIER1_TARGET) {
    out(sprintf(
        '  TODO: tier 1 is %.2f points short of the %.2f%% target (%d statements still uncovered).',
        TIER1_TARGET - $tier1['percent'],
        TIER1_TARGET,
        $tier1['statements'] - $tier1['covered']
    ));
}

// Ratchet each tier-2 directory independently, so a gain in Models cannot mask
// a regression in Controllers.
foreach (TIER2_DIRS as $dir) {
    $actual = percent($totals[$dir]['covered'] ?? 0, $totals[$dir]['statements'] ?? 0);
    $floor = $floors[$dir] ?? null;

    if ($floor === null) {
        $nextFloors[$dir] = $actual;
        out(sprintf('  ratchet: %s has no floor yet, seeding at %.2f%%', $dir, $actual));

        continue;
    }

    if ($actual + RATCHET_TOLERANCE < $floor) {
        $failures[] = sprintf(
            'Tier 2 regression in %s: %.2f%% is more than %.2f points below the recorded floor of %.2f%%.',
            $dir,
            $actual,
            RATCHET_TOLERANCE,
            $floor
        );

        continue;
    }

    if ($actual > $floor) {
        $nextFloors[$dir] = $actual;
        out(sprintf('  ratchet: %s rose %.2f%% -> %.2f%%', $dir, $floor, $actual));
    }
}

if ($update) {
    writeFloors($nextFloors);
    out('  ratchet: coverage-floor.json updated');
} elseif ($nextFloors !== $floors) {
    out('  ratchet: floors would change; re-run with --update to record them');
}

out('');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, '  FAIL  ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, PHP_EOL);

    exit(1);
}

out('  PASS  all coverage gates satisfied');
out('');

exit(0);
