#!/usr/bin/env php
<?php

declare(strict_types=1);

const ROOT = __DIR__ . '/..';

require_once ROOT . '/vendor/autoload.php';

define('BASE_PATH', ROOT . '/public');

\App\Core\Config::init();

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

/**
 * Fetch all non-deleted rows for an entity type, upsert each into searchable_index.
 *
 * @param callable(\stdClass): array{string, string, int|null, string} $mapper
 *     Returns [title, snippet, projectId, searchBlob].
 */
function reindexType(
    \App\Core\Database $db,
    \App\Models\SearchIndex $index,
    string $entityType,
    string $sql,
    callable $mapper
): int {
    out("Indexing {$entityType}s...");
    $stmt = $db->executeQuery($sql, []);
    $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
    $count = 0;

    foreach ($rows as $row) {
        [$title, $snippet, $projectId, $searchBlob] = $mapper($row);
        $index->upsert($entityType, (int) $row->id, $title, $snippet, $projectId, $searchBlob);
        $count++;
    }

    out("  -> {$count} {$entityType}(s) indexed.");

    return $count;
}

try {
    $db = \App\Core\Database::getInstance();
    $index = new \App\Models\SearchIndex();

    out('=== Aureo Search Index — Backfill ===');
    out('');

    $totals = [];

    $totals['task'] = reindexType(
        $db,
        $index,
        'task',
        "SELECT id, title, description, project_id FROM tasks WHERE is_deleted = 0",
        function (\stdClass $row): array {
            $title = $row->title ?? '';
            $desc = $row->description ?? '';

            return [$title, substr($desc, 0, 200), (int) $row->project_id, $title . ' ' . $desc];
        }
    );

    $totals['project'] = reindexType(
        $db,
        $index,
        'project',
        "SELECT id, name, description FROM projects WHERE is_deleted = 0",
        function (\stdClass $row): array {
            $title = $row->name ?? '';
            $desc = $row->description ?? '';

            return [$title, substr($desc, 0, 200), (int) $row->id, $title . ' ' . $desc];
        }
    );

    $totals['user'] = reindexType(
        $db,
        $index,
        'user',
        "SELECT id, first_name, last_name, email FROM users WHERE is_deleted = 0",
        function (\stdClass $row): array {
            $title = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            $email = $row->email ?? '';

            return [$title, $email, null, $title . ' ' . $email];
        }
    );

    $totals['sprint'] = reindexType(
        $db,
        $index,
        'sprint',
        "SELECT id, name, sprint_goal, project_id FROM sprints WHERE is_deleted = 0",
        function (\stdClass $row): array {
            $title = $row->name ?? '';
            $goal = $row->sprint_goal ?? '';

            return [$title, substr($goal, 0, 200), (int) $row->project_id, $title . ' ' . $goal];
        }
    );

    $totals['milestone'] = reindexType(
        $db,
        $index,
        'milestone',
        "SELECT id, title, description, project_id FROM milestones WHERE is_deleted = 0",
        function (\stdClass $row): array {
            $title = $row->title ?? '';
            $desc = $row->description ?? '';

            return [$title, substr($desc, 0, 200), (int) $row->project_id, $title . ' ' . $desc];
        }
    );

    $totals['company'] = reindexType(
        $db,
        $index,
        'company',
        "SELECT id, name, email, address FROM companies WHERE is_deleted = 0",
        function (\stdClass $row): array {
            $name = $row->name ?? '';
            $email = $row->email ?? '';
            $address = $row->address ?? '';

            return [$name, substr((string) $email, 0, 200), null, trim($name . ' ' . $email . ' ' . $address)];
        }
    );

    $grand = array_sum($totals);

    out('');
    out("Done! Search index rebuilt. Total rows upserted: {$grand}");
} catch (\Throwable $e) {
    fwrite(STDERR, 'Reindex failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
