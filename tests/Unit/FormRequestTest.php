<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Http\Requests\FormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormRequest::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(AuthorizationException::class)]
class FormRequestTest extends TestCase
{
    public function testValidateRequired(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals(['name' => 'John'], $validated);
    }

    public function testValidateRequiredFailsForEmpty(): void
    {
        $request = new class (['name' => '']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateRequiredFailsForNull(): void
    {
        $request = new class ([]) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateString(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['string']];
            }
        };

        $validated = $request->validate();
        $this->assertIsString($validated['name']);
    }

    public function testValidateInteger(): void
    {
        $request = new class (['age' => '25']) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['integer']];
            }
        };

        $validated = $request->validate();
        $this->assertTrue(isset($validated['age']));
    }

    public function testValidateIntegerFailsForString(): void
    {
        $request = new class (['age' => 'abc']) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['integer']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateNumeric(): void
    {
        $request = new class (['price' => '19.99']) extends FormRequest {
            protected function rules(): array
            {
                return ['price' => ['numeric']];
            }
        };

        $validated = $request->validate();
        $this->assertTrue(is_numeric($validated['price']));
    }

    public function testValidateEmail(): void
    {
        $request = new class (['email' => 'test@example.com']) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['email']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals('test@example.com', $validated['email']);
    }

    public function testValidateEmailFailsForInvalid(): void
    {
        $request = new class (['email' => 'invalid-email']) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['email']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateUrl(): void
    {
        $request = new class (['website' => 'https://example.com']) extends FormRequest {
            protected function rules(): array
            {
                return ['website' => ['url']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals('https://example.com', $validated['website']);
    }

    public function testValidateUrlFailsForInvalid(): void
    {
        $request = new class (['website' => 'not-a-url']) extends FormRequest {
            protected function rules(): array
            {
                return ['website' => ['url']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateMinString(): void
    {
        $request = new class (['password' => 'abc12345']) extends FormRequest {
            protected function rules(): array
            {
                return ['password' => ['min:8']];
            }
        };

        $validated = $request->validate();
        $this->assertTrue(strlen($validated['password']) >= 8);
    }

    public function testValidateMinStringFailsTooShort(): void
    {
        $request = new class (['password' => 'abc']) extends FormRequest {
            protected function rules(): array
            {
                return ['password' => ['min:8']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateMaxString(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['max:10']];
            }
        };

        $validated = $request->validate();
        $this->assertTrue(strlen($validated['name']) <= 10);
    }

    public function testValidateMaxStringFailsTooLong(): void
    {
        $request = new class (['name' => 'VeryLongNameThatExceedsTheLimit']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['max:10']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateBetween(): void
    {
        $request = new class (['age' => 25]) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['between:18,65']];
            }
        };

        $validated = $request->validate();
        $this->assertTrue(isset($validated['age']));
        $this->assertEquals(25, $validated['age']);
    }

    public function testValidateBetweenFailsOutOfRange(): void
    {
        $request = new class (['age' => '70']) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['between:18,65']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateIn(): void
    {
        $request = new class (['status' => 'active']) extends FormRequest {
            protected function rules(): array
            {
                return ['status' => ['in:active,inactive,pending']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals('active', $validated['status']);
    }

    public function testValidateInFailsForInvalid(): void
    {
        $request = new class (['status' => 'deleted']) extends FormRequest {
            protected function rules(): array
            {
                return ['status' => ['in:active,inactive,pending']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateDate(): void
    {
        $request = new class (['start_date' => '2024-01-15']) extends FormRequest {
            protected function rules(): array
            {
                return ['start_date' => ['date']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals('2024-01-15', $validated['start_date']);
    }

    public function testValidateDateFailsForInvalid(): void
    {
        $request = new class (['start_date' => 'not-a-date']) extends FormRequest {
            protected function rules(): array
            {
                return ['start_date' => ['date']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testValidateBoolean(): void
    {
        $request = new class (['active' => true]) extends FormRequest {
            protected function rules(): array
            {
                return ['active' => ['boolean']];
            }
        };

        $validated = $request->validate();
        $this->assertTrue($validated['active']);
    }

    public function testValidateArray(): void
    {
        $request = new class (['tags' => ['php', 'laravel']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['array']];
            }
        };

        $validated = $request->validate();
        $this->assertIsArray($validated['tags']);
    }

    public function testValidateArrayFailsForNonArray(): void
    {
        $request = new class (['tags' => 'string']) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['array']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testMultipleValidationRules(): void
    {
        $request = new class (['email' => 'test@example.com']) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['required', 'string', 'email']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals('test@example.com', $validated['email']);
    }

    public function testMultipleFields(): void
    {
        $request = new class ([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => '30',
        ]) extends FormRequest {
            protected function rules(): array
            {
                return [
                    'name' => ['required', 'string'],
                    'email' => ['required', 'email'],
                    'age' => ['required', 'integer'],
                ];
            }
        };

        $validated = $request->validate();
        $this->assertCount(3, $validated);
        $this->assertEquals('John Doe', $validated['name']);
        $this->assertEquals('john@example.com', $validated['email']);
    }

    public function testAuthorizationFailure(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }

            protected function authorize(): bool
            {
                return false;
            }
        };

        $this->expectException(AuthorizationException::class);
        $request->validate();
    }

    public function testGetMethod(): void
    {
        $request = new class (['name' => 'John', 'age' => '30']) extends FormRequest {
            protected function rules(): array
            {
                return [];
            }
        };

        $this->assertEquals('John', $request->get('name'));
        $this->assertEquals('30', $request->get('age'));
        $this->assertNull($request->get('nonexistent'));
        $this->assertEquals('default', $request->get('nonexistent', 'default'));
    }

    public function testHasMethod(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return [];
            }
        };

        $this->assertTrue($request->has('name'));
        $this->assertFalse($request->has('nonexistent'));
    }

    public function testValidatedMethod(): void
    {
        $request = new class (['name' => 'John', 'extra' => 'value']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $validated = $request->validated();
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testCustomErrorMessages(): void
    {
        $request = new class (['name' => '']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }

            protected function messages(): array
            {
                return ['name.required' => 'Custom name required message'];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertStringContainsString('Custom name required message', $errors['name'][0]);
        }
    }

    public function testNullValuesPassOptionalRules(): void
    {
        $request = new class (['email' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['email']];
            }
        };

        $validated = $request->validate();
        $this->assertArrayHasKey('email', $validated);
        $this->assertNull($validated['email']);
    }

    public function testMinWithNumericValue(): void
    {
        $request = new class (['quantity' => 10]) extends FormRequest {
            protected function rules(): array
            {
                return ['quantity' => ['min:5']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals(10, $validated['quantity']);
    }

    public function testMaxWithNumericValue(): void
    {
        $request = new class (['quantity' => 5]) extends FormRequest {
            protected function rules(): array
            {
                return ['quantity' => ['max:10']];
            }
        };

        $validated = $request->validate();
        $this->assertEquals(5, $validated['quantity']);
    }

    public function testMinWithArray(): void
    {
        $request = new class (['tags' => ['a', 'b', 'c']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['min:2']];
            }
        };

        $validated = $request->validate();
        $this->assertCount(3, $validated['tags']);
    }

    public function testMaxWithArray(): void
    {
        $request = new class (['tags' => ['a', 'b']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['max:5']];
            }
        };

        $validated = $request->validate();
        $this->assertCount(2, $validated['tags']);
    }
}
