<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\BaseModel;
use InvalidArgumentException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for BaseModel::queryBuilder — the shared SQL construction path behind
 * nearly every read in the application.
 *
 * Two failure modes matter here and both have bitten this codebase:
 *
 *  1. Omitting 'alias' while selecting through one, which points the soft-delete
 *     predicate at the bare table and produces "Unknown table 'x'".
 *  2. Reusing a named placeholder, which native prepares reject with
 *     "Invalid parameter number".
 *
 * The builder is exercised through a spy Database so the generated SQL can be
 * asserted without a live connection.
 */
#[CoversClass(BaseModel::class)]
final class BaseModelQueryBuilderTest extends TestCase
{
    private QueryBuilderProbe $model;

    /** @var array{sql: string, params: array} */
    private array $captured = ['sql' => '', 'params' => []];

    protected function setUp(): void
    {
        parent::setUp();

        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn([]);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use ($statement): PDOStatement {
                $this->captured = ['sql' => $sql, 'params' => $params];

                return $statement;
            }
        );

        // BaseModel::__construct calls Database::getInstance(), which needs a live
        // server. Bypass it and inject the spy directly.
        $reflection = new ReflectionClass(QueryBuilderProbe::class);
        $this->model = $reflection->newInstanceWithoutConstructor();

        // setAccessible() is unnecessary since PHP 8.1 and deprecated in 8.5.
        $reflection->getParentClass()->getProperty('db')->setValue($this->model, $db);
        $reflection->getParentClass()->getProperty('table')->setValue($this->model, 'users');
    }

    private function sql(): string
    {
        // Collapse whitespace so assertions are not brittle about spacing.
        return trim(preg_replace('/\s+/', ' ', $this->captured['sql']));
    }

    // ------------------------------------------------------- the alias footgun

    public function testWithoutAliasSoftDeleteTargetsTheBareTable(): void
    {
        $this->model->build([]);

        $this->assertStringContainsString('FROM users', $this->sql());
        $this->assertStringContainsString('users.is_deleted = 0', $this->sql());
    }

    public function testAliasIsAppliedToBothFromAndSoftDelete(): void
    {
        $this->model->build(['alias' => 'u']);

        $this->assertStringContainsString('FROM users u', $this->sql());
        $this->assertStringContainsString('u.is_deleted = 0', $this->sql());
        $this->assertStringNotContainsString(
            'users.is_deleted',
            $this->sql(),
            'With an alias the soft-delete predicate must not reference the bare table'
        );
    }

    public function testJoinWithAliasProducesResolvableSoftDeleteReference(): void
    {
        // The exact shape that fails with "Unknown table 'u'" when alias is omitted.
        $this->model->build([
            'select' => 'u.id, c.name',
            'alias' => 'u',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'companies c', 'on' => 'u.company_id = c.id'],
            ],
        ]);

        $sql = $this->sql();
        $this->assertStringContainsString('FROM users u', $sql);
        $this->assertStringContainsString('LEFT JOIN companies c ON u.company_id = c.id', $sql);
        $this->assertStringContainsString('u.is_deleted = 0', $sql);
    }

    public function testInvalidAliasIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid alias');

        $this->model->build(['alias' => 'u; DROP TABLE users--']);
    }

    // ------------------------------------------------ distinct placeholder rule

    public function testMultipleConditionsGetDistinctPlaceholders(): void
    {
        $this->model->build([
            'where' => [
                ['column' => 'company_id', 'value' => 1],
                ['column' => 'is_active', 'value' => 1],
            ],
        ]);

        $keys = array_keys($this->captured['params']);

        $this->assertSame(
            count($keys),
            count(array_unique($keys)),
            'Native prepares reject a placeholder reused across bindings'
        );
        $this->assertContains(':where_0', $keys);
        $this->assertContains(':where_1', $keys);
    }

    public function testInOperatorGeneratesOnePlaceholderPerElement(): void
    {
        $this->model->build([
            'where' => [
                ['column' => 'id', 'operator' => 'IN', 'value' => [4, 8, 15]],
            ],
        ]);

        $this->assertStringContainsString(
            'id IN (:where_0_0, :where_0_1, :where_0_2)',
            $this->sql()
        );
        $this->assertSame(
            [':where_0_0' => 4, ':where_0_1' => 8, ':where_0_2' => 15],
            $this->captured['params']
        );
    }

    public function testInOperatorRequiresAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IN operator requires array value');

        $this->model->build([
            'where' => [['column' => 'id', 'operator' => 'IN', 'value' => 7]],
        ]);
    }

    public function testNullOperatorsBindNoParameters(): void
    {
        $this->model->build([
            'where' => [['column' => 'deleted_at', 'operator' => 'IS NULL', 'value' => null]],
        ]);

        // 'value' => null means the condition is skipped entirely by the isset()
        // guard — documented so nobody expects an IS NULL clause here.
        $this->assertStringNotContainsString('deleted_at IS NULL', $this->sql());
        $this->assertSame([], $this->captured['params']);
    }

    // ------------------------------------------------------------- validation

    public function testInvalidColumnNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column name');

        $this->model->build([
            'where' => [['column' => 'id = 1 OR 1=1--', 'value' => 1]],
        ]);
    }

    public function testInvalidOperatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator');

        $this->model->build([
            'where' => [['column' => 'id', 'operator' => 'UNION SELECT', 'value' => 1]],
        ]);
    }

    public function testInvalidJoinTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JOIN type');

        $this->model->build([
            'joins' => [['type' => 'EVIL', 'table' => 'companies c', 'on' => '1=1']],
        ]);
    }

    public function testInvalidJoinTableIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');

        $this->model->build([
            'joins' => [['type' => 'LEFT', 'table' => 'companies; DROP TABLE users', 'on' => '1=1']],
        ]);
    }

    // ----------------------------------------------------------- soft deletes

    public function testIgnoreSoftDeleteOmitsThePredicate(): void
    {
        $this->model->build(['ignoreSoftDelete' => true]);

        $this->assertStringNotContainsString('is_deleted', $this->sql());
    }

    public function testModelsWithSoftDeletesDisabledOmitThePredicate(): void
    {
        (new ReflectionClass(QueryBuilderProbe::class))
            ->getParentClass()
            ->getProperty('usesSoftDeletes')
            ->setValue($this->model, false);

        $this->model->build([]);

        $this->assertStringNotContainsString('is_deleted', $this->sql());
    }

    // ------------------------------------------------------------ clause shape

    public function testOrderByReceivesTheKeywordPrefix(): void
    {
        // Task::buildOrderByClause() returns clauses WITHOUT "ORDER BY"; the
        // builder is what supplies the keyword. Both halves must stay in sync.
        $this->model->build(['orderBy' => 'created_at DESC']);

        $this->assertStringContainsString('ORDER BY created_at DESC', $this->sql());
        $this->assertStringNotContainsString('ORDER BY ORDER BY', $this->sql());
    }

    public function testGroupByReceivesTheKeywordPrefix(): void
    {
        $this->model->build(['groupBy' => 'company_id']);

        $this->assertStringContainsString('GROUP BY company_id', $this->sql());
    }

    public function testSelectListIsPassedThrough(): void
    {
        $this->model->build(['select' => 'id, email']);

        $this->assertStringStartsWith('SELECT id, email FROM users', $this->sql());
    }

    public function testLimitAndOffsetBindAsIntegers(): void
    {
        $this->model->build(['limit' => '25', 'offset' => '50']);

        $this->assertStringContainsString('LIMIT :limit', $this->sql());
        $this->assertStringContainsString('OFFSET :offset', $this->sql());
        $this->assertSame(25, $this->captured['params'][':limit']);
        $this->assertSame(50, $this->captured['params'][':offset']);
    }

    public function testOffsetIsIgnoredWithoutALimit(): void
    {
        // Documents real behavior: OFFSET is only emitted inside the LIMIT branch.
        $this->model->build(['offset' => 50]);

        $this->assertStringNotContainsString('OFFSET', $this->sql());
        $this->assertArrayNotHasKey(':offset', $this->captured['params']);
    }

    public function testWhereRawIsParenthesizedAndMergesItsParams(): void
    {
        $this->model->build([
            'whereRaw' => [
                ['sql' => 'first_name LIKE :term OR last_name LIKE :term_b',
                 'params' => [':term' => 'a%', ':term_b' => 'a%']],
            ],
        ]);

        $this->assertStringContainsString(
            '(first_name LIKE :term OR last_name LIKE :term_b)',
            $this->sql()
        );
        $this->assertSame('a%', $this->captured['params'][':term']);
        $this->assertSame('a%', $this->captured['params'][':term_b']);
    }

    public function testConditionsAreCombinedWithAnd(): void
    {
        $this->model->build([
            'where' => [
                ['column' => 'company_id', 'value' => 1],
                ['column' => 'is_active', 'value' => 1],
            ],
        ]);

        $sql = $this->sql();
        $this->assertStringContainsString('WHERE users.is_deleted = 0 AND', $sql);
        $this->assertStringContainsString('company_id = :where_0 AND is_active = :where_1', $sql);
    }

    public function testIncompleteConditionsAreSkipped(): void
    {
        $this->model->build([
            'where' => [
                ['column' => 'company_id'],           // no value
                ['value' => 5],                        // no column
                ['column' => 'is_active', 'value' => 1],
            ],
        ]);

        $this->assertSame([':where_2' => 1], $this->captured['params']);
    }
}

/**
 * Concrete BaseModel exposing the protected builder for assertion.
 */
final class QueryBuilderProbe extends BaseModel
{
    protected string $table = 'users';

    public function build(array $options = []): array
    {
        return $this->queryBuilder($options);
    }
}
