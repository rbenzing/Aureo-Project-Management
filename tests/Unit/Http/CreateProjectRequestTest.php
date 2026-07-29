<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\ProjectStatus;
use App\Exceptions\ValidationException;
use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\FormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateProjectRequest::class)]
#[UsesClass(FormRequest::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(ProjectStatus::class)]
class CreateProjectRequestTest extends TestCase
{
    public function testValidatesSuccessfullyWithAllFields(): void
    {
        $request = new CreateProjectRequest([
            'name' => 'New Project',
            'description' => 'A brand new project',
            'key_code' => 'NP',
            'owner_id' => 5,
            'company_id' => 2,
            // The FormRequest 'in' rule compares strictly (in_array(..., true)) against
            // the string parameters parsed from the rule definition, so a numeric field
            // must be supplied as a matching string to pass.
            'status_id' => (string) ProjectStatus::READY->value,
            'start_date' => '2024-01-01',
            'due_date' => '2024-06-01',
            'budget' => 1000,
        ]);

        $validated = $request->validate();

        $this->assertSame('New Project', $validated['name']);
        $this->assertSame(5, $validated['owner_id']);
        $this->assertSame((string) ProjectStatus::READY->value, $validated['status_id']);
        $this->assertCount(9, $validated);
    }

    public function testMissingRequiredNameThrowsWithCustomMessage(): void
    {
        $request = new CreateProjectRequest([
            'owner_id' => 1,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Project name is required', $e->getErrors()['name'][0]);
        }
    }

    public function testMissingOwnerIdThrowsWithCustomMessage(): void
    {
        $request = new CreateProjectRequest([
            'name' => 'Valid Name',
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Project owner is required', $e->getErrors()['owner_id'][0]);
        }
    }

    public function testInvalidStatusIdFailsInRule(): void
    {
        $request = new CreateProjectRequest([
            'name' => 'Valid Name',
            'owner_id' => 1,
            'status_id' => 9999,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasError('status_id'));
            $this->assertStringContainsString('invalid', $e->getFieldErrors('status_id')[0]);
        }
    }

    public function testNegativeBudgetFailsWithCustomMessage(): void
    {
        $request = new CreateProjectRequest([
            'name' => 'Valid Name',
            'owner_id' => 1,
            'budget' => -50,
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Budget cannot be negative', $e->getErrors()['budget'][0]);
        }
    }

    public function testKeyCodeTooShortFailsWithCustomMessage(): void
    {
        $request = new CreateProjectRequest([
            'name' => 'Valid Name',
            'owner_id' => 1,
            'key_code' => 'A',
        ]);

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Key code must be at least 2 characters', $e->getErrors()['key_code'][0]);
        }
    }
}
