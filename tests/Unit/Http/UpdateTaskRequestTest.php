<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\TaskStatus;
use App\Exceptions\ValidationException;
use App\Http\Requests\FormRequest;
use App\Http\Requests\UpdateTaskRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTaskRequest::class)]
#[UsesClass(FormRequest::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(TaskStatus::class)]
class UpdateTaskRequestTest extends TestCase
{
    public function testValidatesSuccessfullyWithPartialFields(): void
    {
        $request = new UpdateTaskRequest([
            'title' => 'Updated title',
            'priority' => 2,
            'time_spent' => 10,
            'billable_time' => 5,
            'estimated_time' => 8,
        ]);

        $validated = $request->validate();

        $this->assertSame('Updated title', $validated['title']);
        $this->assertSame(2, $validated['priority']);
        $this->assertCount(5, $validated);
    }

    public function testAllFieldsAreOptionalAndEmptyDataIsValid(): void
    {
        $request = new UpdateTaskRequest([]);

        $validated = $request->validate();

        $this->assertSame([], $validated);
    }

    public function testTitleTooShortFailsWithCustomMessage(): void
    {
        $request = new UpdateTaskRequest([
            'title' => 'ab',
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Task title must be at least 3 characters', $e->getErrors()['title'][0]);
        }
    }

    public function testNegativeTimeSpentFailsWithCustomMessage(): void
    {
        $request = new UpdateTaskRequest([
            'time_spent' => -1,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Time spent cannot be negative', $e->getErrors()['time_spent'][0]);
        }
    }

    public function testNegativeBillableTimeFailsWithCustomMessage(): void
    {
        $request = new UpdateTaskRequest([
            'billable_time' => -1,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Billable time cannot be negative', $e->getErrors()['billable_time'][0]);
        }
    }

    public function testPriorityOutOfRangeFailsWithCustomMessage(): void
    {
        $request = new UpdateTaskRequest([
            'priority' => 0,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Priority must be between 1 (lowest) and 5 (highest)',
                $e->getErrors()['priority'][0]
            );
        }
    }
}
