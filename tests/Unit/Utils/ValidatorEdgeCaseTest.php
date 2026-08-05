<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\Priority;
use App\Utils\Validator;
use InvalidArgumentException;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Covers the Validator branches that tests/Unit/ValidatorTest.php (owned by
 * another agent, not modified here) does not exercise: boolean/in/date/
 * same/alpha/alphanumeric/url/phone/json/array/after/strong_password/enum,
 * the nullable short-circuit, the unknown-rule exception, array-form rule
 * definitions, and the DB-backed unique/exists rules (including their
 * PDOException path) via a mocked Database singleton so no real MySQL
 * connection is ever attempted.
 *
 * Whenever a test does not pre-install a mocked Database (i.e. everything
 * except the unique/exists/PDOException cases below), Validator's
 * constructor builds a real App\Core\Database, whose validateCredentials()
 * calls Config::isProduction() for real — hence #[UsesClass(Config::class)].
 */
#[CoversClass(Validator::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
final class ValidatorEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDatabaseSingleton(null);
    }

    protected function tearDown(): void
    {
        $this->setDatabaseSingleton(null);

        parent::tearDown();
    }

    private function setDatabaseSingleton(?Database $db): void
    {
        $property = (new ReflectionClass(Database::class))->getProperty('instance');
        $property->setValue(null, $db);
    }

    private function mockDatabaseReturningColumn(mixed $columnValue): Database
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn($columnValue);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($statement);

        return $db;
    }

    // ------------------------------------------------------------ unknown rule

    public function testFailsThrowsForUnknownRuleName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validation rule: bogus_rule');

        (new Validator(['name' => 'x'], ['name' => 'bogus_rule']))->fails();
    }

    // -------------------------------------------------------------- nullable

    public function testNullableSkipsAllOtherRulesWhenValueIsEmpty(): void
    {
        $v = new Validator(['email' => ''], ['email' => 'nullable|email']);

        $this->assertFalse($v->fails());
        $this->assertArrayNotHasKey('email', $v->errors());
    }

    public function testArrayFormRuleDefinitionIsParsedLikePipeString(): void
    {
        $v = new Validator(['name' => 'ab'], ['name' => ['required', 'min:3']]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    // -------------------------------------------------------------- boolean

    public function testBooleanAcceptsCanonicalTruthyAndFalsyValues(): void
    {
        foreach ([true, false, 0, 1, '0', '1'] as $value) {
            $v = new Validator(['flag' => $value], ['flag' => 'boolean']);
            $this->assertFalse($v->fails(), 'Expected boolean value ' . var_export($value, true) . ' to pass');
        }
    }

    public function testBooleanRejectsNonBooleanValue(): void
    {
        $v = new Validator(['flag' => 'yes'], ['flag' => 'boolean']);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('flag', $v->errors());
    }

    // ------------------------------------------------------------------- in

    public function testInPassesWhenValueIsInList(): void
    {
        $v = new Validator(['status' => 'open'], ['status' => 'in:open,closed']);

        $this->assertFalse($v->fails());
    }

    public function testInFailsAndListsAllowedValuesInMessage(): void
    {
        $v = new Validator(['status' => 'bogus'], ['status' => 'in:open,closed']);

        $this->assertTrue($v->fails());
        $this->assertStringContainsString('open, closed', $v->errors()['status']);
    }

    // ----------------------------------------------------------------- date

    public function testDatePassesForValidDateString(): void
    {
        $v = new Validator(['start' => '2026-01-15'], ['start' => 'date']);

        $this->assertFalse($v->fails());
    }

    public function testDateFailsForUnparseableString(): void
    {
        $v = new Validator(['start' => 'not-a-real-date-!!'], ['start' => 'date']);

        $this->assertTrue($v->fails());
    }

    // ----------------------------------------------------------------- same

    public function testSamePassesWhenValuesMatch(): void
    {
        $v = new Validator(
            ['password' => 'secret', 'password_confirm' => 'secret'],
            ['password_confirm' => 'same:password'],
        );

        $this->assertFalse($v->fails());
    }

    public function testSameFailsWhenValuesDiffer(): void
    {
        $v = new Validator(
            ['password' => 'secret', 'password_confirm' => 'different'],
            ['password_confirm' => 'same:password'],
        );

        $this->assertTrue($v->fails());
    }

    public function testSameSkipsWhenNoComparisonFieldParameterGiven(): void
    {
        $v = new Validator(['password_confirm' => 'anything'], ['password_confirm' => 'same']);

        $this->assertFalse($v->fails());
    }

    // ---------------------------------------------------------------- alpha

    public function testAlphaPassesForLettersAndSpaces(): void
    {
        $v = new Validator(['name' => 'John Doe'], ['name' => 'alpha']);

        $this->assertFalse($v->fails());
    }

    public function testAlphaFailsWhenDigitsPresent(): void
    {
        $v = new Validator(['name' => 'John3'], ['name' => 'alpha']);

        $this->assertTrue($v->fails());
    }

    // --------------------------------------------------------- alphanumeric

    public function testAlphanumericPassesForLettersAndDigits(): void
    {
        $v = new Validator(['code' => 'Abc123'], ['code' => 'alphanumeric']);

        $this->assertFalse($v->fails());
    }

    public function testAlphanumericFailsWhenSymbolPresent(): void
    {
        $v = new Validator(['code' => 'Abc-123'], ['code' => 'alphanumeric']);

        $this->assertTrue($v->fails());
    }

    // ------------------------------------------------------------------ url

    public function testUrlPassesForValidUrl(): void
    {
        $v = new Validator(['site' => 'https://example.com/path'], ['site' => 'url']);

        $this->assertFalse($v->fails());
    }

    public function testUrlFailsForInvalidUrl(): void
    {
        $v = new Validator(['site' => 'not a url'], ['site' => 'url']);

        $this->assertTrue($v->fails());
    }

    // ---------------------------------------------------------------- phone

    public function testPhonePassesAndSanitizesValidNumber(): void
    {
        $v = new Validator(['phone' => '+1 (555) 123-4567'], ['phone' => 'phone']);

        $this->assertFalse($v->fails());
        $this->assertSame('+1(555)123-4567', $v->sanitized()['phone']);
    }

    public function testPhoneFailsForTooShortNumber(): void
    {
        $v = new Validator(['phone' => '123'], ['phone' => 'phone']);

        $this->assertTrue($v->fails());
    }

    // ----------------------------------------------------------------- json

    public function testJsonPassesForValidJsonString(): void
    {
        $v = new Validator(['payload' => '{"a":1}'], ['payload' => 'json']);

        $this->assertFalse($v->fails());
    }

    public function testJsonFailsForInvalidJsonString(): void
    {
        $v = new Validator(['payload' => '{not json}'], ['payload' => 'json']);

        $this->assertTrue($v->fails());
    }

    // ---------------------------------------------------------------- array

    public function testArrayPassesForArrayValue(): void
    {
        $v = new Validator(['tags' => ['a', 'b']], ['tags' => 'array']);

        $this->assertFalse($v->fails());
    }

    public function testArrayFailsForNonArrayValue(): void
    {
        $v = new Validator(['tags' => 'not-an-array'], ['tags' => 'array']);

        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------- unique

    public function testUniquePassesWhenNoExistingRowFound(): void
    {
        $this->setDatabaseSingleton($this->mockDatabaseReturningColumn(0));

        $v = new Validator(['email' => 'new@example.com'], ['email' => 'unique:users,email']);

        $this->assertFalse($v->fails());
    }

    public function testUniqueFailsWhenExistingRowFound(): void
    {
        $this->setDatabaseSingleton($this->mockDatabaseReturningColumn(1));

        $v = new Validator(['email' => 'taken@example.com'], ['email' => 'unique:users,email']);

        $this->assertTrue($v->fails());
    }

    public function testUniqueExcludesCurrentRecordIdWhenPresent(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn(0);

        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('executeQuery')
            ->with($this->stringContains('AND id != :id'), $this->arrayHasKey(':id'))
            ->willReturn($statement);
        $this->setDatabaseSingleton($db);

        $v = new Validator(['id' => 5, 'email' => 'me@example.com'], ['email' => 'unique:users,email']);

        $this->assertFalse($v->fails());
    }

    public function testUniqueThrowsForInvalidTableName(): void
    {
        $this->setDatabaseSingleton($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);

        (new Validator(['email' => 'x@example.com'], ['email' => 'unique:bad-table!,email']))->fails();
    }

    public function testUniqueSkipsWhenValueIsEmpty(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->never())->method('executeQuery');
        $this->setDatabaseSingleton($db);

        $v = new Validator(['email' => ''], ['email' => 'unique:users,email']);

        $this->assertFalse($v->fails());
    }

    // -------------------------------------------------------------- exists

    public function testExistsPassesWhenRowFound(): void
    {
        $this->setDatabaseSingleton($this->mockDatabaseReturningColumn(1));

        $v = new Validator(['role_id' => 3], ['role_id' => 'exists:roles,id']);

        $this->assertFalse($v->fails());
    }

    public function testExistsFailsWhenRowNotFound(): void
    {
        $this->setDatabaseSingleton($this->mockDatabaseReturningColumn(0));

        $v = new Validator(['role_id' => 999], ['role_id' => 'exists:roles,id']);

        $this->assertTrue($v->fails());
    }

    public function testExistsThrowsForInvalidColumnName(): void
    {
        $this->setDatabaseSingleton($this->createMock(Database::class));

        $this->expectException(InvalidArgumentException::class);

        (new Validator(['role_id' => 3], ['role_id' => 'exists:roles,bad col']))->fails();
    }

    public function testExistsSkipsWhenParametersAreMissing(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->never())->method('executeQuery');
        $this->setDatabaseSingleton($db);

        $v = new Validator(['role_id' => 3], ['role_id' => 'exists']);

        $this->assertFalse($v->fails());
    }

    // ------------------------------------------------------ PDOException path

    public function testDatabaseErrorDuringUniqueValidationIsCaughtAndReported(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new PDOException('connection lost'));
        $this->setDatabaseSingleton($db);

        $v = new Validator(['email' => 'x@example.com'], ['email' => 'unique:users,email']);

        $this->assertTrue($v->fails());
        $this->assertStringContainsString('Database error occurred while validating email', $v->errors()['email']);
    }

    // ----------------------------------------------------------------- after

    public function testAfterPassesWhenValueIsLaterThanStaticDate(): void
    {
        $v = new Validator(['end' => '2026-02-01'], ['end' => 'after:2026-01-01']);

        $this->assertFalse($v->fails());
    }

    public function testAfterFailsWhenValueIsNotLaterThanStaticDate(): void
    {
        $v = new Validator(['end' => '2026-01-01'], ['end' => 'after:2026-02-01']);

        $this->assertTrue($v->fails());
    }

    public function testAfterComparesAgainstAnotherFieldWhenParameterIsFieldName(): void
    {
        $v = new Validator(
            ['start' => '2026-01-01', 'end' => '2026-03-01'],
            ['end' => 'after:start'],
        );

        $this->assertFalse($v->fails());
    }

    public function testAfterFailsForUnparseableDate(): void
    {
        $v = new Validator(['end' => 'not-a-date'], ['end' => 'after:2026-01-01']);

        $this->assertTrue($v->fails());
    }

    public function testAfterSkipsWhenValueIsEmpty(): void
    {
        $v = new Validator(['end' => ''], ['end' => 'after:2026-01-01']);

        $this->assertFalse($v->fails());
    }

    // -------------------------------------------------------- strong_password

    /**
     * Regression test for the snake_case rule dispatch bug.
     *
     * fails() resolves handlers by studly-casing the rule name. It previously
     * used ucfirst(), which capitalises only the first character, so
     * 'strong_password' resolved to the non-existent 'validateStrong_password'
     * and method_exists() silently returned false. The rule never ran, and every
     * password — including "password" — passed the strength check on registration
     * and password reset.
     */
    public function testStrongPasswordRuleRunsThroughPublicDispatch(): void
    {
        $weak = new Validator(['password' => 'password'], ['password' => 'strong_password']);
        $strong = new Validator(['password' => 'Str0ng!Pass'], ['password' => 'strong_password']);

        $this->assertTrue($weak->fails(), 'A weak password must be rejected by the strong_password rule');
        $this->assertArrayHasKey('password', $weak->errors());
        $this->assertFalse($strong->fails(), 'A compliant password must pass');
    }

    /**
     * Every registered rule must resolve to a real handler method. This is the
     * guard that would have caught the strong_password mismatch, and catches any
     * future multi-word rule added to the allow-list without a handler.
     */
    public function testEveryRegisteredRuleResolvesToAHandlerMethod(): void
    {
        $available = (new ReflectionClass(Validator::class))->getConstant('AVAILABLE_RULES');

        // 'nullable' is a modifier consumed by isNullable(), not a rule with its
        // own handler, so it legitimately has no validateNullable() method.
        $modifiers = ['nullable'];

        $unresolved = [];
        foreach (array_diff($available, $modifiers) as $rule) {
            $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));
            if (!method_exists(Validator::class, $method)) {
                $unresolved[] = "{$rule} -> {$method}";
            }
        }

        $this->assertSame([], $unresolved, 'Rules with no handler method: ' . implode(', ', $unresolved));
    }

    /**
     * Because the dispatch bug above makes validateStrongPassword()
     * unreachable through Validator::fails(), its regex branches are
     * exercised directly via reflection instead.
     */
    public function testValidateStrongPasswordMethodAcceptsCompliantValueViaDirectInvocation(): void
    {
        $v = new Validator(['password' => 'Str0ng!Pass'], []);
        $method = new ReflectionMethod(Validator::class, 'validateStrongPassword');
        $method->invoke($v, 'password', 'Str0ng!Pass');

        $this->assertSame([], $v->errors());
    }

    public function testValidateStrongPasswordMethodRejectsWeakValueViaDirectInvocation(): void
    {
        $v = new Validator(['password' => 'weak'], []);
        $method = new ReflectionMethod(Validator::class, 'validateStrongPassword');
        $method->invoke($v, 'password', 'weak');

        $this->assertArrayHasKey('password', $v->errors());
    }

    public function testValidateStrongPasswordMethodSkipsNullValueViaDirectInvocation(): void
    {
        $v = new Validator(['password' => null], []);
        $method = new ReflectionMethod(Validator::class, 'validateStrongPassword');
        $method->invoke($v, 'password', null);

        $this->assertSame([], $v->errors());
    }

    // -------------------------------------------------------------------- enum

    public function testEnumPassesForValidBackedValue(): void
    {
        $v = new Validator(['priority' => 'low'], ['priority' => 'enum:' . Priority::class]);

        $this->assertFalse($v->fails());
    }

    public function testEnumFailsForInvalidValue(): void
    {
        $v = new Validator(['priority' => 'urgent'], ['priority' => 'enum:' . Priority::class]);

        $this->assertTrue($v->fails());
        // The 'enum' custom message has no ':param' token, so the valid
        // values list is computed but never interpolated — only the
        // generic message is produced.
        $this->assertSame('Priority must be a valid value.', $v->errors()['priority']);
    }

    public function testEnumSkipsWhenValueIsNull(): void
    {
        $v = new Validator(['priority' => null], ['priority' => 'enum:' . Priority::class]);

        $this->assertFalse($v->fails());
    }

    public function testEnumThrowsWhenClassParameterIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enum validation requires an enum class name');

        (new Validator(['priority' => 'low'], ['priority' => 'enum']))->fails();
    }

    public function testEnumThrowsForNonEnumClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid enum class: App\Utils\Validator');

        (new Validator(['priority' => 'low'], ['priority' => 'enum:App\Utils\Validator']))->fails();
    }
}
