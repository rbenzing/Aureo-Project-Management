<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Role;
use App\Models\Setting;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\TestCase;

/**
 * Integration tests for Role's hand-written permission SQL.
 *
 * RoleTest mocks Database and asserts on the statement *text*, which cannot
 * tell a valid statement from an invalid one — it pinned
 * "ON DUPLICATE KEY UPDATE role_id = :role_id" for as long as that clause was
 * unusable. Naming :role_id in both the VALUES list and the UPDATE clause gives
 * one placeholder two bindings, which a native prepare rejects outright with
 * "SQLSTATE[HY093]: Invalid parameter number", so assignPermission() could only
 * ever throw. The same defect made global search return 500 (see
 * SearchQueryTest).
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(Role::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[Group('integration')]
final class RolePermissionSqlTest extends TestCase
{
    private int $roleId = 0;
    private int $permissionId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->db->executeInsertUpdate(
            'INSERT INTO roles (guid, name, description) VALUES (UUID(), :name, :description)',
            [':name' => 'sql-probe-' . uniqid(), ':description' => 'integration test fixture']
        );
        $this->roleId = (int) $this->db->getConnection()->lastInsertId();

        $this->permissionId = (int) $this->db
            ->executeQuery('SELECT id FROM permissions ORDER BY id LIMIT 1')
            ->fetch(\PDO::FETCH_OBJ)->id;
    }

    protected function tearDown(): void
    {
        if ($this->roleId !== 0) {
            $this->db?->executeQuery('DELETE FROM role_permissions WHERE role_id = :id', [':id' => $this->roleId]);
            $this->db?->executeQuery('DELETE FROM roles WHERE id = :id', [':id' => $this->roleId]);
        }

        parent::tearDown();
    }

    public function testAssignPermissionPersistsTheGrant(): void
    {
        (new Role())->assignPermission($this->roleId, $this->permissionId);

        $count = (int) $this->db->executeQuery(
            'SELECT COUNT(*) AS c FROM role_permissions WHERE role_id = :role AND permission_id = :perm',
            [':role' => $this->roleId, ':perm' => $this->permissionId]
        )->fetch(\PDO::FETCH_OBJ)->c;

        $this->assertSame(1, $count, 'Assigning a permission must actually grant it.');
    }

    /**
     * The upsert clause only runs on the second call, so a test that assigns
     * once never reaches the branch that was broken.
     */
    public function testAssigningTheSamePermissionTwiceIsIdempotent(): void
    {
        $role = new Role();
        $role->assignPermission($this->roleId, $this->permissionId);
        $role->assignPermission($this->roleId, $this->permissionId);

        $count = (int) $this->db->executeQuery(
            'SELECT COUNT(*) AS c FROM role_permissions WHERE role_id = :role AND permission_id = :perm',
            [':role' => $this->roleId, ':perm' => $this->permissionId]
        )->fetch(\PDO::FETCH_OBJ)->c;

        $this->assertSame(1, $count, 'A repeated grant must not duplicate the row.');
    }
}
