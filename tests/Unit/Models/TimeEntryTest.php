<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeEntry::class)]
final class TimeEntryTest extends TestCase
{
    /**
     * time_entries has no is_deleted column, so this is the first model in the
     * project that hard-deletes. If usesSoftDeletes were left at its inherited
     * true, every find() would emit "AND is_deleted = 0" against a column that
     * does not exist and fail at runtime.
     */
    public function testDoesNotUseSoftDeletes(): void
    {
        $reflection = new \ReflectionClass(TimeEntry::class);
        $property = $reflection->getProperty('usesSoftDeletes');

        $this->assertFalse($property->getDefaultValue());
    }

    public function testTableIsTimeEntries(): void
    {
        $reflection = new \ReflectionClass(TimeEntry::class);

        $this->assertSame('time_entries', $reflection->getProperty('table')->getDefaultValue());
    }

    /**
     * Mass assignment must not reach id, created_at or updated_at.
     */
    public function testFillableCoversExactlyTheEditableColumns(): void
    {
        $reflection = new \ReflectionClass(TimeEntry::class);

        $this->assertSame(
            ['task_id', 'user_id', 'start_time', 'end_time', 'duration', 'notes', 'is_billable'],
            $reflection->getProperty('fillable')->getDefaultValue()
        );
    }
}
