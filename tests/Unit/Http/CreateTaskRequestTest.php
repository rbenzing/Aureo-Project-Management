<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\TaskStatus;
use App\Exceptions\ValidationException;
use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\FormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateTaskRequest::class)]
#[UsesClass(FormRequest::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(TaskStatus::class)]
class CreateTaskRequestTest extends TestCase
{
    public function testValidatesSuccessfullyWithAllFields(): void
    {
        $request = new CreateTaskRequest([
            'title' => 'Fix login bug',
            'description' => 'Users cannot log in',
            'project_id' => 1,
            'assigned_to' => 2,
            // The FormRequest 'in' rule compares strictly against the string
            // parameters parsed from the rule definition, so the value must be a
            // matching string, not an int, to pass.
            'status_id' => (string) TaskStatus::OPEN->value,
            'priority' => 3,
            'due_date' => '2024-02-01',
            'estimated_time' => 5,
            'parent_task_id' => 9,
            'is_subtask' => false,
        ]);

        $validated = $request->validate();

        $this->assertSame('Fix login bug', $validated['title']);
        $this->assertSame(1, $validated['project_id']);
        $this->assertCount(10, $validated);
    }

    public function testMissingTitleThrowsWithCustomMessage(): void
    {
        $request = new CreateTaskRequest([
            'project_id' => 1,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Task title is required', $e->getErrors()['title'][0]);
        }
    }

    public function testMissingProjectIdThrowsWithCustomMessage(): void
    {
        $request = new CreateTaskRequest([
            'title' => 'Fix login bug',
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Project is required', $e->getErrors()['project_id'][0]);
        }
    }

    public function testPriorityOutOfRangeFailsWithCustomMessage(): void
    {
        $request = new CreateTaskRequest([
            'title' => 'Fix login bug',
            'project_id' => 1,
            'priority' => 9,
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

    public function testNegativeEstimatedTimeFailsWithCustomMessage(): void
    {
        $request = new CreateTaskRequest([
            'title' => 'Fix login bug',
            'project_id' => 1,
            'estimated_time' => -10,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Estimated time cannot be negative', $e->getErrors()['estimated_time'][0]);
        }
    }

    public function testTitleTooShortFailsWithCustomMessage(): void
    {
        $request = new CreateTaskRequest([
            'title' => 'Fi',
            'project_id' => 1,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Task title must be at least 3 characters', $e->getErrors()['title'][0]);
        }
    }
}
