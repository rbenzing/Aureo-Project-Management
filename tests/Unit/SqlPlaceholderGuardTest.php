<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Repo-wide guard: no SQL string literal may name the same placeholder twice.
 *
 * `Database` sets PDO::ATTR_EMULATE_PREPARES=false, and a native prepare allows
 * exactly one binding per placeholder — reusing a name throws
 * "SQLSTATE[HY093]: Invalid parameter number" before the statement runs. The
 * codebase hit this four separate times:
 *
 *   SearchIndex::fullTextSearch()          /api/search returned 500 for every
 *                                          query of three or more characters
 *   Sprint::getSprintTasksWithSubtasks()   caught, logged, returned [] — a
 *                                          sprint silently showed no tasks
 *   ActivityController::getTotalActivities()  caught, returned 0 — the activity
 *                                          log's search filter reported no results
 *   Role::assignPermission()               threw on every call (no callers yet)
 *
 * None of it was catchable by the existing tests: every unit test of these
 * classes mocks Database, and a mock accepts any SQL at all. That is what makes
 * a static guard the right shape here — it needs no database and it fails at
 * the moment the statement is written.
 *
 * Deliberate limits, so nobody reads more assurance into this than it offers:
 *
 *  - It sees one string literal at a time. A statement assembled from two
 *    appended fragments that each name `:x` once is invisible to it.
 *  - It does not parse SQL. A literal is treated as SQL when it starts with a
 *    statement verb or a clause keyword, which is how this codebase writes both
 *    whole statements and the fragments it appends.
 *
 * `AssetUrlTest` is the precedent for a repo-wide guard with no CoversClass.
 */
final class SqlPlaceholderGuardTest extends TestCase
{
    /**
     * A literal counts as SQL when it opens with a statement verb (a whole
     * statement) or a clause keyword (a fragment appended to one).
     */
    private const SQL_OPENING = '/^(SELECT|INSERT|UPDATE|DELETE|REPLACE|AND|OR|WHERE|ORDER BY|GROUP BY|HAVING|LIMIT|JOIN|LEFT JOIN|INNER JOIN)\s/i';

    private const PLACEHOLDER = '/:[a-zA-Z_][a-zA-Z0-9_]*/';

    public function testNoSqlLiteralNamesTheSamePlaceholderTwice(): void
    {
        $offenders = [];

        foreach ($this->sqlLiterals() as [$path, $line, $text]) {
            preg_match_all(self::PLACEHOLDER, $text, $matches);

            foreach (array_count_values($matches[0]) as $name => $occurrences) {
                if ($occurrences > 1) {
                    $offenders[] = sprintf('%s:%d — %s appears %d times', $path, $line, $name, $occurrences);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A named placeholder must be bound once per statement. Give each occurrence its own "
            . "name (:foo_a, :foo_b) and bind the same value to both:\n  " . implode("\n  ", $offenders)
        );
    }

    /**
     * Guards the guard: without this, a detector that silently matched nothing
     * would pass for ever and the test above would assert only that an empty
     * list is empty.
     */
    public function testTheDetectorActuallyFindsSqlLiterals(): void
    {
        $this->assertGreaterThan(
            100,
            count($this->sqlLiterals()),
            'The SQL literal detector found almost nothing — it has stopped matching.'
        );
    }

    /**
     * Proves the rule catches the real defect shape, using the statement that
     * broke /api/search. A guard nobody has seen reject anything is a guard
     * nobody should trust.
     */
    public function testTheRuleRejectsAStatementThatRepeatsAPlaceholder(): void
    {
        $sql = 'SELECT *, MATCH(search_blob) AGAINST(:query IN NATURAL LANGUAGE MODE) AS score '
            . 'FROM searchable_index WHERE MATCH(search_blob) AGAINST(:query IN NATURAL LANGUAGE MODE) > 0';

        $this->assertSame(1, preg_match(self::SQL_OPENING, $sql), 'The sample must be recognised as SQL.');

        preg_match_all(self::PLACEHOLDER, $sql, $matches);

        $this->assertSame(2, array_count_values($matches[0])[':query']);
    }

    /**
     * @return list<array{0:string,1:int,2:string}>
     */
    private function sqlLiterals(): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $literals = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = 'src/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            // The tokenizer is what makes this exact: one literal is one
            // statement. Matching with a regex over raw file text spans
            // neighbouring statements and reports only false positives.
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token)) {
                    continue;
                }

                // Plain strings and heredoc bodies both arrive as literal text.
                if ($token[0] !== T_CONSTANT_ENCAPSED_STRING && $token[0] !== T_ENCAPSED_AND_WHITESPACE) {
                    continue;
                }

                $body = trim($token[1], "\"' \t\n\r");

                if (preg_match(self::SQL_OPENING, $body) === 1) {
                    $literals[] = [$path, $token[2], $token[1]];
                }
            }
        }

        return $literals;
    }
}
