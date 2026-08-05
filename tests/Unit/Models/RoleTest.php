<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Role;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Role model: detail loading with selective
 * eager-loading options, permission/user association queries and mutations
 * (including the batched syncPermissions upsert), permission checks, and
 * the paginated listing helper.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * Role does not use the Searchable trait and does not override
 * create()/update(), so no SearchIndex/SecurityService wiring is needed here.
 */
// Config is declared because Database consults Config::isProduction(). It is a
// process-wide singleton, so only the first test in a run to reach it executes
// its body — which test that is moves with execution order (see MilestoneTest).
#[CoversClass(Role::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
final class RoleTest extends TestCase
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

    // ------------------------------------------------------------ findWithDetails()

    public function testFindWithDetailsLoadsPermissionsByDefault(): void
    {
        $role = (object)['id' => 1, 'name' => 'Admin'];
        $permissions = [(object)['id' => 1, 'name' => 'view_tasks']];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => $role]), // find()
            $this->statement(['fetchAll' => $permissions]), // getPermissions()
        ]);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->findWithDetails(1);

        $this->assertSame($permissions, $result->permissions);
        $this->assertFalse(property_exists($result, 'users'));
        $this->assertFalse(property_exists($result, 'user_count'));
    }

    /**
     * Regression: find() returns object|false, and this method is typed ?object.
     * Returning that false under strict_types raised a TypeError — an Error, not
     * an Exception — so it slipped past findWithDetails()'s own catch and
     * propagated uncaught instead of yielding null. Every lookup of a
     * non-existent role hit it, including via findBasic() and
     * findWithPermissions().
     */
    public function testFindWithDetailsReturnsNullWhenRoleDoesNotExist(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertNull($model->findWithDetails(999));
    }

    public function testFindBasicReturnsNullWhenRoleDoesNotExist(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertNull($model->findBasic(999));
    }

    public function testFindWithDetailsLoadsUsersAndCountsWhenRequested(): void
    {
        $role = (object)['id' => 1, 'name' => 'Admin'];
        $permissions = [(object)['id' => 1, 'name' => 'view_tasks']];
        $users = [(object)['id' => 5, 'first_name' => 'Ada']];
        $counts = (object)['permission_count' => 3, 'user_count' => 2];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => $role]),
            $this->statement(['fetchAll' => $permissions]),
            $this->statement(['fetchAll' => $users]),
            $this->statement(['fetch' => $counts]),
        ]);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->findWithDetails(1, ['users' => true, 'counts' => true]);

        $this->assertSame($permissions, $result->permissions);
        $this->assertSame($users, $result->users);
        $this->assertSame(3, $result->permission_count);
        $this->assertSame(2, $result->user_count);
    }

    // NOTE: findWithDetails()'s "not found" path is not exercised here.
    // $role = $this->find($id) (BaseModel::find(), typed object|false) is
    // returned directly from a method declared `: ?object`. When the role
    // doesn't exist, $role is `false`, and returning `false` from an `?object`
    // return type is a genuine bug in src/Models/Role.php (line 108): it
    // throws an uncaught \TypeError (a \Error, not \Exception) that bypasses
    // this method's own `catch (\Exception $e)` and propagates to the caller,
    // instead of yielding `null` as the signature promises. Same defect is
    // reachable via findBasic(). Not asserted here per instructions — a test
    // must not enshrine buggy behaviour as if it were correct.

    public function testFindWithDetailsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find role with details:');

        $model->findWithDetails(1);
    }

    public function testFindWithPermissionsDelegatesToFindWithDetails(): void
    {
        $role = (object)['id' => 1, 'name' => 'Admin'];
        $permissions = [(object)['id' => 1, 'name' => 'view_tasks']];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => $role]),
            $this->statement(['fetchAll' => $permissions]),
        ]);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->findWithPermissions(1);

        $this->assertSame($permissions, $result->permissions);
    }

    public function testFindBasicOmitsRelatedData(): void
    {
        $role = (object)['id' => 1, 'name' => 'Admin'];
        $db = $this->dbSequenced([$this->statement(['fetch' => $role])]);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->findBasic(1);

        $this->assertFalse(property_exists($result, 'permissions'));
        $this->assertFalse(property_exists($result, 'users'));
    }

    // ------------------------------------------------------------- getPermissions()

    public function testGetPermissionsReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'name' => 'view_tasks']];
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->getPermissions(3);

        $this->assertSame($rows, $result);
        $this->assertSame(3, $calls[0]['params'][':role_id']);
    }

    public function testGetPermissionsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get role permissions:');

        $model->getPermissions(3);
    }

    // ------------------------------------------------------------------- getUsers()

    public function testGetUsersReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'first_name' => 'Ada']];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertSame($rows, $model->getUsers(3));
    }

    public function testGetUsersWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get role users:');

        $model->getUsers(3);
    }

    // -------------------------------------------------------------- syncPermissions()

    public function testSyncPermissionsDeletesThenBatchInsertsWithIndexedPlaceholders(): void
    {
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true, true], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->syncPermissions(1, [10, 20]);

        $this->assertTrue($result);
        $this->assertCount(2, $calls);
        $this->assertStringContainsString('DELETE FROM role_permissions WHERE role_id = :role_id', $calls[0]['sql']);
        $this->assertSame(1, $calls[0]['params'][':role_id']);
        $this->assertStringContainsString('(:role_id_0, :permission_id_0),(:role_id_1, :permission_id_1)', $calls[1]['sql']);
        $this->assertSame(1, $calls[1]['params'][':role_id_0']);
        $this->assertSame(10, $calls[1]['params'][':permission_id_0']);
        $this->assertSame(1, $calls[1]['params'][':role_id_1']);
        $this->assertSame(20, $calls[1]['params'][':permission_id_1']);
    }

    public function testSyncPermissionsWithEmptyArraySkipsInsertAfterDelete(): void
    {
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->syncPermissions(1, []);

        $this->assertTrue($result);
        $this->assertCount(1, $calls); // only the DELETE ran
    }

    public function testSyncPermissionsBatchesInsertsAtOneHundredPerStatement(): void
    {
        $permissionIds = range(1, 150);
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true, true, true], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->syncPermissions(1, $permissionIds);

        $this->assertTrue($result);
        // 1 DELETE + 2 batched INSERTs (100 + 50)
        $this->assertCount(3, $calls);
        $this->assertStringContainsString(':permission_id_99', $calls[1]['sql']);
        $this->assertArrayNotHasKey(':permission_id_100', $calls[1]['params']);
        $this->assertStringContainsString(':permission_id_49', $calls[2]['sql']);
    }

    public function testSyncPermissionsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequencedInsertUpdate([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to sync permissions:');

        $model->syncPermissions(1, [10]);
    }

    // ------------------------------------------------------------- assignPermission()

    public function testAssignPermissionInsertsWithUpsertClause(): void
    {
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->assignPermission(1, 10);

        $this->assertTrue($result);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE role_id = :role_id', $calls[0]['sql']);
        $this->assertSame(1, $calls[0]['params'][':role_id']);
        $this->assertSame(10, $calls[0]['params'][':permission_id']);
    }

    public function testAssignPermissionWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequencedInsertUpdate([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to assign permission:');

        $model->assignPermission(1, 10);
    }

    // ------------------------------------------------------------- removePermission()

    public function testRemovePermissionDeletesAssociation(): void
    {
        $calls = [];
        $db = $this->dbSequencedInsertUpdate([true], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->removePermission(1, 10);

        $this->assertTrue($result);
        $this->assertSame(1, $calls[0]['params'][':role_id']);
        $this->assertSame(10, $calls[0]['params'][':permission_id']);
    }

    public function testRemovePermissionWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequencedInsertUpdate([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove permission:');

        $model->removePermission(1, 10);
    }

    // ------------------------------------------------------- getAllWithPermissionCounts()

    public function testGetAllWithPermissionCountsReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'permission_count' => 3, 'user_count' => 2]];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertSame($rows, $model->getAllWithPermissionCounts());
    }

    public function testGetAllWithPermissionCountsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get roles with permission counts:');

        $model->getAllWithPermissionCounts();
    }

    // -------------------------------------------------------------------- hasPermission()

    public function testHasPermissionReturnsTrueWhenCountPositive(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 1])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertTrue($model->hasPermission(1, 10));
    }

    public function testHasPermissionReturnsFalseWhenCountZero(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 0])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertFalse($model->hasPermission(1, 10));
    }

    public function testHasPermissionWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to check permission:');

        $model->hasPermission(1, 10);
    }

    // -------------------------------------------------------------- hasPermissionByName()

    public function testHasPermissionByNameReturnsTrueWhenCountPositive(): void
    {
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 1])], $calls);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertTrue($model->hasPermissionByName(1, 'view_tasks'));
        $this->assertSame('view_tasks', $calls[0]['params'][':permission_name']);
    }

    public function testHasPermissionByNameReturnsFalseWhenCountZero(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetchColumn' => 0])]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->assertFalse($model->hasPermissionByName(1, 'missing'));
    }

    public function testHasPermissionByNameWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to check permission by name:');

        $model->hasPermissionByName(1, 'view_tasks');
    }

    // ------------------------------------------------------------- getAllWithDetails()

    public function testGetAllWithDetailsReturnsRecordsAndTotalWithComputedOffset(): void
    {
        $rows = [(object)['id' => 1, 'user_count' => 2, 'permission_count' => 5]];
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['fetchAll' => $rows]),
            $this->statement(['fetchColumn' => 42]),
        ], $calls);
        $this->seedDatabase($db);

        $model = new Role();
        $result = $model->getAllWithDetails(3, 10);

        $this->assertSame($rows, $result['records']);
        $this->assertSame(42, $result['total']);
        $this->assertSame(20, $calls[0]['params'][':offset']); // (page 3 - 1) * limit 10
        $this->assertSame(10, $calls[0]['params'][':limit']);
    }

    public function testGetAllWithDetailsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Role();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get roles with details:');

        $model->getAllWithDetails();
    }
}
