<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\Sort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for the Sort utility: URL generation, indicator markup,
 * and object sorting across every branch of sortObjects().
 */
#[CoversClass(Sort::class)]
final class SortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    public function testGetUrlTogglesToDescWhenSwitchingFields(): void
    {
        $url = Sort::getUrl('name', 'title', 'asc');

        $this->assertStringStartsWith('?', $url);
        parse_str(ltrim($url, '?'), $parsed);
        $this->assertSame('name', $parsed['sort']);
        $this->assertSame('desc', $parsed['dir']);
    }

    public function testGetUrlTogglesToAscWhenSameFieldCurrentlyDesc(): void
    {
        $url = Sort::getUrl('name', 'name', 'desc');

        parse_str(ltrim($url, '?'), $parsed);
        $this->assertSame('asc', $parsed['dir']);
    }

    public function testGetUrlTogglesToDescWhenSameFieldCurrentlyAsc(): void
    {
        $url = Sort::getUrl('name', 'name', 'asc');

        parse_str(ltrim($url, '?'), $parsed);
        $this->assertSame('desc', $parsed['dir']);
    }

    public function testGetUrlPreservesExistingGetParameters(): void
    {
        $_GET = ['page' => '2', 'filter' => 'active'];

        $url = Sort::getUrl('name', 'title', 'asc');

        parse_str(ltrim($url, '?'), $parsed);
        $this->assertSame('2', $parsed['page']);
        $this->assertSame('active', $parsed['filter']);
        $this->assertSame('name', $parsed['sort']);
    }

    public function testGetUrlUsesCustomParameterNames(): void
    {
        $url = Sort::getUrl('name', 'title', 'asc', 'order_by', 'order_dir');

        parse_str(ltrim($url, '?'), $parsed);
        $this->assertArrayHasKey('order_by', $parsed);
        $this->assertArrayHasKey('order_dir', $parsed);
        $this->assertSame('name', $parsed['order_by']);
        $this->assertSame('desc', $parsed['order_dir']);
    }

    public function testGetIndicatorReturnsNeutralIconForNonMatchingField(): void
    {
        $html = Sort::getIndicator('name', 'title', 'asc');

        $this->assertStringContainsString('text-gray-400', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function testGetIndicatorReturnsAscendingIconWhenAscending(): void
    {
        $html = Sort::getIndicator('name', 'name', 'asc');

        $this->assertStringContainsString('M14.707 12.707', $html);
    }

    public function testGetIndicatorReturnsDescendingIconWhenDescending(): void
    {
        $html = Sort::getIndicator('name', 'name', 'desc');

        $this->assertStringContainsString('M5.293 7.293', $html);
    }

    public function testSortObjectsByPriorityAscendingOrdersNoneLowMediumHigh(): void
    {
        $items = [
            (object)['id' => 1, 'priority' => 'high'],
            (object)['id' => 2, 'priority' => 'low'],
            (object)['id' => 3, 'priority' => 'none'],
            (object)['id' => 4, 'priority' => 'medium'],
        ];

        $sorted = Sort::sortObjects($items, 'priority', 'asc');

        $this->assertSame([3, 2, 4, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsByPriorityDescendingOrdersHighFirst(): void
    {
        $items = [
            (object)['id' => 1, 'priority' => 'low'],
            (object)['id' => 2, 'priority' => 'high'],
        ];

        $sorted = Sort::sortObjects($items, 'priority', 'desc');

        $this->assertSame([2, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsByPriorityTreatsMissingPriorityAsNone(): void
    {
        $items = [
            (object)['id' => 1],
            (object)['id' => 2, 'priority' => 'low'],
        ];

        $sorted = Sort::sortObjects($items, 'priority', 'asc');

        $this->assertSame(1, $sorted[0]->id);
    }

    public function testSortObjectsByDueDatePutsNullDatesLast(): void
    {
        $items = [
            (object)['id' => 1, 'due_date' => null],
            (object)['id' => 2, 'due_date' => '2026-01-01'],
            (object)['id' => 3, 'due_date' => '2025-06-15'],
        ];

        $sorted = Sort::sortObjects($items, 'due_date', 'asc');

        $this->assertSame([3, 2, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsByAssignedToUsesFullNameWhenAvailable(): void
    {
        $items = [
            (object)['id' => 1, 'first_name' => 'Zed', 'last_name' => 'Zephyr'],
            (object)['id' => 2, 'first_name' => 'Amy', 'last_name' => 'Adams'],
        ];

        $sorted = Sort::sortObjects($items, 'assigned_to', 'asc');

        $this->assertSame([2, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsByAssignedToFallsBackToRawId(): void
    {
        $items = [
            (object)['id' => 1, 'assigned_to' => 'user-b'],
            (object)['id' => 2, 'assigned_to' => 'user-a'],
        ];

        $sorted = Sort::sortObjects($items, 'assigned_to', 'asc');

        $this->assertSame([2, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsDefaultCaseIsCaseInsensitiveAscending(): void
    {
        $items = [
            (object)['id' => 1, 'title' => 'Zebra'],
            (object)['id' => 2, 'title' => 'apple'],
            (object)['id' => 3, 'title' => 'Mango'],
        ];

        $sorted = Sort::sortObjects($items, 'title', 'asc');

        $this->assertSame([2, 3, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsDefaultCaseDescending(): void
    {
        $items = [
            (object)['id' => 1, 'title' => 'apple'],
            (object)['id' => 2, 'title' => 'Zebra'],
        ];

        $sorted = Sort::sortObjects($items, 'title', 'desc');

        $this->assertSame([2, 1], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsDefaultCaseHandlesEqualValuesWithoutError(): void
    {
        $items = [
            (object)['id' => 1, 'title' => 'same'],
            (object)['id' => 2, 'title' => 'same'],
        ];

        $sorted = Sort::sortObjects($items, 'title', 'asc');

        $this->assertCount(2, $sorted);
        $this->assertEqualsCanonicalizing([1, 2], array_map(fn ($item) => $item->id, $sorted));
    }

    public function testSortObjectsDefaultCaseHandlesMissingField(): void
    {
        $items = [
            (object)['id' => 1],
            (object)['id' => 2, 'title' => 'value'],
        ];

        $sorted = Sort::sortObjects($items, 'title', 'asc');

        $this->assertCount(2, $sorted);
    }
}
