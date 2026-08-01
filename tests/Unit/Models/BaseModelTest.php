<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Database;
use App\Exceptions\NotFoundException;
use App\Models\BaseModel;
use App\Services\SecurityService;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Tests for BaseModel — the shared CRUD/query foundation every model extends.
 *
 * Database is always a mock injected via reflection (see ModelProbe/makeModel())
 * so no live MySQL connection is opened. SecurityService is seeded as a mock
 * singleton only for the tests that exercise the create()/update() exception
 * wrapping paths, and reset to null in tearDown so it cannot leak into other
 * test files running in the same process.
 */
#[CoversClass(BaseModel::class)]
#[UsesClass(Database::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(SecurityService::class)]
final class BaseModelTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->seedSecurityService(null);
        $this->seedDatabaseSingleton(null);

        parent::tearDown();
    }

    private function seedSecurityService(?SecurityService $service): void
    {
        $ref = new ReflectionClass(SecurityService::class);
        $ref->getProperty('instance')->setValue(null, $service);
    }

    private function seedDatabaseSingleton(?Database $db): void
    {
        $ref = new ReflectionClass(Database::class);
        $ref->getProperty('instance')->setValue(null, $db);
    }

    private function makeModel(Database $db): ModelProbe
    {
        $reflection = new ReflectionClass(ModelProbe::class);
        /** @var ModelProbe $model */
        $model = $reflection->newInstanceWithoutConstructor();
        $reflection->getParentClass()->getProperty('db')->setValue($model, $db);

        return $model;
    }

    private function setProperty(object $model, string $name, mixed $value): void
    {
        (new ReflectionClass(BaseModel::class))->getProperty($name)->setValue($model, $value);
    }

    /**
     * @return array{sql: string, params: array}
     */
    private function sql(array $calls, int $index = 0): array
    {
        return [
            'sql' => trim(preg_replace('/\s+/', ' ', $calls[$index]['sql'])),
            'params' => $calls[$index]['params'],
        ];
    }

    private function statementReturningColumn(mixed $value): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn($value);

        return $stmt;
    }

    private function dbCapturingQueries(PDOStatement $stmt, array &$calls): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$calls, $stmt): PDOStatement {
                $calls[] = ['sql' => $sql, 'params' => $params];

                return $stmt;
            }
        );

        return $db;
    }

    // ----------------------------------------------------------------- count()

    public function testCountWithNoConditionsBuildsBareCountQuery(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('5'), $calls);
        $model = $this->makeModel($db);

        $this->assertSame(5, $model->count());
        $this->assertSame('SELECT COUNT(*) FROM `widgets`', $this->sql($calls)['sql']);
    }

    public function testCountWithSimpleEqualityCondition(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('2'), $calls);
        $model = $this->makeModel($db);

        $model->count(['status' => 'active']);

        $this->assertStringContainsString('WHERE status = :status', $this->sql($calls)['sql']);
        $this->assertSame('active', $this->sql($calls)['params'][':status']);
    }

    public function testCountWithGreaterThanOperator(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['score' => ['>' => 10]]);

        $this->assertStringContainsString('score > :score', $this->sql($calls)['sql']);
        $this->assertSame(10, $this->sql($calls)['params'][':score']);
    }

    public function testCountWithLessThanOperator(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['score' => ['<' => 3]]);

        $this->assertStringContainsString('score < :score', $this->sql($calls)['sql']);
        $this->assertSame(3, $this->sql($calls)['params'][':score']);
    }

    public function testCountWithNotInOperator(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['status' => ['NOT IN' => ['a', 'b']]]);

        $this->assertStringContainsString(
            'status NOT IN (:not_in_status_0, :not_in_status_1)',
            $this->sql($calls)['sql']
        );
        $this->assertSame('a', $this->sql($calls)['params'][':not_in_status_0']);
        $this->assertSame('b', $this->sql($calls)['params'][':not_in_status_1']);
    }

    public function testCountWithNotInOperatorRequiresAnArray(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NOT IN requires an array of values');

        $model->count(['status' => ['NOT IN' => 'x']]);
    }

    public function testCountWithIsOperatorAndNullValueProducesIsNull(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['deleted_at' => ['IS' => null]]);

        $this->assertStringContainsString('deleted_at IS NULL', $this->sql($calls)['sql']);
        $this->assertArrayNotHasKey(':deleted_at', $this->sql($calls)['params']);
    }

    public function testCountWithIsOperatorAndNonNullValueProducesEquality(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['status' => ['IS' => 'active']]);

        $this->assertStringContainsString('status = :status', $this->sql($calls)['sql']);
        $this->assertSame('active', $this->sql($calls)['params'][':status']);
    }

    public function testCountWithIsNotOperatorAndNullValueProducesIsNotNull(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['deleted_at' => ['IS NOT' => null]]);

        $this->assertStringContainsString('deleted_at IS NOT NULL', $this->sql($calls)['sql']);
    }

    public function testCountWithIsNotOperatorAndNonNullValueProducesNotEquals(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['status' => ['IS NOT' => 'archived']]);

        $this->assertStringContainsString('status != :status', $this->sql($calls)['sql']);
        $this->assertSame('archived', $this->sql($calls)['params'][':status']);
    }

    public function testCountWithUnknownOperatorFallsBackToEquality(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->count(['status' => ['weird' => 'val']]);

        $this->assertStringContainsString('status = :status', $this->sql($calls)['sql']);
        $this->assertSame('val', $this->sql($calls)['params'][':status']);
    }

    public function testCountInvalidColumnNameIsRejected(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column name');

        $model->count(['id = 1 OR 1=1--' => 1]);
    }

    public function testCountInvalidTableNameIsRejected(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));
        $this->setProperty($model, 'table', 'widgets; DROP TABLE widgets--');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name');

        $model->count();
    }

    public function testCountWrapsQueryExceptions(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('db down'));
        $model = $this->makeModel($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error counting records: db down');

        $model->count();
    }

    // ---------------------------------------------------------------- create()

    public function testCreateReturnsNewIdAndInvokesAfterSave(): void
    {
        $captured = ['sql' => '', 'params' => []];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $db->method('lastInsertId')->willReturn(7);
        $model = $this->makeModel($db);

        $newId = $model->create(['name' => 'Widget A']);

        $this->assertSame(7, $newId);
        $this->assertSame('INSERT INTO widgets (name) VALUES (:name)', $captured['sql']);
        $this->assertSame('Widget A', $captured['params'][':name']);
        $this->assertTrue($model->afterSaveCalled);
        $this->assertSame(7, $model->afterSaveId);
    }

    public function testCreateFiltersGuardedFields(): void
    {
        $captured = ['sql' => '', 'params' => []];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $db->method('lastInsertId')->willReturn(1);
        $model = $this->makeModel($db);

        $model->create([
            'id' => 99,
            'guid' => 'abc',
            'created_at' => 'now',
            'updated_at' => 'now',
            'name' => 'Widget A',
        ]);

        $this->assertSame('INSERT INTO widgets (name) VALUES (:name)', $captured['sql']);
        $this->assertSame('Widget A', $captured['params'][':name']);
    }

    public function testCreateReturnsFalseWhenInsertFails(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturn(false);
        $db->expects($this->never())->method('lastInsertId');
        $model = $this->makeModel($db);

        $this->assertFalse($model->create(['name' => 'x']));
        $this->assertFalse($model->afterSaveCalled);
    }

    public function testCreateWrapsExceptionUsingSecurityService(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('insert failed'));
        $model = $this->makeModel($db);

        $securityMock = $this->createMock(SecurityService::class);
        $securityMock->method('getSafeErrorMessage')->willReturn('Safe message for create');
        $this->seedSecurityService($securityMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Safe message for create');

        $model->create(['name' => 'x']);
    }

    public function testCreateFallsBackToDefaultMessageWhenSecurityServiceThrows(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('insert failed'));
        $model = $this->makeModel($db);

        $securityMock = $this->createMock(SecurityService::class);
        $securityMock->method('getSafeErrorMessage')->willThrowException(new RuntimeException('security down'));
        $this->seedSecurityService($securityMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create widgets record');

        $model->create(['name' => 'x']);
    }

    // ---------------------------------------------------------------- update()

    public function testUpdateAppliesChangesAndInvokesAfterSave(): void
    {
        $captured = ['sql' => '', 'params' => []];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $model = $this->makeModel($db);

        $result = $model->update(5, ['name' => 'Updated', 'status' => 'active']);

        $this->assertTrue($result);
        $this->assertSame(
            'UPDATE widgets SET name = :name, status = :status WHERE id = :id AND is_deleted = 0',
            $captured['sql']
        );
        $this->assertSame('Updated', $captured['params'][':name']);
        $this->assertSame('active', $captured['params'][':status']);
        $this->assertSame(5, $captured['params'][':id']);
        $this->assertTrue($model->afterSaveCalled);
        $this->assertSame(5, $model->afterSaveId);
    }

    public function testUpdateOmitsSoftDeleteClauseWhenDisabled(): void
    {
        $captured = ['sql' => '', 'params' => []];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $model = $this->makeModel($db);
        $this->setProperty($model, 'usesSoftDeletes', false);

        $model->update(5, ['name' => 'Updated']);

        $this->assertSame('UPDATE widgets SET name = :name WHERE id = :id', $captured['sql']);
    }

    public function testUpdateReturnsTrueWithoutTouchingDatabaseWhenDataIsFullyGuarded(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->never())->method('executeInsertUpdate');
        $model = $this->makeModel($db);

        $this->assertTrue($model->update(1, ['id' => 1]));
        $this->assertFalse($model->afterSaveCalled);
    }

    public function testUpdateDoesNotInvokeAfterSaveWhenUpdateFails(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturn(false);
        $model = $this->makeModel($db);

        $this->assertFalse($model->update(1, ['name' => 'x']));
        $this->assertFalse($model->afterSaveCalled);
    }

    /**
     * update()'s catch block throws a RuntimeException built from the
     * SecurityService-sanitized message, but that throw happens INSIDE its
     * own try, and the very next catch (\Exception $securityException) also
     * matches RuntimeException — so the sanitized message is always
     * discarded and the generic fallback is what actually propagates,
     * regardless of what SecurityService returns.
     */
    public function testUpdateExceptionAlwaysFallsBackToGenericMessage(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willThrowException(new RuntimeException('constraint violation'));
        $model = $this->makeModel($db);

        $securityMock = $this->createMock(SecurityService::class);
        $securityMock->method('getSafeErrorMessage')->willReturn('This message is unreachable');
        $this->seedSecurityService($securityMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update widgets record');

        $model->update(1, ['name' => 'x']);
    }

    // ------------------------------------------------------------------ find()

    public function testFindReturnsRecordAndHidesConfiguredAttributes(): void
    {
        $record = (object) ['id' => 1, 'name' => 'Ann', 'password' => 'secret'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->with(PDO::FETCH_OBJ)->willReturn($record);

        $calls = [];
        $db = $this->dbCapturingQueries($stmt, $calls);
        $model = $this->makeModel($db);
        $this->setProperty($model, 'hidden', ['password']);

        $result = $model->find(1);

        $this->assertNotFalse($result);
        $this->assertSame('Ann', $result->name);
        $this->assertFalse(property_exists($result, 'password'));
        $this->assertSame('SELECT * FROM widgets WHERE id = :id AND is_deleted = 0', $this->sql($calls)['sql']);
    }

    public function testFindReturnsFalseWhenRecordNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $calls = [];
        $db = $this->dbCapturingQueries($stmt, $calls);
        $model = $this->makeModel($db);

        $this->assertFalse($model->find(999));
    }

    public function testFindOmitsSoftDeleteFilterWhenDisabled(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $calls = [];
        $db = $this->dbCapturingQueries($stmt, $calls);
        $model = $this->makeModel($db);
        $this->setProperty($model, 'usesSoftDeletes', false);

        $model->find(1);

        $this->assertSame('SELECT * FROM widgets WHERE id = :id', $this->sql($calls)['sql']);
    }

    // ------------------------------------------------------------ findOrFail()

    public function testFindOrFailReturnsRecordWhenFound(): void
    {
        $record = (object) ['id' => 1];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($record);
        $calls = [];
        $db = $this->dbCapturingQueries($stmt, $calls);
        $model = $this->makeModel($db);

        $this->assertSame($record, $model->findOrFail(1));
    }

    public function testFindOrFailThrowsNotFoundExceptionWhenMissing(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $calls = [];
        $db = $this->dbCapturingQueries($stmt, $calls);
        $model = $this->makeModel($db);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('ModelProbe with ID 42 not found');

        $model->findOrFail(42);
    }

    // ----------------------------------------------------------------- getAll()

    private function dbForGetAll(mixed $total, array $records, array &$calls): Database
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn($total);
        $stmt->method('fetchAll')->willReturn($records);

        return $this->dbCapturingQueries($stmt, $calls);
    }

    public function testGetAllReturnsTotalAndRecords(): void
    {
        $rows = [(object) ['id' => 1], (object) ['id' => 2]];
        $calls = [];
        $db = $this->dbForGetAll('2', $rows, $calls);
        $model = $this->makeModel($db);

        $result = $model->getAll();

        $this->assertSame('2', $result['total']);
        $this->assertCount(2, $result['records']);
        $this->assertStringStartsWith('SELECT COUNT(*) FROM widgets', $this->sql($calls, 0)['sql']);
        $this->assertStringStartsWith('SELECT * FROM widgets', $this->sql($calls, 1)['sql']);
    }

    public function testGetAllInvalidOrderByIsRejected(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order column');

        $model->getAll([], 1, 10, 'id; DROP TABLE widgets--');
    }

    public function testGetAllInvalidOrderDirIsRejected(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order direction');

        $model->getAll([], 1, 10, 'id', 'SIDEWAYS');
    }

    public function testGetAllInvalidFilterFieldIsRejected(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filter field');

        $model->getAll(['bad field;' => 1]);
    }

    public function testGetAllAppliesSimpleFilter(): void
    {
        $calls = [];
        $db = $this->dbForGetAll('0', [], $calls);
        $model = $this->makeModel($db);

        $model->getAll(['status' => 'active']);

        $this->assertStringContainsString('status = :status', $this->sql($calls, 0)['sql']);
        $this->assertSame('active', $this->sql($calls, 0)['params'][':status']);
    }

    public function testGetAllAppliesArrayFilterAsInClause(): void
    {
        $calls = [];
        $db = $this->dbForGetAll('0', [], $calls);
        $model = $this->makeModel($db);

        $model->getAll(['status' => ['a', 'b']]);

        $this->assertStringContainsString('status IN (:status_0,:status_1)', $this->sql($calls, 0)['sql']);
        $this->assertSame('a', $this->sql($calls, 0)['params'][':status_0']);
        $this->assertSame('b', $this->sql($calls, 0)['params'][':status_1']);
    }

    public function testGetAllAppliesSearchFilterAcrossSearchableFields(): void
    {
        $calls = [];
        $db = $this->dbForGetAll('0', [], $calls);
        $model = $this->makeModel($db);
        $this->setProperty($model, 'searchable', ['name', 'email']);

        $model->getAll(['search' => 'foo']);

        $sql = $this->sql($calls, 0)['sql'];
        $this->assertStringContainsString('(name LIKE :name_search OR email LIKE :email_search)', $sql);
        $this->assertSame('%foo%', $this->sql($calls, 0)['params'][':name_search']);
        $this->assertSame('%foo%', $this->sql($calls, 0)['params'][':email_search']);
    }

    public function testGetAllIgnoresSearchFilterWhenNoSearchableFieldsDefined(): void
    {
        $calls = [];
        $db = $this->dbForGetAll('0', [], $calls);
        $model = $this->makeModel($db);

        $model->getAll(['search' => 'foo']);

        $this->assertStringNotContainsString('LIKE', $this->sql($calls, 0)['sql']);
    }

    public function testGetAllOmitsSoftDeleteConditionWhenDisabled(): void
    {
        $calls = [];
        $db = $this->dbForGetAll('0', [], $calls);
        $model = $this->makeModel($db);
        $this->setProperty($model, 'usesSoftDeletes', false);

        $model->getAll();

        $this->assertStringNotContainsString('is_deleted', $this->sql($calls, 0)['sql']);
    }

    public function testGetAllComputesOffsetFromPageAndLimit(): void
    {
        $calls = [];
        $db = $this->dbForGetAll('0', [], $calls);
        $model = $this->makeModel($db);

        $model->getAll([], 3, 5);

        $this->assertSame(10, $this->sql($calls, 1)['params'][':offset']);
        $this->assertSame(5, $this->sql($calls, 1)['params'][':limit']);
    }

    public function testGetAllHidesConfiguredAttributesOnEachRecord(): void
    {
        $rows = [(object) ['id' => 1, 'secret' => 'x']];
        $calls = [];
        $db = $this->dbForGetAll('1', $rows, $calls);
        $model = $this->makeModel($db);
        $this->setProperty($model, 'hidden', ['secret']);

        $result = $model->getAll();

        $this->assertFalse(property_exists($result['records'][0], 'secret'));
    }

    // ------------------------------------------------------------------ delete()

    public function testDeleteSoftDeletesAndInvokesAfterDelete(): void
    {
        $captured = ['sql' => '', 'params' => []];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $model = $this->makeModel($db);

        $this->assertTrue($model->delete(9));
        $this->assertSame('UPDATE widgets SET is_deleted = 1 WHERE id = :id', $captured['sql']);
        $this->assertSame(9, $captured['params'][':id']);
        $this->assertTrue($model->afterDeleteCalled);
        $this->assertSame(9, $model->afterDeleteId);
    }

    public function testDeleteHardDeletesWhenSoftDeletesDisabled(): void
    {
        $captured = ['sql' => '', 'params' => []];
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params) use (&$captured): bool {
                $captured = ['sql' => $sql, 'params' => $params];

                return true;
            }
        );
        $model = $this->makeModel($db);
        $this->setProperty($model, 'usesSoftDeletes', false);

        $this->assertTrue($model->delete(9));
        $this->assertSame('DELETE FROM widgets WHERE id = :id', $captured['sql']);
    }

    public function testDeleteDoesNotInvokeAfterDeleteOnFailure(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeInsertUpdate')->willReturn(false);
        $model = $this->makeModel($db);

        $this->assertFalse($model->delete(9));
        $this->assertFalse($model->afterDeleteCalled);
    }

    // -------------------------------------------------------- protected helpers

    public function testPrepareSaveDataStripsGuardedFields(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $result = $model->callPrepareSaveData([
            'id' => 1,
            'guid' => 'g',
            'created_at' => 'c',
            'updated_at' => 'u',
            'name' => 'Widget',
        ]);

        $this->assertSame(['name' => 'Widget'], $result);
    }

    public function testHideAttributesRemovesConfiguredFields(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));
        $this->setProperty($model, 'hidden', ['password']);

        $record = (object) ['id' => 1, 'password' => 'secret', 'name' => 'Ann'];
        $result = $model->callHideAttributes($record);

        $this->assertFalse(property_exists($result, 'password'));
        $this->assertSame('Ann', $result->name);
    }

    // ------------------------------------------------------------- transactions

    public function testBeginTransactionDelegatesToDatabase(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('beginTransaction')->willReturn(true);
        $model = $this->makeModel($db);

        $this->assertTrue($model->beginTransaction());
    }

    public function testCommitDelegatesToDatabase(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('commit')->willReturn(true);
        $model = $this->makeModel($db);

        $this->assertTrue($model->commit());
    }

    public function testRollBackDelegatesToDatabase(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $model = $this->makeModel($db);

        $this->assertTrue($model->rollBack());
    }

    // ----------------------------------------------------------------- pluralize()

    public function testPluralizeHandlesIrregularWords(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $pairs = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'mouse' => 'mice',
            'goose' => 'geese',
        ];

        foreach ($pairs as $singular => $plural) {
            $this->assertSame($plural, $model->pluralizeWord($singular));
        }
    }

    public function testPluralizeAddsEsForSXZChSh(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->assertSame('boxes', $model->pluralizeWord('box'));
        $this->assertSame('buses', $model->pluralizeWord('bus'));
        $this->assertSame('buzzes', $model->pluralizeWord('buzz'));
        $this->assertSame('matches', $model->pluralizeWord('match'));
        $this->assertSame('wishes', $model->pluralizeWord('wish'));
    }

    public function testPluralizeChangesConsonantYToIes(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->assertSame('companies', $model->pluralizeWord('company'));
        $this->assertSame('categories', $model->pluralizeWord('category'));
    }

    public function testPluralizeChangesFOrFeToVes(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->assertSame('leaves', $model->pluralizeWord('leaf'));
        $this->assertSame('knives', $model->pluralizeWord('knife'));
    }

    public function testPluralizeAddsEsForConsonantO(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->assertSame('heroes', $model->pluralizeWord('hero'));
        $this->assertSame('potatoes', $model->pluralizeWord('potato'));
    }

    public function testPluralizeDefaultAddsS(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->assertSame('tasks', $model->pluralizeWord('task'));
        $this->assertSame('cats', $model->pluralizeWord('cat'));
    }

    // --------------------------------------------------------- constructor table inference

    public function testConstructorInfersTableNameFromClassNameWhenNotExplicitlySet(): void
    {
        $this->seedDatabaseSingleton($this->createMock(Database::class));

        $instance = new CompanyBoxProbe();

        // CompanyBoxProbe -> company_box_probe -> pluralized. Inference uses the
        // full short class name, so every word is retained.
        $this->assertSame(
            'company_box_probes',
            (new ReflectionClass(BaseModel::class))->getProperty('table')->getValue($instance)
        );
    }

    // -------------------------------------------------------------- queryBuilderCount()

    public function testQueryBuilderCountBuildsBareCountQueryWithSoftDelete(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('4'), $calls);
        $model = $this->makeModel($db);

        $this->assertSame(4, $model->callQueryBuilderCount());
        $this->assertSame(
            'SELECT COUNT(*) FROM widgets WHERE widgets.is_deleted = 0',
            $this->sql($calls)['sql']
        );
    }

    public function testQueryBuilderCountAppliesAlias(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount(['alias' => 'w']);

        $sql = $this->sql($calls)['sql'];
        $this->assertStringContainsString('FROM widgets w', $sql);
        $this->assertStringContainsString('w.is_deleted = 0', $sql);
    }

    public function testQueryBuilderCountInvalidAliasIsRejected(): void
    {
        $model = $this->makeModel($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid alias');

        $model->callQueryBuilderCount(['alias' => 'w; DROP TABLE widgets--']);
    }

    public function testQueryBuilderCountAppliesWhereConditions(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount([
            'where' => [['column' => 'status', 'operator' => '!=', 'value' => 'archived']],
        ]);

        $sql = $this->sql($calls)['sql'];
        $this->assertStringContainsString('status != :where_0', $sql);
        $this->assertSame('archived', $this->sql($calls)['params'][':where_0']);
    }

    public function testQueryBuilderCountIsNullOperatorIsReachableWithNonNullPlaceholderValue(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount([
            'where' => [['column' => 'deleted_at', 'operator' => 'IS NULL', 'value' => true]],
        ]);

        $this->assertStringContainsString('deleted_at IS NULL', $this->sql($calls)['sql']);
        $this->assertArrayNotHasKey(':where_0', $this->sql($calls)['params']);
    }

    public function testQueryBuilderCountIsNotNullOperatorIsReachableWithNonNullPlaceholderValue(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount([
            'where' => [['column' => 'deleted_at', 'operator' => 'IS NOT NULL', 'value' => true]],
        ]);

        $this->assertStringContainsString('deleted_at IS NOT NULL', $this->sql($calls)['sql']);
    }

    public function testQueryBuilderCountIncompleteConditionIsSkipped(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount([
            'where' => [['column' => 'deleted_at', 'value' => null], ['column' => 'status', 'value' => 'active']],
        ]);

        // The incomplete first condition is skipped, but placeholders are numbered
        // by array position rather than by emission order, so the surviving
        // condition keeps index 1. The SQL is valid either way; this pins the
        // actual numbering so a future change to it is visible.
        $this->assertStringContainsString('status = :where_1', $this->sql($calls)['sql']);
        $this->assertSame('active', $this->sql($calls)['params'][':where_1']);
    }

    public function testQueryBuilderCountAppliesWhereRawConditions(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount([
            'whereRaw' => [['sql' => 'a = b', 'params' => [':p' => 1]]],
        ]);

        $this->assertStringContainsString('(a = b)', $this->sql($calls)['sql']);
        $this->assertSame(1, $this->sql($calls)['params'][':p']);
    }

    public function testQueryBuilderCountAppliesJoins(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount([
            'joins' => [['type' => 'inner', 'table' => 'other o', 'on' => 'widgets.id = o.widget_id']],
        ]);

        $this->assertStringContainsString('INNER JOIN other o ON widgets.id = o.widget_id', $this->sql($calls)['sql']);
    }

    public function testQueryBuilderCountIgnoreSoftDeleteOmitsThePredicate(): void
    {
        $calls = [];
        $db = $this->dbCapturingQueries($this->statementReturningColumn('1'), $calls);
        $model = $this->makeModel($db);

        $model->callQueryBuilderCount(['ignoreSoftDelete' => true]);

        $this->assertStringNotContainsString('is_deleted', $this->sql($calls)['sql']);
    }

    public function testQueryBuilderCountWrapsQueryExceptions(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new RuntimeException('boom'));
        $model = $this->makeModel($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Count query error: boom');

        $model->callQueryBuilderCount();
    }
}

