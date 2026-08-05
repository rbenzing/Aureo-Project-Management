<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\TaskStatus;
use App\Models\BaseModel;
use App\Models\Concerns\Searchable;
use App\Models\User;
use App\Services\SecurityService;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Coverage-focused behavioural tests for the User model's query/mutation
 * surface that UserModelTest.php (property-only tests) does not touch:
 * detail loading (including the two methods that bypass Database::executeQuery
 * and call db->getConnection()->prepare() directly), role/permission lookup,
 * activation/reset-token lifecycle, company/project association mutations,
 * and the search-index row projection.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * getUserProjects()/getUserCompanies() call $this->db->getConnection()
 * directly and prepare()/bindValue()/execute() the statement themselves
 * (bypassing Database::executeQuery), so those two methods need a mocked PDO
 * + PDOStatement in addition to the Database mock. User does not override
 * create()/update(), so those paths (and the Searchable::afterSave chain
 * they'd trigger) are exercised by BaseModelTest instead — this file only
 * calls the methods User.php actually defines, plus the two Searchable
 * contract methods it implements directly.
 */
// Config is declared because Database consults Config::isProduction(). It is a
// process-wide singleton, so only the first test in a run to reach it executes
// its body — which test that is moves with execution order (see MilestoneTest).
#[CoversClass(User::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(Searchable::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(TaskStatus::class)]
final class UserCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
        $this->seedSecurityService(null);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);
        $this->seedSecurityService(null);

        parent::tearDown();
    }

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function seedSecurityService(?SecurityService $service): void
    {
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, $service);
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
     * Database mock whose executeQuery()/executeInsertUpdate() each replay
     * their own queue of steps in order (a PDOStatement/bool to return, or a
     * Throwable to throw), recording every call's SQL/params, repeating the
     * last queued step for calls beyond the list.
     */
    private function newDb(
        array $queryQueue = [],
        array $insertUpdateQueue = [],
        ?array &$queryCalls = null,
        ?array &$insertCalls = null
    ): Database {
        $queryCalls = [];
        $insertCalls = [];
        $db = $this->createMock(Database::class);

        $qi = 0;
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$qi, $queryQueue, &$queryCalls) {
                $queryCalls[] = ['sql' => $sql, 'params' => $params];
                $step = $queryQueue[$qi] ?? end($queryQueue);
                $qi++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        $ii = 0;
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$ii, $insertUpdateQueue, &$insertCalls) {
                $insertCalls[] = ['sql' => $sql, 'params' => $params];
                $step = $insertUpdateQueue[$ii] ?? end($insertUpdateQueue);
                $ii++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        return $db;
    }

    /**
     * A PDOStatement double for the getConnection()->prepare() code path:
     * accepts any bindValue()/execute() calls and returns $rows from
     * fetchAll().
     */
    private function preparedStatement(array $rows): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        return $stmt;
    }

    private function userRow(int $id, string $email = 'user@example.com'): object
    {
        return (object)[
            'id' => $id,
            'company_id' => null,
            'role_id' => 2,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $email,
            'is_active' => true,
        ];
    }

    // ------------------------------------------------------- findByEmail()/private getUsersWithDetails()

    public function testFindByEmailReturnsFirstMatchAndBuildsFilteredQuery(): void
    {
        $row = $this->userRow(1, 'ada@example.com');
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => [$row]])], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->findByEmail('ada@example.com');

        $this->assertSame($row, $result);
        $this->assertStringContainsString('u.email = :where_0', $calls[0]['sql']);
        $this->assertStringContainsString('u.is_deleted = 0', $calls[0]['sql']);
        $this->assertStringContainsString('ORDER BY u.first_name asc', $calls[0]['sql']);
        $this->assertSame('ada@example.com', $calls[0]['params'][':where_0']);
        $this->assertSame(1, $calls[0]['params'][':limit']);
    }

    public function testFindByEmailReturnsNullWhenNoMatch(): void
    {
        $db = $this->newDb([$this->statement(['fetchAll' => []])]);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertNull($model->findByEmail('missing@example.com'));
    }

    public function testFindByEmailWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find user by email:');

        $model->findByEmail('ada@example.com');
    }

    // --------------------------------------------------------------- getAllUsers()

    public function testGetAllUsersRequestsNoLimitAndCombinedSort(): void
    {
        $rows = [$this->userRow(1), $this->userRow(2)];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => $rows])], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->getAllUsers();

        $this->assertSame($rows, $result);
        $this->assertStringContainsString('ORDER BY u.first_name, u.last_name asc', $calls[0]['sql']);
        $this->assertArrayNotHasKey(':limit', $calls[0]['params']);
    }

    public function testGetAllUsersWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get all users:');

        $model->getAllUsers();
    }

    // ------------------------------------------------------- getRolesAndPermissions()

    public function testGetRolesAndPermissionsReturnsRoleAndPermissionNames(): void
    {
        $calls = [];
        $db = $this->newDb([
            $this->statement(['fetch' => ['role_name' => 'Admin']]),
            $this->statement(['fetchAll' => [
                ['permission_name' => 'view_tasks'],
                ['permission_name' => 'edit_tasks'],
            ]]),
        ], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->getRolesAndPermissions(5);

        $this->assertSame(['Admin'], $result['roles']);
        $this->assertSame(['view_tasks', 'edit_tasks'], $result['permissions']);
        $this->assertSame(5, $calls[0]['params'][':user_id']);
    }

    public function testGetRolesAndPermissionsReturnsEmptyRolesWhenUserHasNoRole(): void
    {
        $db = $this->newDb([
            $this->statement(['fetch' => false]),
            $this->statement(['fetchAll' => []]),
        ]);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->getRolesAndPermissions(5);

        $this->assertSame([], $result['roles']);
        $this->assertSame([], $result['permissions']);
    }

    public function testGetRolesAndPermissionsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get roles and permissions:');

        $model->getRolesAndPermissions(5);
    }

    // ------------------------------------------------------------- findWithDetails()

    public function testFindWithDetailsLoadsEverythingByDefault(): void
    {
        $userRow = $this->userRow(1);
        $queryCalls = [];
        $db = $this->newDb([
            $this->statement(['fetchAll' => [$userRow]]), // getUsersWithDetails
            $this->statement(['fetch' => ['role_name' => 'Admin']]), // getRolesAndPermissions: role
            $this->statement(['fetchAll' => [['permission_name' => 'view_tasks']]]), // permissions
            $this->statement(['fetchAll' => [(object)['id' => 99, 'status_name' => 'Open']]]), // active tasks
        ], [], $queryCalls);

        $projectRows = [(object)['id' => 10]];
        $companyRows = [(object)['id' => 20]];
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls(
            $this->preparedStatement($projectRows),
            $this->preparedStatement($companyRows)
        );
        $db->method('getConnection')->willReturn($pdo);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->findWithDetails(1);

        $this->assertSame($projectRows, $result->projects);
        $this->assertSame(['view_tasks'], $result->permissions);
        $this->assertSame($companyRows, $result->companies);
        $this->assertCount(1, $result->active_tasks);
    }

    public function testFindWithDetailsSkipsAllRelatedDataWhenOptionsDisabled(): void
    {
        $userRow = $this->userRow(1);
        $db = $this->newDb([$this->statement(['fetchAll' => [$userRow]])]);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->findWithDetails(1, [
            'projects' => false,
            'permissions' => false,
            'companies' => false,
            'active_tasks' => false,
        ]);

        $this->assertFalse(property_exists($result, 'projects'));
        $this->assertFalse(property_exists($result, 'permissions'));
        $this->assertFalse(property_exists($result, 'companies'));
        $this->assertFalse(property_exists($result, 'active_tasks'));
    }

    public function testFindWithDetailsReturnsNullWhenUserNotFound(): void
    {
        $db = $this->newDb([$this->statement(['fetchAll' => []])]);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertNull($model->findWithDetails(999));
    }

    /**
     * findWithDetails()'s catch block re-throws the RuntimeException built
     * from SecurityService::getSafeErrorMessage()'s return value inside its
     * own try, which is then immediately caught by the enclosing
     * catch(\Exception $securityException) and replaced with the generic
     * fallback message — the "safe" message never actually reaches the
     * caller. This mirrors the same documented quirk in MilestoneTest.
     */
    public function testFindWithDetailsWrapsExceptionUsingFallbackMessage(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('a safe message that is discarded');
        $this->seedSecurityService($security);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find user with details');

        $model->findWithDetails(1);
    }

    public function testFindBasicOmitsRelatedData(): void
    {
        $userRow = $this->userRow(1);
        $db = $this->newDb([$this->statement(['fetchAll' => [$userRow]])]);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->findBasic(1);

        $this->assertFalse(property_exists($result, 'projects'));
        $this->assertFalse(property_exists($result, 'permissions'));
        $this->assertFalse(property_exists($result, 'companies'));
        $this->assertFalse(property_exists($result, 'active_tasks'));
    }

    // -------------------------------------------------------------- getUserProjects()

    public function testGetUserProjectsBindsSameUserIdToAllThreePlaceholders(): void
    {
        $rows = [(object)['id' => 1, 'status_name' => 'Active']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->exactly(3))->method('bindValue')->with(
            $this->logicalOr(':owner_id', ':user_projects_id', ':tasks_user_id'),
            7,
            PDO::PARAM_INT
        );
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createMock(Database::class);
        $db->method('getConnection')->willReturn($pdo);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertSame($rows, $model->getUserProjects(7));
    }

    public function testGetUserProjectsWrapsPdoExceptionInRuntimeException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('connection lost'));

        $db = $this->createMock(Database::class);
        $db->method('getConnection')->willReturn($pdo);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get user projects:');

        $model->getUserProjects(7);
    }

    // ------------------------------------------------------------- getUserCompanies()

    public function testGetUserCompaniesReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'name' => 'Acme']];
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($this->preparedStatement($rows));

        $db = $this->createMock(Database::class);
        $db->method('getConnection')->willReturn($pdo);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertSame($rows, $model->getUserCompanies(7));
    }

    public function testGetUserCompaniesWrapsExceptionInRuntimeException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new RuntimeException('boom'));

        $db = $this->createMock(Database::class);
        $db->method('getConnection')->willReturn($pdo);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get user companies:');

        $model->getUserCompanies(7);
    }

    // ---------------------------------------------------------- getUserActiveTasks()

    public function testGetUserActiveTasksExcludesClosedAndCompletedStatuses(): void
    {
        $rows = [(object)['id' => 1, 'project_name' => 'Alpha']];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => $rows])], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->getUserActiveTasks(3, 10);

        $this->assertSame($rows, $result);
        $this->assertStringContainsString(
            'NOT IN (' . TaskStatus::CLOSED->value . ', ' . TaskStatus::COMPLETED->value . ')',
            $calls[0]['sql']
        );
        $this->assertSame(3, $calls[0]['params'][':user_id']);
        $this->assertSame(10, $calls[0]['params'][':limit']);
    }

    public function testGetUserActiveTasksDefaultsLimitToFive(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $model->getUserActiveTasks(3);

        $this->assertSame(5, $calls[0]['params'][':limit']);
    }

    public function testGetUserActiveTasksWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get user active tasks:');

        $model->getUserActiveTasks(3);
    }

    // ------------------------------------------------------- findByActivationToken()

    public function testFindByActivationTokenReturnsUser(): void
    {
        $row = $this->userRow(1);
        $calls = [];
        $db = $this->newDb([$this->statement(['fetch' => $row])], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->findByActivationToken('tok123');

        $this->assertSame($row, $result);
        $this->assertSame('tok123', $calls[0]['params'][':token']);
    }

    public function testFindByActivationTokenReturnsNullWhenExpiredOrMissing(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertNull($model->findByActivationToken('missing'));
    }

    public function testFindByActivationTokenWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find user by activation token:');

        $model->findByActivationToken('tok');
    }

    // ------------------------------------------------------------ findByResetToken()

    public function testFindByResetTokenReturnsUser(): void
    {
        $row = $this->userRow(1);
        $calls = [];
        $db = $this->newDb([$this->statement(['fetch' => $row])], [], $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->findByResetToken('tok456');

        $this->assertSame($row, $result);
        $this->assertSame('tok456', $calls[0]['params'][':token']);
    }

    public function testFindByResetTokenReturnsNullWhenExpiredOrMissing(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertNull($model->findByResetToken('missing'));
    }

    public function testFindByResetTokenWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find user by reset token:');

        $model->findByResetToken('tok');
    }

    // -------------------------------------------------- generatePasswordResetToken()

    public function testGeneratePasswordResetTokenReturnsHexTokenAndUsesOneHourWindow(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();
        $token = $model->generatePasswordResetToken(4);

        $this->assertSame(32, strlen($token));
        $this->assertTrue(ctype_xdigit($token));
        $this->assertStringContainsString('INTERVAL 1 HOUR', $calls[0]['sql']);
        $this->assertSame(4, $calls[0]['params'][':id']);
        $this->assertSame($token, $calls[0]['params'][':token']);
    }

    public function testGeneratePasswordResetTokenWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate reset token:');

        $model->generatePasswordResetToken(4);
    }

    // ---------------------------------------------------- generateActivationToken()

    public function testGenerateActivationTokenReturnsHexTokenAndUsesTwentyFourHourWindow(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();
        $token = $model->generateActivationToken(4);

        $this->assertSame(32, strlen($token));
        $this->assertTrue(ctype_xdigit($token));
        $this->assertStringContainsString('INTERVAL 24 HOUR', $calls[0]['sql']);
        $this->assertSame(4, $calls[0]['params'][':id']);
    }

    public function testGenerateActivationTokenWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate activation token:');

        $model->generateActivationToken(4);
    }

    // ----------------------------------------------------- clearPasswordResetToken()

    public function testClearPasswordResetTokenReturnsDatabaseResult(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertTrue($model->clearPasswordResetToken(4));
        $this->assertSame(4, $calls[0]['params'][':id']);
    }

    public function testClearPasswordResetTokenWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to clear reset token:');

        $model->clearPasswordResetToken(4);
    }

    // -------------------------------------------------------- clearActivationToken()

    public function testClearActivationTokenReturnsDatabaseResult(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [false], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertFalse($model->clearActivationToken(4));
    }

    public function testClearActivationTokenWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to clear activation token:');

        $model->clearActivationToken(4);
    }

    // -------------------------------------------------------------------- addCompany()

    public function testAddCompanyUsesDistinctPlaceholderForDuplicateUpdateClause(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->addCompany(4, 9);

        $this->assertTrue($result);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE company_id = :company_id_dup', $calls[0]['sql']);
        $this->assertSame(9, $calls[0]['params'][':company_id']);
        $this->assertSame(9, $calls[0]['params'][':company_id_dup']);
    }

    public function testAddCompanyWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add company association:');

        $model->addCompany(4, 9);
    }

    // ---------------------------------------------------------------- removeCompany()

    public function testRemoveCompanyDeletesAssociation(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->removeCompany(4, 9);

        $this->assertTrue($result);
        $this->assertSame(4, $calls[0]['params'][':user_id']);
        $this->assertSame(9, $calls[0]['params'][':company_id']);
    }

    public function testRemoveCompanyWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove company association:');

        $model->removeCompany(4, 9);
    }

    // ------------------------------------------------------------------- addProject()

    public function testAddProjectUsesDistinctPlaceholderForDuplicateUpdateClause(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->addProject(4, 11);

        $this->assertTrue($result);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE project_id = :project_id_dup', $calls[0]['sql']);
        $this->assertSame(11, $calls[0]['params'][':project_id']);
        $this->assertSame(11, $calls[0]['params'][':project_id_dup']);
    }

    public function testAddProjectWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add project assignment:');

        $model->addProject(4, 11);
    }

    // ---------------------------------------------------------------- removeProject()

    public function testRemoveProjectDeletesAssignment(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new User();
        $result = $model->removeProject(4, 11);

        $this->assertTrue($result);
        $this->assertSame(4, $calls[0]['params'][':user_id']);
        $this->assertSame(11, $calls[0]['params'][':project_id']);
    }

    public function testRemoveProjectWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new User();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove project assignment:');

        $model->removeProject(4, 11);
    }

    // --------------------------------------------------------------------- search index

    public function testSearchEntityTypeReturnsUser(): void
    {
        $model = new User();

        $this->assertSame('user', $model->searchEntityType());
    }

    public function testToSearchIndexRowReturnsProjectedFields(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => (object)[
            'id' => 1,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
        ]])]);
        $this->seedDatabase($db);

        $model = new User();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame('Ada Lovelace', $row[0]);
        $this->assertSame('ada@example.com', $row[1]);
        $this->assertNull($row[2]);
        $this->assertSame('Ada Lovelace ada@example.com', $row[3]);
    }

    public function testToSearchIndexRowHandlesMissingNameFields(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => (object)[
            'id' => 1,
            'email' => 'noname@example.com',
        ]])]);
        $this->seedDatabase($db);

        $model = new User();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame('', $row[0]);
        $this->assertSame('noname@example.com', $row[1]);
    }

    public function testToSearchIndexRowTruncatesLongEmail(): void
    {
        $email = str_repeat('a', 250) . '@example.com';
        $db = $this->newDb([$this->statement(['fetch' => (object)[
            'id' => 1,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $email,
        ]])]);
        $this->seedDatabase($db);

        $model = new User();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame(200, strlen($row[1]));
    }

    public function testToSearchIndexRowReturnsNullWhenUserNotFound(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new User();

        $this->assertNull($model->toSearchIndexRow(999));
    }
}
