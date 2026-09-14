<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

/**
 * Integration tests for the search statements against a real database.
 *
 * SearchIndex writes its SQL by hand, and every unit test that touches it
 * mocks Database — so the statement text itself was never handed to a real
 * driver. That hid a defect which made global search return HTTP 500 for every
 * query of three characters or more: fullTextSearch() named the same
 * placeholder ":query" in both the SELECT list and the WHERE clause, and with
 * PDO::ATTR_EMULATE_PREPARES=false a native prepare requires one placeholder
 * per binding, so the driver rejected it with "SQLSTATE[HY093]: Invalid
 * parameter number" before the query ever ran.
 *
 * An assertion on results is not enough on its own here — the point is that the
 * statement survives prepare/execute at all. Requires a migrated test database;
 * skipped cleanly when none is reachable.
 */
#[CoversClass(SearchIndex::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class SearchQueryTest extends TestCase
{
    /**
     * Deliberately not a real word: FULLTEXT ignores tokens shorter than
     * innodb_ft_min_token_size (3) and anything in the stopword list, so a
     * nonsense token keeps the test about the statement rather than about
     * MySQL's dictionary.
     */
    private const TOKEN = 'zzyzxqualopt';

    private int $seededId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->db->executeQuery(
            "INSERT INTO searchable_index
                (entity_type, entity_id, title, snippet, project_id, search_blob, updated_at, is_deleted)
             VALUES
                ('task', :entity_id, :title, 'snippet', NULL, :blob, NOW(), 0)",
            [
                ':entity_id' => 987654,
                ':title' => self::TOKEN . ' title',
                ':blob' => self::TOKEN . ' searchable body text',
            ]
        );

        $this->seededId = 987654;
    }

    protected function tearDown(): void
    {
        if ($this->seededId !== 0) {
            $this->db?->executeQuery(
                'DELETE FROM searchable_index WHERE entity_type = :type AND entity_id = :id',
                [':type' => 'task', ':id' => $this->seededId]
            );
        }

        parent::tearDown();
    }

    public function testFullTextSearchRunsAndFindsASeededRow(): void
    {
        $results = (new SearchIndex())->fullTextSearch(self::TOKEN);

        $this->assertNotSame([], $results, 'A query of three or more characters must reach the index.');
        $this->assertSame(987654, (int) $results[0]->entity_id);
    }

    /**
     * The entity-type filter appends further placeholders to the same
     * statement, so it is the case most likely to break again if the
     * placeholder names are ever collapsed back together.
     */
    public function testFullTextSearchRunsWithAnEntityTypeFilter(): void
    {
        $results = (new SearchIndex())->fullTextSearch(self::TOKEN, ['task']);

        $this->assertNotSame([], $results);
        $this->assertSame('task', $results[0]->entity_type);
    }

    public function testFullTextSearchExcludesRowsOfAnotherEntityType(): void
    {
        $results = (new SearchIndex())->fullTextSearch(self::TOKEN, ['project']);

        $this->assertSame([], $results);
    }

    /**
     * Queries shorter than the FULLTEXT minimum take prefixSearch instead. It
     * was always sound, which is exactly why it needs a guard: it is the only
     * reason global search was not broken for every possible input.
     */
    public function testShortQueriesTakeThePrefixPathAndStillRun(): void
    {
        $results = (new SearchIndex())->prefixSearch(substr(self::TOKEN, 0, 2));

        $this->assertNotSame([], $results);
        $this->assertSame(987654, (int) $results[0]->entity_id);
    }
}