/**
 * Concrete BaseModel exposing protected internals for direct assertion.
 */
final class ModelProbe extends BaseModel
{
    protected string $table = 'widgets';

    public bool $afterSaveCalled = false;
    public ?int $afterSaveId = null;
    public bool $afterDeleteCalled = false;
    public ?int $afterDeleteId = null;

    public function afterSave(int $id): void
    {
        $this->afterSaveCalled = true;
        $this->afterSaveId = $id;
    }

    public function afterDelete(int $id): void
    {
        $this->afterDeleteCalled = true;
        $this->afterDeleteId = $id;
    }

    public function callQueryBuilderCount(array $options = []): int
    {
        return $this->queryBuilderCount($options);
    }

    public function callPrepareSaveData(array $data): array
    {
        return $this->prepareSaveData($data);
    }

    public function callHideAttributes(object $record): object
    {
        return $this->hideAttributes($record);
    }

    public function pluralizeWord(string $word): string
    {
        // No setAccessible() call: it has been a no-op since PHP 8.1 and is
        // deprecated in 8.5, which failOnDeprecation turns into a build failure.
        $method = new ReflectionMethod(BaseModel::class, 'pluralize');

        return $method->invoke($this, $word);
    }
}

/**
 * Table left unset on purpose so the constructor's class-name-inference
 * branch runs: "CompanyBox" -> "company_box" -> pluralize -> "company_boxes".
 */
final class CompanyBoxProbe extends BaseModel
{
}
