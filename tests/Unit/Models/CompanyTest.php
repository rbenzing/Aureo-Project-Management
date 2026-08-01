<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Concerns\Searchable;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Company model: detail loading with selective
 * eager-loading options (including the id save/restore dance around
 * getProjects()), direct+junction-table user/project association
 * queries/mutations, and the search-index row projection.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * Company does not override create()/update(), so those paths (and the
 * Searchable::afterSave chain they'd trigger) are exercised by
 * BaseModelTest instead — this file only calls the methods Company.php
 * actually defines, plus the two Searchable contract methods it implements
 * directly (searchEntityType/toSearchIndexRow), mirroring ProjectTest's
 * documented scope split.
 *
 * A mocked Database cannot reject a malformed prepared statement, which is how
 * three statements here shipped reusing one named placeholder across several
 * positions — illegal under native prepares and guaranteed to throw "Invalid
 * parameter number" against real MySQL. Those are fixed;
 * assertPlaceholdersAreDistinctAndBound() now guards every raw statement in this
 * file so the class of defect cannot silently return.
 */
// Config is declared because Database consults Config::isProduction(). It is a
// process-wide singleton, so only the first test in a run to reach it executes
// its body — which test that is moves with execution order (see MilestoneTest).
#[CoversClass(Company::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(Searchable::class)]
final class CompanyTest extends TestCase
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
     * Assert a statement is executable under native prepares: no named
     * placeholder may appear twice, and every one must be bound.
     *
     * PDO::ATTR_EMULATE_PREPARES is false in App\Core\Database, so MySQL rejects a
     * repeated placeholder with "Invalid parameter number". A mocked Database
     * cannot catch that, which is exactly how three such statements shipped.
     */
    private function assertPlaceholdersAreDistinctAndBound(string $sql, array $params): void
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $sql, $matches);
        $placeholders = $matches[0];

        $this->assertSame(
            array_values(array_unique($placeholders)),
            $placeholders,
            'A named placeholder appears more than once: ' . implode(', ', $placeholders)
        );

        foreach ($placeholders as $placeholder) {
            $this->assertArrayHasKey(
                $placeholder,
                $params,
                "Placeholder {$placeholder} appears in the SQL but is never bound"
            );
        }
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

    // ------------------------------------------------------------ findWithDetails()

    public function testFindWithDetailsReturnsBasicCompanyByDefault(): void
    {
        $company = (object)['id' => 1, 'name' => 'Acme'];
        $db = $this->newDb([$this->statement(['fetchAll' => [$company]])]);
        $this->seedDatabase($db);

        $model = new Company();
        $result = $model->findWithDetails(1);

        $this->assertSame($company, $result);
        $this->assertFalse(property_exists($result, 'users'));
        $this->assertFalse(property_exists($result, 'projects'));
    }

    public function testFindWithDetailsLoadsUsersWhenRequested(): void
    {
        $company = (object)['id' => 1, 'name' => 'Acme'];
        $users = [(object)['id' => 5]];
        $db = $this->newDb([
            $this->statement(['fetchAll' => [$company]]),
            $this->statement(['fetchAll' => $users]), // getUsers: direct
            $this->statement(['fetchAll' => []]), // getUsers: indirect
        ]);
        $this->seedDatabase($db);

        $model = new Company();
        $result = $model->findWithDetails(1, ['users' => true]);

        $this->assertSame($users, $result->users);
    }

    public function testFindWithDetailsLoadsProjectsAndRestoresIdAfterward(): void
    {
        $company = (object)['id' => 1, 'name' => 'Acme'];
        $projects = [(object)['id' => 9]];
        $db = $this->newDb([
            $this->statement(['fetchAll' => [$company]]),
            $this->statement(['fetchAll' => $projects]), // getProjects: direct
            $this->statement(['fetchAll' => []]), // getProjects: indirect
        ]);
        $this->seedDatabase($db);

        $model = new Company();
        $originalId = $model->id;
        $result = $model->findWithDetails(1, ['projects' => true]);

        $this->assertSame($projects, $result->projects);
        // getProjects() temporarily sets $this->id = $id, then must restore it.
        $this->assertSame($originalId, $model->id);
    }

    public function testFindWithDetailsLoadsCountsWhenRequested(): void
    {
        $company = (object)['id' => 1, 'name' => 'Acme'];
        $counts = (object)['user_count' => 3, 'project_count' => 7];
        $calls = [];
        $db = $this->newDb([
            $this->statement(['fetchAll' => [$company]]),
            $this->statement(['fetch' => $counts]),
        ], [], $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $result = $model->findWithDetails(1, ['counts' => true]);

        $this->assertSame(3, $result->user_count);
        $this->assertSame(7, $result->project_count);
        $this->assertSame(1, $calls[1]['params'][':company_id']);
        $this->assertSame(1, $calls[1]['params'][':company_id4']);
    }

    public function testFindWithDetailsReturnsNullWhenCompanyNotFound(): void
    {
        $db = $this->newDb([$this->statement(['fetchAll' => []])]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->assertNull($model->findWithDetails(99));
    }

    public function testFindWithDetailsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to find company with details:');

        $model->findWithDetails(1);
    }

    public function testFindBasicOmitsRelatedData(): void
    {
        $company = (object)['id' => 1, 'name' => 'Acme'];
        $db = $this->newDb([$this->statement(['fetchAll' => [$company]])]);
        $this->seedDatabase($db);

        $model = new Company();
        $result = $model->findBasic(1);

        $this->assertFalse(property_exists($result, 'users'));
        $this->assertFalse(property_exists($result, 'projects'));
        $this->assertFalse(property_exists($result, 'user_count'));
    }

    // -------------------------------------------------------------------- getUsers()

    public function testGetUsersMergesDirectAndIndirectDeduplicatingById(): void
    {
        $direct = [(object)['id' => 1], (object)['id' => 2]];
        $indirect = [(object)['id' => 2], (object)['id' => 3]]; // id 2 overlaps
        $db = $this->newDb([
            $this->statement(['fetchAll' => $direct]),
            $this->statement(['fetchAll' => $indirect]),
        ]);
        $this->seedDatabase($db);

        $model = new Company();
        $result = $model->getUsers(1);

        $this->assertCount(3, $result);
        $this->assertSame([1, 2, 3], array_map(fn ($u) => $u->id, $result));
    }

    public function testGetUsersWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get company users:');

        $model->getUsers(1);
    }

    // ----------------------------------------------------------------- getProjects()

    public function testGetProjectsThrowsWhenIdNotSet(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get company projects: Company ID is not set');

        $model->getProjects();
    }

    public function testGetProjectsMergesDirectAndIndirectDeduplicatingById(): void
    {
        $direct = [(object)['id' => 1]];
        $indirect = [(object)['id' => 1], (object)['id' => 2]];
        $calls = [];
        $db = $this->newDb([
            $this->statement(['fetchAll' => $direct]),
            $this->statement(['fetchAll' => $indirect]),
        ], [], $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;
        $result = $model->getProjects();

        $this->assertCount(2, $result);
        $this->assertSame([1, 2], array_map(fn ($p) => $p->id, $result));
        $this->assertSame(4, $calls[0]['params'][':company_id']);
    }

    public function testGetProjectsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get company projects: boom');

        $model->getProjects();
    }

    // ------------------------------------------------------------- getAllCompanies()

    public function testGetAllCompaniesReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'name' => 'Acme']];
        $db = $this->newDb([$this->statement(['fetchAll' => $rows])]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->assertSame($rows, $model->getAllCompanies());
    }

    public function testGetAllCompaniesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get all companies:');

        $model->getAllCompanies();
    }

    // ------------------------------------------------------ getRecentProjectsByUser()

    public function testGetRecentProjectsByUserReturnsRows(): void
    {
        $rows = [(object)['id' => 1, 'company_name' => 'Acme']];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => $rows])], [], $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $result = $model->getRecentProjectsByUser(9);

        $this->assertSame($rows, $result);
        $this->assertSame(9, $calls[0]['params'][':user_id']);
    }

    /**
     * Regression: with PDO::ATTR_EMULATE_PREPARES = false the driver rejects a
     * named placeholder that appears more than once — "Invalid parameter number".
     * This SQL referenced :user_id three times while binding it once, so the query
     * could never execute against real MySQL; the mocked Database never noticed.
     * Asserting placeholder integrity directly is what catches that class of bug,
     * since substr_count(':user_id') also matches ':user_id_owner' and would pass
     * either way.
     */
    public function testGetRecentProjectsByUserBindsEveryPlaceholderExactlyOnce(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        (new Company())->getRecentProjectsByUser(9);

        $this->assertPlaceholdersAreDistinctAndBound($calls[0]['sql'], $calls[0]['params']);
    }

    public function testGetRecentProjectsByUserWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get recent projects:');

        $model->getRecentProjectsByUser(9);
    }

    // ------------------------------------------------------------------------ addUser()

    public function testAddUserThrowsWhenIdNotSet(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add user to company: Company ID is not set');

        $model->addUser(5);
    }

    public function testAddUserInsertsAssociation(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;
        $result = $model->addUser(5);

        $this->assertTrue($result);
        $this->assertSame(5, $calls[0]['params'][':user_id']);
        $this->assertSame(4, $calls[0]['params'][':company_id']);
        $this->assertPlaceholdersAreDistinctAndBound($calls[0]['sql'], $calls[0]['params']);
    }

    public function testAddUserWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add user to company: boom');

        $model->addUser(5);
    }

    // --------------------------------------------------------------------- removeUser()

    public function testRemoveUserThrowsWhenIdNotSet(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove user from company: Company ID is not set');

        $model->removeUser(5);
    }

    public function testRemoveUserDeletesAssociation(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;
        $result = $model->removeUser(5);

        $this->assertTrue($result);
        $this->assertSame(5, $calls[0]['params'][':user_id']);
        $this->assertSame(4, $calls[0]['params'][':company_id']);
    }

    public function testRemoveUserWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove user from company: boom');

        $model->removeUser(5);
    }

    // ---------------------------------------------------------------------- addProject()

    public function testAddProjectThrowsWhenIdNotSet(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add project to company: Company ID is not set');

        $model->addProject(6);
    }

    public function testAddProjectInsertsAssociation(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;
        $result = $model->addProject(6);

        $this->assertTrue($result);
        $this->assertSame(4, $calls[0]['params'][':company_id']);
        $this->assertSame(6, $calls[0]['params'][':project_id']);
        $this->assertPlaceholdersAreDistinctAndBound($calls[0]['sql'], $calls[0]['params']);
    }

    public function testAddProjectWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add project to company: boom');

        $model->addProject(6);
    }

    // ------------------------------------------------------------------- removeProject()

    public function testRemoveProjectThrowsWhenIdNotSet(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Company();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove project from company: Company ID is not set');

        $model->removeProject(6);
    }

    public function testRemoveProjectDeletesAssociation(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;
        $result = $model->removeProject(6);

        $this->assertTrue($result);
        $this->assertSame(4, $calls[0]['params'][':company_id']);
        $this->assertSame(6, $calls[0]['params'][':project_id']);
    }

    public function testRemoveProjectWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([], [new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Company();
        $model->id = 4;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove project from company: boom');

        $model->removeProject(6);
    }

    // --------------------------------------------------------------------- search index

    public function testSearchEntityTypeReturnsCompany(): void
    {
        $model = new Company();

        $this->assertSame('company', $model->searchEntityType());
    }

    public function testToSearchIndexRowReturnsProjectedFields(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => (object)[
            'id' => 1,
            'name' => 'Acme Corp',
            'email' => 'contact@acme.test',
            'address' => '123 Main St',
        ]])]);
        $this->seedDatabase($db);

        $model = new Company();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame('Acme Corp', $row[0]);
        $this->assertSame('contact@acme.test', $row[1]);
        $this->assertNull($row[2]);
        $this->assertSame('Acme Corp contact@acme.test 123 Main St', $row[3]);
    }

    public function testToSearchIndexRowTruncatesLongEmail(): void
    {
        $email = str_repeat('a', 250) . '@example.com';
        $db = $this->newDb([$this->statement(['fetch' => (object)[
            'id' => 1,
            'name' => 'Acme Corp',
            'email' => $email,
            'address' => null,
        ]])]);
        $this->seedDatabase($db);

        $model = new Company();
        $row = $model->toSearchIndexRow(1);

        $this->assertSame(200, strlen($row[1]));
    }

    public function testToSearchIndexRowReturnsNullWhenCompanyNotFound(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new Company();

        $this->assertNull($model->toSearchIndexRow(999));
    }
}
