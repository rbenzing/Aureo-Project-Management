<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Concerns\Searchable;
use App\Models\SearchIndex;
use App\Models\Setting;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

/**
 * Integration tests for BaseModel::create() against a real database.
 *
 * Every model/controller unit test mocks Database, so no INSERT is ever issued
 * against a server running STRICT_TRANS_TABLES (the MySQL and MariaDB default).
 * That hid a defect that made the entire write surface of the application
 * non-functional: eight tables declare `guid CHAR(36) NOT NULL` with no
 * default, while BaseModel lists `guid` in $guarded — so it was stripped from
 * every insert and generated nowhere, and every create() failed with
 * "SQLSTATE[HY000] 1364 Field 'guid' doesn't have a default value".
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(BaseModel::class)]
#[UsesClass(Company::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(Searchable::class)]
#[UsesClass(SearchIndex::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class RecordCreationTest extends TestCase
{
    /** @var list<int> */
    private array $createdCompanyIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdCompanyIds as $id) {
            $this->db?->executeQuery('DELETE FROM companies WHERE id = :id', [':id' => $id]);
        }
        $this->createdCompanyIds = [];

        parent::tearDown();
    }

    public function testCreatingARecordPersistsARow(): void
    {
        $email = 'create-' . uniqid() . '@example.test';

        $id = (new Company())->create(['name' => 'Record Creation Co', 'email' => $email]);

        $this->assertIsNumeric($id, 'create() should return the new row id.');
        $this->createdCompanyIds[] = (int) $id;

        $row = $this->db->executeQuery(
            'SELECT name, email FROM companies WHERE id = :id',
            [':id' => (int) $id]
        )->fetch(\PDO::FETCH_OBJ);

        $this->assertNotFalse($row, 'The created row should be readable back.');
        $this->assertSame('Record Creation Co', $row->name);
        $this->assertSame($email, $row->email);
    }

    /**
     * The column is NOT NULL with no default, so a row can only exist if the
     * application generated the value. Asserting it is a real UUID (rather than
     * merely non-empty) is what stops a future "fill it with an empty string"
     * regression from looking like a fix.
     */
    public function testCreatedRecordReceivesAGeneratedGuid(): void
    {
        $id = (new Company())->create([
            'name' => 'Guid Co',
            'email' => 'guid-' . uniqid() . '@example.test',
        ]);
        $this->createdCompanyIds[] = (int) $id;

        $guid = $this->db->executeQuery(
            'SELECT guid FROM companies WHERE id = :id',
            [':id' => (int) $id]
        )->fetch(\PDO::FETCH_OBJ)->guid;

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $guid,
            'Every guid-bearing table needs an application-generated UUID on insert.'
        );
    }

    public function testEachCreatedRecordReceivesADistinctGuid(): void
    {
        $model = new Company();

        $guids = [];
        foreach (['a', 'b'] as $suffix) {
            $id = (int) $model->create([
                'name' => 'Distinct Co ' . $suffix,
                'email' => 'distinct-' . $suffix . '-' . uniqid() . '@example.test',
            ]);
            $this->createdCompanyIds[] = $id;

            $guids[] = $this->db->executeQuery(
                'SELECT guid FROM companies WHERE id = :id',
                [':id' => $id]
            )->fetch(\PDO::FETCH_OBJ)->guid;
        }

        $this->assertNotSame($guids[0], $guids[1], 'Guids must be unique per row.');
    }

    /**
     * guid stays in $guarded: a caller must not be able to choose it through
     * mass assignment, or the uniqueness the column indexes is theirs to break.
     */
    public function testCallerSuppliedGuidIsIgnored(): void
    {
        $id = (int) (new Company())->create([
            'name' => 'Guarded Co',
            'email' => 'guarded-' . uniqid() . '@example.test',
            'guid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);
        $this->createdCompanyIds[] = $id;

        $guid = $this->db->executeQuery(
            'SELECT guid FROM companies WHERE id = :id',
            [':id' => $id]
        )->fetch(\PDO::FETCH_OBJ)->guid;

        $this->assertNotSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $guid);
    }
}
