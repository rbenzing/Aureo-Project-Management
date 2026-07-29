<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Http\Requests\FormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive coverage of App\Http\Requests\FormRequest.
 *
 * NOTE: tests/Unit/FormRequestTest.php (outside this module's scope) already
 * exercises this class behaviourally, but PHPUnit's metadata-driven coverage
 * only records a class's executed lines against the test that declares
 * #[CoversClass] for it — lines touched only via #[UsesClass] in other test
 * files are not merged in. Since this module is verified in isolation
 * (tests/Unit/Http only), FormRequest is exercised end-to-end here so its
 * own coverage run reflects reality, rather than relying on the excluded
 * suite.
 */
#[CoversClass(FormRequest::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(AuthorizationException::class)]
class FormRequestEdgeCasesTest extends TestCase
{
    // -------------------------------------------------------------------
    // required
    // -------------------------------------------------------------------

    public function testRequiredPassesForNonEmptyValue(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $this->assertSame(['name' => 'John'], $request->validate());
    }

    public function testRequiredFailsForNull(): void
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

    public function testRequiredFailsForEmptyString(): void
    {
        $request = new class (['name' => '  ']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testRequiredFailsForEmptyArray(): void
    {
        $request = new class (['tags' => []]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['required']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The tags field is required', $e->getErrors()['tags'][0]);
        }
    }

    // -------------------------------------------------------------------
    // string
    // -------------------------------------------------------------------

    public function testStringPassesForNull(): void
    {
        $request = new class (['bio' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['bio' => ['string']];
            }
        };

        $this->assertArrayHasKey('bio', $request->validate());
    }

    public function testStringFailsForNonStringValue(): void
    {
        $request = new class (['bio' => 12345]) extends FormRequest {
            protected function rules(): array
            {
                return ['bio' => ['string']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The bio must be a string', $e->getErrors()['bio'][0]);
        }
    }

    // -------------------------------------------------------------------
    // integer
    // -------------------------------------------------------------------

    public function testIntegerPassesForNumericString(): void
    {
        $request = new class (['age' => '30']) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['integer']];
            }
        };

        $this->assertSame(['age' => '30'], $request->validate());
    }

    public function testIntegerFailsForNonNumericString(): void
    {
        $request = new class (['age' => 'thirty']) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['integer']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The age must be an integer', $e->getErrors()['age'][0]);
        }
    }

    // -------------------------------------------------------------------
    // numeric
    // -------------------------------------------------------------------

    public function testNumericPassesForDecimalString(): void
    {
        $request = new class (['price' => '9.99']) extends FormRequest {
            protected function rules(): array
            {
                return ['price' => ['numeric']];
            }
        };

        $this->assertSame(['price' => '9.99'], $request->validate());
    }

    public function testNumericFailsForNonNumericValue(): void
    {
        $request = new class (['price' => 'free']) extends FormRequest {
            protected function rules(): array
            {
                return ['price' => ['numeric']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The price must be numeric', $e->getErrors()['price'][0]);
        }
    }

    // -------------------------------------------------------------------
    // email
    // -------------------------------------------------------------------

    public function testEmailPassesForValidAddress(): void
    {
        $request = new class (['email' => 'a@b.com']) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['email']];
            }
        };

        $this->assertSame(['email' => 'a@b.com'], $request->validate());
    }

    public function testEmailFailsForInvalidAddress(): void
    {
        $request = new class (['email' => 'not-an-email']) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['email']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The email must be a valid email address', $e->getErrors()['email'][0]);
        }
    }

    // -------------------------------------------------------------------
    // url
    // -------------------------------------------------------------------

    public function testUrlPassesForValidUrl(): void
    {
        $request = new class (['site' => 'https://example.com']) extends FormRequest {
            protected function rules(): array
            {
                return ['site' => ['url']];
            }
        };

        $this->assertSame(['site' => 'https://example.com'], $request->validate());
    }

    public function testUrlFailsForInvalidUrl(): void
    {
        $request = new class (['site' => 'nope']) extends FormRequest {
            protected function rules(): array
            {
                return ['site' => ['url']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The site must be a valid URL', $e->getErrors()['site'][0]);
        }
    }

    // -------------------------------------------------------------------
    // min / max — null skip, scalar, numeric, array, non-scalar fallback
    // -------------------------------------------------------------------

    public function testMinSkipsForNull(): void
    {
        $request = new class (['nickname' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['nickname' => ['min:3']];
            }
        };

        $this->assertArrayHasKey('nickname', $request->validate());
    }

    public function testMinPassesForStringLongEnough(): void
    {
        $request = new class (['password' => 'abc12345']) extends FormRequest {
            protected function rules(): array
            {
                return ['password' => ['min:8']];
            }
        };

        $this->assertSame(['password' => 'abc12345'], $request->validate());
    }

    public function testMinFailsForStringTooShort(): void
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

    public function testMinPassesForNumericValue(): void
    {
        $request = new class (['quantity' => 10]) extends FormRequest {
            protected function rules(): array
            {
                return ['quantity' => ['min:5']];
            }
        };

        $this->assertSame(['quantity' => 10], $request->validate());
    }

    public function testMinFailsForNumericValueTooLow(): void
    {
        $request = new class (['quantity' => 1]) extends FormRequest {
            protected function rules(): array
            {
                return ['quantity' => ['min:5']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The quantity must be at least 5', $e->getErrors()['quantity'][0]);
        }
    }

    public function testMinPassesForArrayWithEnoughElements(): void
    {
        $request = new class (['tags' => ['a', 'b', 'c']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['min:2']];
            }
        };

        $this->assertCount(3, $request->validate()['tags']);
    }

    public function testMinFailsForArrayWithTooFewElements(): void
    {
        $request = new class (['tags' => ['a', 'b']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['min:5']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testMinFailsForNonScalarNonArrayValue(): void
    {
        $request = new class (['flag' => true]) extends FormRequest {
            protected function rules(): array
            {
                return ['flag' => ['min:1']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testMaxSkipsForNull(): void
    {
        $request = new class (['nickname' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['nickname' => ['max:3']];
            }
        };

        $this->assertArrayHasKey('nickname', $request->validate());
    }

    public function testMaxPassesForStringShortEnough(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['max:10']];
            }
        };

        $this->assertSame(['name' => 'John'], $request->validate());
    }

    public function testMaxFailsForStringTooLong(): void
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

    public function testMaxPassesForNumericValue(): void
    {
        $request = new class (['quantity' => 5]) extends FormRequest {
            protected function rules(): array
            {
                return ['quantity' => ['max:10']];
            }
        };

        $this->assertSame(['quantity' => 5], $request->validate());
    }

    public function testMaxFailsForNumericValueTooHigh(): void
    {
        $request = new class (['quantity' => 20]) extends FormRequest {
            protected function rules(): array
            {
                return ['quantity' => ['max:10']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testMaxPassesForArrayWithinLimit(): void
    {
        $request = new class (['tags' => ['a', 'b']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['max:5']];
            }
        };

        $this->assertCount(2, $request->validate()['tags']);
    }

    public function testMaxFailsForArrayWithTooManyElements(): void
    {
        $request = new class (['tags' => ['a', 'b', 'c', 'd', 'e']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['max:2']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testMaxFailsForNonScalarNonArrayValue(): void
    {
        $request = new class (['flag' => false]) extends FormRequest {
            protected function rules(): array
            {
                return ['flag' => ['max:1']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    // -------------------------------------------------------------------
    // between
    // -------------------------------------------------------------------

    public function testBetweenPassesWithinRange(): void
    {
        $request = new class (['age' => 25]) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['between:18,65']];
            }
        };

        $this->assertSame(['age' => 25], $request->validate());
    }

    public function testBetweenFailsOutsideRange(): void
    {
        $request = new class (['age' => 70]) extends FormRequest {
            protected function rules(): array
            {
                return ['age' => ['between:18,65']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The age must be between 18 and 65', $e->getErrors()['age'][0]);
        }
    }

    // -------------------------------------------------------------------
    // in
    // -------------------------------------------------------------------

    public function testInSkipsForNull(): void
    {
        $request = new class (['status' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['status' => ['in:active,inactive']];
            }
        };

        $this->assertArrayHasKey('status', $request->validate());
    }

    public function testInPassesForAllowedValue(): void
    {
        $request = new class (['status' => 'active']) extends FormRequest {
            protected function rules(): array
            {
                return ['status' => ['in:active,inactive']];
            }
        };

        $this->assertSame(['status' => 'active'], $request->validate());
    }

    public function testInFailsForDisallowedValue(): void
    {
        $request = new class (['status' => 'archived']) extends FormRequest {
            protected function rules(): array
            {
                return ['status' => ['in:active,inactive']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The selected status is invalid', $e->getErrors()['status'][0]);
        }
    }

    // -------------------------------------------------------------------
    // date
    // -------------------------------------------------------------------

    public function testDateSkipsForNull(): void
    {
        $request = new class (['start' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['start' => ['date']];
            }
        };

        $this->assertArrayHasKey('start', $request->validate());
    }

    public function testDatePassesForValidDateString(): void
    {
        $request = new class (['start' => '2024-01-15']) extends FormRequest {
            protected function rules(): array
            {
                return ['start' => ['date']];
            }
        };

        $this->assertSame(['start' => '2024-01-15'], $request->validate());
    }

    public function testDateFailsForNonStringValue(): void
    {
        $request = new class (['start' => 12345]) extends FormRequest {
            protected function rules(): array
            {
                return ['start' => ['date']];
            }
        };

        $this->expectException(ValidationException::class);
        $request->validate();
    }

    public function testDateFailsForUnparseableString(): void
    {
        $request = new class (['start' => 'not-a-date']) extends FormRequest {
            protected function rules(): array
            {
                return ['start' => ['date']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The start must be a valid date', $e->getErrors()['start'][0]);
        }
    }

    // -------------------------------------------------------------------
    // boolean
    // -------------------------------------------------------------------

    public function testBooleanSkipsForNull(): void
    {
        $request = new class (['active' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['active' => ['boolean']];
            }
        };

        $this->assertArrayHasKey('active', $request->validate());
    }

    public function testBooleanPassesForRecognisedValue(): void
    {
        $request = new class (['active' => 1]) extends FormRequest {
            protected function rules(): array
            {
                return ['active' => ['boolean']];
            }
        };

        $this->assertSame(['active' => 1], $request->validate());
    }

    public function testBooleanFailsForUnrecognisedValue(): void
    {
        $request = new class (['active' => 'yes']) extends FormRequest {
            protected function rules(): array
            {
                return ['active' => ['boolean']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The active must be true or false', $e->getErrors()['active'][0]);
        }
    }

    // -------------------------------------------------------------------
    // array
    // -------------------------------------------------------------------

    public function testArraySkipsForNull(): void
    {
        $request = new class (['tags' => null]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['array']];
            }
        };

        $this->assertArrayHasKey('tags', $request->validate());
    }

    public function testArrayPassesForArrayValue(): void
    {
        $request = new class (['tags' => ['php']]) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['array']];
            }
        };

        $this->assertSame(['tags' => ['php']], $request->validate());
    }

    public function testArrayFailsForNonArrayValue(): void
    {
        $request = new class (['tags' => 'php']) extends FormRequest {
            protected function rules(): array
            {
                return ['tags' => ['array']];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The tags must be an array', $e->getErrors()['tags'][0]);
        }
    }

    // -------------------------------------------------------------------
    // exists / unique placeholders and unrecognised rules
    // -------------------------------------------------------------------

    public function testExistsAndUniquePlaceholderRulesAlwaysPass(): void
    {
        $request = new class (['email' => 'not-checked-against-db']) extends FormRequest {
            protected function rules(): array
            {
                return ['email' => ['exists:users,email', 'unique:users,email']];
            }
        };

        $this->assertSame(['email' => 'not-checked-against-db'], $request->validate());
    }

    public function testUnrecognisedRuleAlwaysPasses(): void
    {
        $request = new class (['name' => 'anything']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['totally-made-up-rule']];
            }
        };

        $this->assertSame(['name' => 'anything'], $request->validate());
    }

    // -------------------------------------------------------------------
    // custom messages() override
    // -------------------------------------------------------------------

    public function testCustomMessageOverridesDefaultMessage(): void
    {
        $request = new class (['name' => '']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }

            protected function messages(): array
            {
                return ['name.required' => 'Custom required message'];
            }
        };

        try {
            $request->validate();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('Custom required message', $e->getErrors()['name'][0]);
        }
    }

    // -------------------------------------------------------------------
    // authorize()
    // -------------------------------------------------------------------

    public function testAuthorizeFailureThrowsAuthorizationException(): void
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

    // -------------------------------------------------------------------
    // validated() / get() / has()
    // -------------------------------------------------------------------

    public function testValidatedDelegatesToValidate(): void
    {
        $request = new class (['name' => 'John', 'extra' => 'ignored']) extends FormRequest {
            protected function rules(): array
            {
                return ['name' => ['required']];
            }
        };

        $validated = $request->validated();

        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testGetReturnsValueOrDefault(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return [];
            }
        };

        $this->assertSame('John', $request->get('name'));
        $this->assertNull($request->get('missing'));
        $this->assertSame('fallback', $request->get('missing', 'fallback'));
    }

    public function testHasReturnsWhetherKeyIsSet(): void
    {
        $request = new class (['name' => 'John']) extends FormRequest {
            protected function rules(): array
            {
                return [];
            }
        };

        $this->assertTrue($request->has('name'));
        $this->assertFalse($request->has('missing'));
    }

    // -------------------------------------------------------------------
    // multi-field validate()
    // -------------------------------------------------------------------

    public function testValidateReturnsOnlyRuleDefinedFields(): void
    {
        $request = new class ([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'unrelated' => 'dropped',
        ]) extends FormRequest {
            protected function rules(): array
            {
                return [
                    'name' => ['required', 'string'],
                    'email' => ['required', 'email'],
                ];
            }
        };

        $validated = $request->validate();

        $this->assertCount(2, $validated);
        $this->assertArrayNotHasKey('unrelated', $validated);
    }
}
