<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\SprintStatus;
use App\Exceptions\ValidationException;
use App\Http\Requests\CreateSprintRequest;
use App\Http\Requests\FormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSprintRequest::class)]
#[UsesClass(FormRequest::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(SprintStatus::class)]
class CreateSprintRequestTest extends TestCase
{
    public function testValidatesSuccessfullyWithAllFields(): void
    {
        $request = new CreateSprintRequest([
            'name' => 'Sprint 1',
            'description' => 'First sprint',
            'project_id' => 1,
            // The FormRequest 'in' rule compares strictly against the string
            // parameters parsed from the rule definition, so the value must be a
            // matching string, not an int, to pass.
            'status_id' => (string) SprintStatus::PLANNING->value,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-14',
            'goal' => 'Ship the feature',
            'capacity_hours' => 40,
            'capacity_story_points' => 20,
        ]);

        $validated = $request->validate();

        $this->assertSame('Sprint 1', $validated['name']);
        $this->assertSame(1, $validated['project_id']);
        $this->assertCount(9, $validated);
    }

    public function testMissingProjectIdThrowsWithCustomMessage(): void
    {
        $request = new CreateSprintRequest([
            'name' => 'Sprint 1',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-14',
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Project is required', $e->getErrors()['project_id'][0]);
        }
    }

    public function testMissingStartAndEndDateThrowWithCustomMessages(): void
    {
        $request = new CreateSprintRequest([
            'name' => 'Sprint 1',
            'project_id' => 1,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Start date is required', $e->getErrors()['start_date'][0]);
            $this->assertSame('End date is required', $e->getErrors()['end_date'][0]);
        }
    }

    public function testNegativeCapacityHoursFailsWithCustomMessage(): void
    {
        $request = new CreateSprintRequest([
            'name' => 'Sprint 1',
            'project_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-14',
            'capacity_hours' => -5,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Capacity hours cannot be negative', $e->getErrors()['capacity_hours'][0]);
        }
    }

    public function testNegativeCapacityStoryPointsFailsWithCustomMessage(): void
    {
        $request = new CreateSprintRequest([
            'name' => 'Sprint 1',
            'project_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-14',
            'capacity_story_points' => -3,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Capacity story points cannot be negative', $e->getErrors()['capacity_story_points'][0]);
        }
    }

    public function testNameTooShortFailsWithCustomMessage(): void
    {
        $request = new CreateSprintRequest([
            'name' => 'AB',
            'project_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-14',
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Sprint name must be at least 3 characters', $e->getErrors()['name'][0]);
        }
    }
}
