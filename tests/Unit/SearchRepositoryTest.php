<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\SearchIndex;
use App\Repositories\SearchRepository;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchRepositoryTest extends TestCase
{
    /** @var SearchIndex&MockObject */
    private SearchIndex $modelMock;

    /** @var Database&MockObject */
    private Database $dbMock;

    private SearchRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = $this->createMock(SearchIndex::class);
        $this->dbMock = $this->createMock(Database::class);

        $this->repository = new SearchRepository($this->modelMock, $this->dbMock);
    }

    // -------------------------------------------------------------------------
    // search() — routing to fullTextSearch vs prefixSearch
    // -------------------------------------------------------------------------

    public function testSearchWithQueryLengthGreaterThanOrEqualToThreeCallsFullTextSearch(): void
    {
        $expectedRows = [(object)['entity_type' => 'task', 'entity_id' => 1, 'title' => 'My Task']];

        $this->modelMock
            ->expects($this->once())
            ->method('fullTextSearch')
            ->with('foo', [], 30)
            ->willReturn($expectedRows);

        $this->modelMock
            ->expects($this->never())
            ->method('prefixSearch');

        $result = $this->repository->search('foo');

        $this->assertSame($expectedRows, $result);
    }

    public function testSearchWithQueryLengthLessThanThreeCallsPrefixSearch(): void
    {
        $expectedRows = [(object)['entity_type' => 'project', 'entity_id' => 5, 'title' => 'AB Project']];

        $this->modelMock
            ->expects($this->once())
            ->method('prefixSearch')
            ->with('AB', [], 30)
            ->willReturn($expectedRows);

        $this->modelMock
            ->expects($this->never())
            ->method('fullTextSearch');

        $result = $this->repository->search('AB');

        $this->assertSame($expectedRows, $result);
    }

    public function testSearchWithSingleCharQueryCallsPrefixSearch(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('prefixSearch')
            ->with('x', [], 30)
            ->willReturn([]);

        $this->modelMock->expects($this->never())->method('fullTextSearch');

        $result = $this->repository->search('x');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSearchWithEmptyQueryCallsPrefixSearch(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('prefixSearch')
            ->with('', [], 30)
            ->willReturn([]);

        $this->modelMock->expects($this->never())->method('fullTextSearch');

        $result = $this->repository->search('');

        $this->assertIsArray($result);
    }

    public function testSearchExactlyThreeCharsCallsFullTextSearch(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('fullTextSearch')
            ->with('bar', [], 30)
            ->willReturn([]);

        $this->modelMock->expects($this->never())->method('prefixSearch');

        $this->repository->search('bar');
    }

    public function testSearchForwardsEntityTypesAndLimit(): void
    {
        $types = ['task', 'project'];

        $this->modelMock
            ->expects($this->once())
            ->method('fullTextSearch')
            ->with('hello world', $types, 10)
            ->willReturn([]);

        $this->repository->search('hello world', $types, 10);
    }

    public function testSearchReturnsArrayFromModel(): void
    {
        $rows = [
            (object)['entity_type' => 'task', 'entity_id' => 1, 'title' => 'Alpha'],
            (object)['entity_type' => 'task', 'entity_id' => 2, 'title' => 'Beta'],
        ];

        $this->modelMock->method('fullTextSearch')->willReturn($rows);

        $result = $this->repository->search('test query');

        $this->assertCount(2, $result);
        $this->assertSame($rows[0], $result[0]);
        $this->assertSame($rows[1], $result[1]);
    }

    // -------------------------------------------------------------------------
    // logQuery()
    // -------------------------------------------------------------------------

    public function testLogQueryInsertsRowAndReturnsTrue(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeInsertUpdate')
            ->with(
                $this->stringContains('INSERT INTO `search_queries`'),
                $this->callback(function (array $params): bool {
                    return $params[':user_id'] === 42
                        && $params[':query'] === 'my search'
                        && $params[':result_count'] === 5
                        && $params[':took_ms'] === 12
                        && $params[':clicked_position'] === 2;
                })
            )
            ->willReturn(true);

        $result = $this->repository->logQuery(42, 'my search', 5, 12, 2);

        $this->assertTrue($result);
    }

    public function testLogQueryWithNullClickedPosition(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeInsertUpdate')
            ->with(
                $this->stringContains('INSERT INTO `search_queries`'),
                $this->callback(function (array $params): bool {
                    return $params[':clicked_position'] === null;
                })
            )
            ->willReturn(true);

        $result = $this->repository->logQuery(1, 'foo bar', 3, 8);

        $this->assertTrue($result);
    }

    public function testLogQueryReturnsFalseWhenInsertFails(): void
    {
        $this->dbMock
            ->method('executeInsertUpdate')
            ->willReturn(false);

        $result = $this->repository->logQuery(1, 'fail test', 0, 5);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // getRecentQueries()
    // -------------------------------------------------------------------------

    public function testGetRecentQueriesReturnsRowsForUser(): void
    {
        $rows = [
            (object)['id' => 10, 'user_id' => 7, 'query' => 'latest', 'created_at' => '2026-05-19 10:00:00'],
            (object)['id' => 9,  'user_id' => 7, 'query' => 'earlier', 'created_at' => '2026-05-18 09:00:00'],
        ];

        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('fetchAll')->with(PDO::FETCH_OBJ)->willReturn($rows);

        $this->dbMock
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('search_queries'),
                $this->callback(function (array $params): bool {
                    return $params[':user_id'] === 7 && $params[':limit'] === 10;
                })
            )
            ->willReturn($stmtMock);

        $result = $this->repository->getRecentQueries(7);

        $this->assertCount(2, $result);
        $this->assertSame($rows, $result);
    }

    public function testGetRecentQueriesRespectsCustomLimit(): void
    {
        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('fetchAll')->with(PDO::FETCH_OBJ)->willReturn([]);

        $this->dbMock
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    return $params[':limit'] === 5;
                })
            )
            ->willReturn($stmtMock);

        $result = $this->repository->getRecentQueries(3, 5);

        $this->assertIsArray($result);
    }

    public function testGetRecentQueriesOrdersByCreatedAtDesc(): void
    {
        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([]);

        $this->dbMock
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->matchesRegularExpression('/`?created_at`?\s+DESC/i'),
                $this->anything()
            )
            ->willReturn($stmtMock);

        $this->repository->getRecentQueries(1);
    }

    public function testGetRecentQueriesFiltersOnUserId(): void
    {
        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([]);

        $this->dbMock
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('user_id'),
                $this->callback(function (array $params): bool {
                    return isset($params[':user_id']) && $params[':user_id'] === 99;
                })
            )
            ->willReturn($stmtMock);

        $this->repository->getRecentQueries(99);
    }
}
