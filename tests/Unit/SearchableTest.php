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

    public function testTaskMapsToIndexRow(): void
    {
        $task = $this->getMockBuilder(\App\Models\Task::class)
            ->onlyMethods(['find'])
            ->getMock();

        $row = new \stdClass();
        $row->title = 'Fix login';
        $row->description = str_repeat('z', 300);
        $row->project_id = 4;
        $task->method('find')->with(11)->willReturn($row);

        $mapped = $task->toSearchIndexRow(11);

        $this->assertSame('Fix login', $mapped[0]);
        $this->assertSame(str_repeat('z', 200), $mapped[1]);
        $this->assertSame(4, $mapped[2]);
        $this->assertSame('Fix login ' . str_repeat('z', 300), $mapped[3]);
        $this->assertSame('task', $task->searchEntityType());
    }

    public function testTaskMapReturnsNullWhenNotFound(): void
    {
        $task = $this->getMockBuilder(\App\Models\Task::class)
            ->onlyMethods(['find'])
            ->getMock();
        $task->method('find')->willReturn(false);

        $this->assertNull($task->toSearchIndexRow(999));
    }

    public function testProjectMapsToIndexRowUsingOwnIdAsProjectId(): void
    {
        $project = $this->getMockBuilder(\App\Models\Project::class)
            ->onlyMethods(['find'])
            ->getMock();

        $row = new \stdClass();
        $row->name = 'Aureo';
        $row->description = 'PM app';
        $project->method('find')->with(10)->willReturn($row);

        $mapped = $project->toSearchIndexRow(10);

        $this->assertSame('Aureo', $mapped[0]);
        $this->assertSame('PM app', $mapped[1]);
        $this->assertSame(10, $mapped[2]); // own id
        $this->assertSame('Aureo PM app', $mapped[3]);
        $this->assertSame('project', $project->searchEntityType());
    }

    public function testCompanyMapsNameEmailAddressNoProject(): void
    {
        $company = $this->getMockBuilder(\App\Models\Company::class)
            ->onlyMethods(['find'])->getMock();
        $row = new \stdClass();
        $row->name = 'Acme Corp';
        $row->email = 'hi@acme.com';
        $row->address = '1 Main St';
        $company->method('find')->with(3)->willReturn($row);

        $mapped = $company->toSearchIndexRow(3);

        $this->assertSame('Acme Corp', $mapped[0]);
        $this->assertSame('hi@acme.com', $mapped[1]);
        $this->assertNull($mapped[2]);
        $this->assertSame('Acme Corp hi@acme.com 1 Main St', $mapped[3]);
        $this->assertSame('company', $company->searchEntityType());
    }

    public function testUserMapsFullNameAndEmail(): void
    {
        $user = $this->getMockBuilder(\App\Models\User::class)
            ->onlyMethods(['find'])->getMock();
        $row = new \stdClass();
        $row->first_name = 'Ada';
        $row->last_name = 'Lovelace';
        $row->email = 'ada@x.com';
        $user->method('find')->willReturn($row);

        $mapped = $user->toSearchIndexRow(1);

        $this->assertSame('Ada Lovelace', $mapped[0]);
        $this->assertSame('ada@x.com', $mapped[1]);
        $this->assertNull($mapped[2]);
        $this->assertSame('Ada Lovelace ada@x.com', $mapped[3]);
        $this->assertSame('user', $user->searchEntityType());
    }

    public function testSprintMapsNameGoalProject(): void
    {
        $sprint = $this->getMockBuilder(\App\Models\Sprint::class)
            ->onlyMethods(['find'])->getMock();
        $row = new \stdClass();
        $row->name = 'Sprint 5';
        $row->sprint_goal = 'Ship search';
        $row->project_id = 8;
        $sprint->method('find')->willReturn($row);

        $mapped = $sprint->toSearchIndexRow(1);

        $this->assertSame('Sprint 5', $mapped[0]);
        $this->assertSame('Ship search', $mapped[1]);
        $this->assertSame(8, $mapped[2]);
        $this->assertSame('Sprint 5 Ship search', $mapped[3]);
        $this->assertSame('sprint', $sprint->searchEntityType());
    }

    public function testMilestoneMapsTitleDescriptionProject(): void
    {
        $ms = $this->getMockBuilder(\App\Models\Milestone::class)
            ->onlyMethods(['find'])->getMock();
        $row = new \stdClass();
        $row->title = 'v1.0';
        $row->description = 'First release';
        $row->project_id = 2;
        $ms->method('find')->willReturn($row);

        $mapped = $ms->toSearchIndexRow(1);

        $this->assertSame('v1.0', $mapped[0]);
        $this->assertSame('First release', $mapped[1]);
        $this->assertSame(2, $mapped[2]);
        $this->assertSame('v1.0 First release', $mapped[3]);
        $this->assertSame('milestone', $ms->searchEntityType());
    }
}
