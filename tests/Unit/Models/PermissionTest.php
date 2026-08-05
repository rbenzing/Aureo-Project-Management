<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Permission;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Permission model: role/permission association
 * queries and mutations, the grouping/organizing projections used by the
 * permissions UI, name-based lookups, idempotent creation, bulk creation,
 * and the static role-template catalogue.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * Permission does not use the Searchable trait, so create()/bulkCreate()
 * never reach SearchIndex/SecurityService.
 */
// Config is declared because Database consults Config::isProduction(). It is a
// process-wide singleton, so only the first test in a run to reach it executes
// its body — which test that is moves with execution order (see MilestoneTest).
#[CoversClass(Permission::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
final class PermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);

        parent::tearDown();
    }

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function statement(array $methodReturns): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);

        foreach ($methodReturns as $method => $value) {
            $stmt->method($method)->willReturn($value);
        }

        return $stmt;
    }

    /**
     * Database mock whose executeQuery() replays $steps in order (each either
     * a PDOStatement to return or a Throwable to throw), recording every
     * call's SQL/params into $calls, repeating the last step for calls beyond
     * the list.
     */
    private function dbSequenced(array $steps, ?array &$calls = null): Database
    {
        $calls = [];
        $db = $this->createMock(Database::class);
        $call = 0;
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$call, $steps, &$calls) {
                $calls[] = ['sql' => $sql, 'params' => $params];
                $step = $steps[$call] ?? end($steps);
                $call++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        return $db;
    }

    /**
     * Database mock whose executeInsertUpdate() replays $steps in order (each
     * either a bool to return or a Throwable to throw), recording every call's
     * SQL/params into $calls, repeating the last step for calls beyond the list.
     */
    private function dbSequencedInsertUpdate(array $steps, ?array &$calls = null): Database
    {
        $calls = [];
        $db = $this->createMock(Database::class);
        $call = 0;
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$call, $steps, &$calls) {
                $calls[] = ['sql' => $sql, 'params' => $params];
                $step = $steps[$call] ?? end($steps);
                $call++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        return $db;
    }

    private function permissionRow(int $id, string $name, ?string $description = null): object
    {
        return (object)[
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    // ---------------------------------------------------------- getByRoleId()

    public function testGetByRoleIdReturnsRows(): void
    {
        $rows = [$this->permissionRow(1, 'view_tasks')];
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])], $calls);
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->getByRoleId(3);

        $this->assertSame($rows, $result);
        $this->assertStringContainsString('WHERE rp.role_id = :role_id', $calls[0]['sql']);
        $this->assertSame(3, $calls[0]['params'][':role_id']);
    }

    public function testGetByRoleIdWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get permissions for role:');

        $model->getByRoleId(3);
    }

    // --------------------------------------------------------- assignToRole()

    public function testAssignToRoleDeletesThenInsertsEachPermissionAndCommits(): void
    {
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true, true, true], $calls);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('commit');
        $db->expects($this->never())->method('rollBack');
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->assignToRole(5, [10, 11]);

        $this->assertTrue($result);
        $this->assertCount(3, $calls);
        $this->assertStringContainsString('DELETE FROM role_permissions WHERE role_id = :role_id', $calls[0]['sql']);
        $this->assertSame(5, $calls[0]['params'][':role_id']);
        $this->assertStringContainsString('INSERT INTO role_permissions', $calls[1]['sql']);
        $this->assertSame(10, $calls[1]['params'][':permission_id']);
        $this->assertSame(11, $calls[2]['params'][':permission_id']);
    }

    public function testAssignToRoleWithEmptyArraySkipsInsertsAfterDelete(): void
    {
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true], $calls);
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->assignToRole(5, []);

        $this->assertTrue($result);
        $this->assertCount(1, $calls); // only the DELETE ran
    }

    public function testAssignToRoleRollsBackAndThrowsOnFailure(): void
    {
        $db = $this->dbSequencedInsertUpdate([new RuntimeException('insert failed')]);
        $db->method('beginTransaction');
        $db->expects($this->once())->method('rollBack');
        $db->expects($this->never())->method('commit');
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to assign permissions:');

        $model->assignToRole(5, [10]);
    }

    // ---------------------------------------------------- getGroupedPermissions()

    public function testGetGroupedPermissionsGroupsByFirstUnderscoreSegmentAndSortsKeys(): void
    {
        $rows = [
            $this->permissionRow(1, 'view_tasks'),
            $this->permissionRow(2, 'create_tasks'),
            $this->permissionRow(3, 'view_projects'),
            $this->permissionRow(4, 'standalone'),
        ];
        $db = $this->dbSequenced([
            $this->statement(['fetchColumn' => 4]),
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Permission();
        $grouped = $model->getGroupedPermissions();

        // The group key is always explode('_', $name)[0] — 'other' is only used
        // as a fallback when that segment itself is falsy (e.g. an empty name),
        // so a name with no underscore ('standalone') groups under its own name.
        $this->assertSame(['create', 'standalone', 'view'], array_keys($grouped));
        $this->assertCount(1, $grouped['create']);
        $this->assertCount(2, $grouped['view']);
        $this->assertCount(1, $grouped['standalone']);
    }

    public function testGetGroupedPermissionsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to group permissions:');

        $model->getGroupedPermissions();
    }

    // -------------------------------------------------- getOrganizedPermissions()

    public function testGetOrganizedPermissionsUsesKnownEntityAndActionConfig(): void
    {
        $rows = [
            $this->permissionRow(1, 'view_tasks'),
            $this->permissionRow(2, 'create_tasks'),
        ];
        $db = $this->dbSequenced([
            $this->statement(['fetchColumn' => 2]),
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Permission();
        $organized = $model->getOrganizedPermissions();

        $this->assertArrayHasKey('tasks', $organized);
        $this->assertSame('Tasks', $organized['tasks']['config']['label']);
        // Sorted by action level: view(1) before create(2)
        $this->assertSame('view_tasks', $organized['tasks']['permissions'][0]['name']);
        $this->assertSame('create_tasks', $organized['tasks']['permissions'][1]['name']);
        $this->assertSame('view', $organized['tasks']['permissions'][0]['action']);
        $this->assertSame('blue', $organized['tasks']['permissions'][0]['action_config']['color']);
    }

    public function testGetOrganizedPermissionsFallsBackToDefaultsForUnknownEntityAndAction(): void
    {
        $rows = [$this->permissionRow(1, 'frobnicate_widgets')];
        $db = $this->dbSequenced([
            $this->statement(['fetchColumn' => 1]),
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Permission();
        $organized = $model->getOrganizedPermissions();

        $this->assertArrayHasKey('widgets', $organized);
        $this->assertSame('Widgets', $organized['widgets']['config']['label']);
        $this->assertSame('collection', $organized['widgets']['config']['icon']);
        $permission = $organized['widgets']['permissions'][0];
        $this->assertSame('frobnicate', $permission['action']);
        $this->assertSame(0, $permission['action_config']['level']);
        $this->assertSame('Frobnicate', $permission['action_config']['label']);
        // Entities outside the predefined config order are appended at the end.
        $this->assertSame('widgets', array_key_last($organized));
    }

    public function testGetOrganizedPermissionsUsesOtherEntityWhenNameHasNoUnderscore(): void
    {
        $rows = [$this->permissionRow(1, 'standalone')];
        $db = $this->dbSequenced([
            $this->statement(['fetchColumn' => 1]),
            $this->statement(['fetchAll' => $rows]),
        ]);
        $this->seedDatabase($db);

        $model = new Permission();
        $organized = $model->getOrganizedPermissions();

        $this->assertArrayHasKey('other', $organized);
        $this->assertSame('standalone', $organized['other']['permissions'][0]['action']);
    }

    public function testGetOrganizedPermissionsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to organize permissions:');

        $model->getOrganizedPermissions();
    }

    // ------------------------------------------------------------ existsByName()

    public function testExistsByNameReturnsTrueWhenCountPositive(): void
    {
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 1])], $calls);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->assertTrue($model->existsByName('view_tasks'));
        $this->assertSame('view_tasks', $calls[0]['params'][':name']);
    }

    public function testExistsByNameReturnsFalseWhenCountZero(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 0])]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->assertFalse($model->existsByName('missing'));
    }

    public function testExistsByNameWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to check if permission exists:');

        $model->existsByName('view_tasks');
    }

    // --------------------------------------------------------------- getByName()

    public function testGetByNameReturnsPermission(): void
    {
        $row = $this->permissionRow(1, 'view_tasks');
        $db = $this->dbSequenced([$this->statement(['fetch' => $row])]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->assertSame($row, $model->getByName('view_tasks'));
    }

    public function testGetByNameReturnsNullWhenNotFound(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->assertNull($model->getByName('missing'));
    }

    public function testGetByNameWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get permission by name:');

        $model->getByName('view_tasks');
    }

    // ---------------------------------------------------------- createIfNotExists()

    public function testCreateIfNotExistsReturnsExistingIdWithoutCreating(): void
    {
        $row = $this->permissionRow(42, 'view_tasks');
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetch' => $row])], $calls);
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->createIfNotExists('view_tasks', 'View tasks');

        $this->assertSame(42, $result);
        $this->assertCount(1, $calls); // only the lookup ran, no INSERT
    }

    public function testCreateIfNotExistsCreatesNewPermissionWhenMissing(): void
    {
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetch' => false])], $calls);
        $db->method('executeInsertUpdate')->willReturn(true);
        $db->method('lastInsertId')->willReturn(99);
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->createIfNotExists('new_permission', 'A new permission');

        $this->assertSame(99, $result);
    }

    public function testCreateIfNotExistsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create permission:');

        $model->createIfNotExists('view_tasks');
    }

    // --------------------------------------------------------------- bulkCreate()

    public function testBulkCreateReusesExistingAndCreatesMissingThenCommits(): void
    {
        $existing = $this->permissionRow(5, 'view_tasks');
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => $existing]), // getByName('view_tasks') -> exists
            $this->statement(['fetch' => false]),      // getByName('new_perm') -> missing
        ], $calls);
        $db->method('executeInsertUpdate')->willReturn(true);
        $db->method('lastInsertId')->willReturn(77);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('commit');
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->bulkCreate([
            ['name' => 'view_tasks', 'description' => 'existing'],
            ['name' => 'new_perm', 'description' => 'brand new'],
        ]);

        $this->assertSame([5, 77], $result);
    }

    public function testBulkCreateRollsBackAndThrowsOnFailure(): void
    {
        $db = $this->dbSequenced([new RuntimeException('lookup failed')]);
        $db->method('beginTransaction');
        $db->expects($this->once())->method('rollBack');
        $db->expects($this->never())->method('commit');
        $this->seedDatabase($db);

        $model = new Permission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to bulk create permissions:');

        $model->bulkCreate([['name' => 'view_tasks']]);
    }

    // -------------------------------------------------------------- getRoleTemplates()

    public function testGetRoleTemplatesReturnsExpectedKeysWithPermissionLists(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Permission();
        $templates = $model->getRoleTemplates();

        $this->assertSame(['admin', 'manager', 'developer', 'client', 'viewer'], array_keys($templates));
        $this->assertSame('Administrator', $templates['admin']['name']);
        $this->assertContains('manage_settings', $templates['admin']['permissions']);
        $this->assertContains('view_dashboard', $templates['viewer']['permissions']);
    }

    // -------------------------------------------------------- getTemplatePermissionIds()

    public function testGetTemplatePermissionIdsReturnsEmptyArrayForUnknownTemplate(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Permission();

        $this->assertSame([], $model->getTemplatePermissionIds('does-not-exist'));
    }

    public function testGetTemplatePermissionIdsResolvesKnownNamesAndSkipsMissingOnes(): void
    {
        // 'client' template permissions: view_dashboard, view_projects, view_tasks,
        // view_milestones, view_sprints. Simulate only the first two existing.
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => $this->permissionRow(1, 'view_dashboard')]),
            $this->statement(['fetch' => $this->permissionRow(2, 'view_projects')]),
            $this->statement(['fetch' => false]),
            $this->statement(['fetch' => false]),
            $this->statement(['fetch' => false]),
        ], $calls);
        $this->seedDatabase($db);

        $model = new Permission();
        $result = $model->getTemplatePermissionIds('client');

        $this->assertSame([1, 2], $result);
        $this->assertCount(5, $calls);
    }
}
