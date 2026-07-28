<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SearchRepository;
use App\Services\SearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchService::class)]
class SearchServiceTest extends TestCase
{
    /** @var SearchRepository&MockObject */
    private SearchRepository $repositoryMock;

    private SearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = $this->createMock(SearchRepository::class);
        $this->service = new SearchService($this->repositoryMock);
    }

    // -------------------------------------------------------------------------
    // search() — non-empty query
    // -------------------------------------------------------------------------

    public function testSearchWithNonEmptyQueryCallsRepositorySearch(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('search')
            ->with('my task', [], 30)
            ->willReturn([]);

        $this->repositoryMock
            ->method('logQuery')
            ->willReturn(true);

        $this->service->search('my task', 1);
    }

    public function testSearchWithNonEmptyQueryCallsLogQuery(): void
    {
        $rows = [(object)['entity_type' => 'task', 'entity_id' => 1, 'title' => 'Alpha']];

        $this->repositoryMock
            ->method('search')
            ->willReturn($rows);

        $this->repositoryMock
            ->expects($this->once())
            ->method('logQuery')
            ->with(
                42,
                'my task',
                1,
                $this->isType('int'),
                null
            )
            ->willReturn(true);

        $this->service->search('my task', 42);
    }

    public function testSearchReturnShapeHasRequiredKeys(): void
    {
        $this->repositoryMock->method('search')->willReturn([]);
        $this->repositoryMock->method('logQuery')->willReturn(true);

        $result = $this->service->search('hello', 1);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('took_ms', $result);
        $this->assertArrayHasKey('count', $result);
    }

    public function testSearchReturnQueryMatchesTrimmedInput(): void
    {
        $this->repositoryMock->method('search')->willReturn([]);
        $this->repositoryMock->method('logQuery')->willReturn(true);

        $result = $this->service->search('  hello  ', 1);

        $this->assertSame('hello', $result['query']);
    }

    public function testSearchReturnCountMatchesResultCount(): void
    {
        $rows = [
            (object)['entity_type' => 'task', 'entity_id' => 1, 'title' => 'Alpha'],
            (object)['entity_type' => 'task', 'entity_id' => 2, 'title' => 'Beta'],
            (object)['entity_type' => 'project', 'entity_id' => 3, 'title' => 'Gamma'],
        ];

        $this->repositoryMock->method('search')->willReturn($rows);
        $this->repositoryMock->method('logQuery')->willReturn(true);

        $result = $this->service->search('test', 1);

        $this->assertSame(3, $result['count']);
        $this->assertCount(3, $result['results']);
    }

    public function testSearchReturnResultsMatchRepositoryOutput(): void
    {
        $rows = [(object)['entity_type' => 'task', 'entity_id' => 7, 'title' => 'My Task']];

        $this->repositoryMock->method('search')->willReturn($rows);
        $this->repositoryMock->method('logQuery')->willReturn(true);

        $result = $this->service->search('my task', 1);

        $this->assertSame($rows, $result['results']);
    }

    public function testSearchTookMsIsNonNegativeInteger(): void
    {
        $this->repositoryMock->method('search')->willReturn([]);
        $this->repositoryMock->method('logQuery')->willReturn(true);

        $result = $this->service->search('test', 1);

        $this->assertIsInt($result['took_ms']);
        $this->assertGreaterThanOrEqual(0, $result['took_ms']);
    }

    public function testSearchForwardsEntityTypesAndLimitToRepository(): void
    {
        $types = ['task', 'project'];

        $this->repositoryMock
            ->expects($this->once())
            ->method('search')
            ->with('hello', $types, 10)
            ->willReturn([]);

        $this->repositoryMock->method('logQuery')->willReturn(true);

        $this->service->search('hello', 1, $types, 10);
    }

    // -------------------------------------------------------------------------
    // search() — whitespace-only / empty query
    // -------------------------------------------------------------------------

    public function testSearchWithWhitespaceOnlyQueryReturnsEmptyResultsWithoutCallingRepository(): void
    {
        $this->repositoryMock
            ->expects($this->never())
            ->method('search');

        $this->repositoryMock
            ->expects($this->never())
            ->method('logQuery');

        $result = $this->service->search('   ', 1);

        $this->assertSame([], $result['results']);
        $this->assertSame(0, $result['count']);
    }

    public function testSearchWithEmptyStringReturnsEmptyResultsWithoutCallingRepository(): void
    {
        $this->repositoryMock->expects($this->never())->method('search');
        $this->repositoryMock->expects($this->never())->method('logQuery');

        $result = $this->service->search('', 1);

        $this->assertArrayHasKey('results', $result);
        $this->assertSame([], $result['results']);
        $this->assertSame(0, $result['count']);
    }

    public function testSearchWithWhitespaceOnlyQueryReturnShapeIsComplete(): void
    {
        $this->repositoryMock->expects($this->never())->method('search');

        $result = $this->service->search('  ', 1);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('took_ms', $result);
        $this->assertArrayHasKey('count', $result);
    }

    // -------------------------------------------------------------------------
    // getRecentQueries()
    // -------------------------------------------------------------------------

    public function testGetRecentQueriesDelegatesToRepository(): void
    {
        $rows = [
            (object)['id' => 1, 'query' => 'foo'],
            (object)['id' => 2, 'query' => 'bar'],
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('getRecentQueries')
            ->with(7, 10)
            ->willReturn($rows);

        $result = $this->service->getRecentQueries(7);

        $this->assertSame($rows, $result);
    }

    public function testGetRecentQueriesForwardsCustomLimit(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('getRecentQueries')
            ->with(3, 5)
            ->willReturn([]);

        $result = $this->service->getRecentQueries(3, 5);

        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // recordClick()
    // -------------------------------------------------------------------------

    public function testRecordClickDelegatesToRepositoryLogQueryWithPosition(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('logQuery')
            ->with(42, 'my search', 0, 0, 3)
            ->willReturn(true);

        $result = $this->service->recordClick(42, 'my search', 3);

        $this->assertTrue($result);
    }

    public function testRecordClickReturnsFalseWhenRepositoryFails(): void
    {
        $this->repositoryMock
            ->method('logQuery')
            ->willReturn(false);

        $result = $this->service->recordClick(1, 'query', 1);

        $this->assertFalse($result);
    }

    public function testRecordClickForwardsPositionArgCorrectly(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('logQuery')
            ->with(
                $this->anything(),
                $this->anything(),
                0,
                0,
                7
            )
            ->willReturn(true);

        $this->service->recordClick(1, 'test', 7);
    }
}
