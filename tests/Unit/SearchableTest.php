<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Concerns\Searchable;
use App\Models\SearchIndex;
use PHPUnit\Framework\TestCase;

/**
 * A minimal host class that uses the trait, so we can test the trait in isolation
 * without a real model / database.
 */
final class SearchableHostStub
{
    use Searchable;

    /** @var array<int, array{string,string,?int,string}|null> */
    public array $rows = [];

    public function searchEntityType(): string
    {
        return 'stub';
    }

    public function toSearchIndexRow(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }
}

class SearchableTest extends TestCase
{
    /** @var SearchIndex&\PHPUnit\Framework\MockObject\MockObject */
    private SearchIndex $indexMock;
    private SearchableHostStub $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexMock = $this->createMock(SearchIndex::class);
        $this->host = new SearchableHostStub();
        $this->host->setSearchIndex($this->indexMock);
    }

    public function testAfterSaveUpsertsMappedRow(): void
    {
        $this->host->rows[7] = ['Acme Corp', 'acme@x.com', null, 'Acme Corp acme@x.com 1 Main St'];

        $this->indexMock
            ->expects($this->once())
            ->method('upsert')
            ->with('stub', 7, 'Acme Corp', 'acme@x.com', null, 'Acme Corp acme@x.com 1 Main St', false)
            ->willReturn(true);

        $this->host->afterSave(7);
    }

    public function testAfterSaveSkipsWhenRowIsNull(): void
    {
        $this->indexMock->expects($this->never())->method('upsert');
        $this->host->afterSave(404); // no row registered
    }

    public function testAfterDeleteMarksDeleted(): void
    {
        $this->indexMock
            ->expects($this->once())
            ->method('markDeleted')
            ->with('stub', 9)
            ->willReturn(true);

        $this->host->afterDelete(9);
    }

    public function testAfterSaveSwallowsIndexExceptions(): void
    {
        $this->host->rows[1] = ['t', 's', null, 't s'];
        $this->indexMock->method('upsert')->willThrowException(new \RuntimeException('db down'));

        // Must not throw.
        $this->host->afterSave(1);
        $this->assertTrue(true);
    }
}
