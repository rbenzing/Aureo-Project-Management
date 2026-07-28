<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\FavoriteType;
use App\Enums\MilestoneStatus;
use App\Enums\MilestoneType;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TemplateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests applied to every backed enum.
 *
 * These catch the failure mode that actually happens in this codebase: someone
 * adds a case and forgets a match() arm, which throws \UnhandledMatchError at
 * runtime — in a view, in production, with no compile-time warning. Iterating
 * every case through every accessor turns that into a test failure instead.
 */
#[CoversClass(FavoriteType::class)]
#[CoversClass(MilestoneStatus::class)]
#[CoversClass(MilestoneType::class)]
#[CoversClass(Priority::class)]
#[CoversClass(ProjectStatus::class)]
#[CoversClass(SprintStatus::class)]
#[CoversClass(TaskStatus::class)]
#[CoversClass(TaskType::class)]
#[CoversClass(TemplateType::class)]
final class EnumContractTest extends TestCase
{
    /** Every backed enum in the application. */
    public static function allEnums(): array
    {
        return [
            'FavoriteType' => [FavoriteType::class],
            'MilestoneStatus' => [MilestoneStatus::class],
            'MilestoneType' => [MilestoneType::class],
            'Priority' => [Priority::class],
            'ProjectStatus' => [ProjectStatus::class],
            'SprintStatus' => [SprintStatus::class],
            'TaskStatus' => [TaskStatus::class],
            'TaskType' => [TaskType::class],
            'TemplateType' => [TemplateType::class],
        ];
    }

    /** Enums exposing fromOrDefault() (string-backed). */
    public static function stringBackedEnums(): array
    {
        return [
            'FavoriteType' => [FavoriteType::class],
            'MilestoneType' => [MilestoneType::class],
            'Priority' => [Priority::class],
            'TaskType' => [TaskType::class],
            'TemplateType' => [TemplateType::class],
        ];
    }

    /** Enums exposing tryFromInt() (int-backed). */
    public static function intBackedEnums(): array
    {
        return [
            'MilestoneStatus' => [MilestoneStatus::class],
            'ProjectStatus' => [ProjectStatus::class],
            'SprintStatus' => [SprintStatus::class],
            'TaskStatus' => [TaskStatus::class],
        ];
    }

    // ------------------------------------------------------------- structure

    #[DataProvider('allEnums')]
    public function testHasAtLeastOneCase(string $enum): void
    {
        $this->assertNotEmpty($enum::cases(), "{$enum} declares no cases");
    }

    #[DataProvider('allEnums')]
    public function testValuesMatchDeclaredCases(string $enum): void
    {
        $expected = array_map(fn ($c) => $c->value, $enum::cases());

        $this->assertSame($expected, $enum::values());
    }

    #[DataProvider('allEnums')]
    public function testCaseValuesAreUnique(string $enum): void
    {
        $values = $enum::values();

        $this->assertSame(
            count($values),
            count(array_unique($values)),
            "{$enum} has duplicate backing values"
        );
    }

    // ---------------------------------------------------------- match() arms

    #[DataProvider('allEnums')]
    public function testEveryCaseHasALabel(string $enum): void
    {
        foreach ($enum::cases() as $case) {
            // Throws \UnhandledMatchError if a match arm is missing.
            $label = $case->label();

            $this->assertNotSame('', trim($label), "{$enum}::{$case->name} has an empty label");
        }
    }

    #[DataProvider('allEnums')]
    public function testLabelsAreUniqueWithinAnEnum(string $enum): void
    {
        $labels = array_map(fn ($c) => $c->label(), $enum::cases());

        $this->assertSame(
            count($labels),
            count(array_unique($labels)),
            "{$enum} has two cases sharing a label — dropdowns would be ambiguous"
        );
    }

    #[DataProvider('allEnums')]
    public function testEveryCaseHasAColorClass(string $enum): void
    {
        foreach ($enum::cases() as $case) {
            $this->assertNotSame(
                '',
                trim($case->colorClass()),
                "{$enum}::{$case->name} has an empty colorClass"
            );
        }
    }

    #[DataProvider('allEnums')]
    public function testEveryOptionalAccessorCoversEveryCase(string $enum): void
    {
        // Accessors that only some enums declare. Calling each on every case
        // proves no match() arm was forgotten.
        $optional = ['description', 'icon', 'badgeClass', 'pluralLabel', 'shortLabel'];
        $checked = 0;

        foreach ($optional as $method) {
            if (!method_exists($enum, $method)) {
                continue;
            }

            foreach ($enum::cases() as $case) {
                $value = $case->{$method}();
                $this->assertIsString($value);
                $this->assertNotSame(
                    '',
                    trim($value),
                    "{$enum}::{$case->name}->{$method}() returned empty"
                );
                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(0, $checked);
    }

    // -------------------------------------------------------------- dropdown

    #[DataProvider('allEnums')]
    public function testOptionsMapEveryValueToItsLabel(string $enum): void
    {
        $options = $enum::options();

        $this->assertCount(count($enum::cases()), $options);

        foreach ($enum::cases() as $case) {
            $this->assertArrayHasKey($case->value, $options);
            $this->assertSame($case->label(), $options[$case->value]);
        }
    }

    // ------------------------------------------------------------ validation

    #[DataProvider('allEnums')]
    public function testValidationRuleListsEveryValue(string $enum): void
    {
        $rule = $enum::validationRule();

        $this->assertStringStartsWith('in:', $rule);

        $listed = explode(',', substr($rule, 3));
        $this->assertSame(
            array_map('strval', $enum::values()),
            $listed,
            "{$enum}::validationRule() is out of sync with its cases"
        );
    }

    // ------------------------------------------------------- safe conversion

    #[DataProvider('stringBackedEnums')]
    public function testFromOrDefaultResolvesKnownValues(string $enum): void
    {
        foreach ($enum::cases() as $case) {
            $this->assertSame($case, $enum::fromOrDefault($case->value));
        }
    }

    #[DataProvider('stringBackedEnums')]
    public function testFromOrDefaultFallsBackOnGarbage(string $enum): void
    {
        $result = $enum::fromOrDefault('not-a-real-value-' . bin2hex(random_bytes(4)));

        $this->assertInstanceOf($enum, $result, "{$enum}::fromOrDefault() must never return null");
    }

    #[DataProvider('stringBackedEnums')]
    public function testFromOrDefaultHonoursAnExplicitDefault(string $enum): void
    {
        $explicit = $enum::cases()[count($enum::cases()) - 1];

        $this->assertSame($explicit, $enum::fromOrDefault('garbage-value', $explicit));
    }

    #[DataProvider('intBackedEnums')]
    public function testTryFromIntResolvesKnownValues(string $enum): void
    {
        foreach ($enum::cases() as $case) {
            $this->assertSame($case, $enum::tryFromInt($case->value));
        }
    }

    #[DataProvider('intBackedEnums')]
    public function testTryFromIntReturnsNullForNull(string $enum): void
    {
        $this->assertNull($enum::tryFromInt(null));
    }

    #[DataProvider('intBackedEnums')]
    public function testTryFromIntReturnsNullForUnknownValue(string $enum): void
    {
        $this->assertNull($enum::tryFromInt(999999));
    }

    /**
     * Guards the documented footgun: redefining tryFrom() on a backed enum is a
     * fatal error. tryFromInt() must be a distinct method that delegates to the
     * native tryFrom().
     */
    #[DataProvider('intBackedEnums')]
    public function testTryFromIntDelegatesToNativeTryFrom(string $enum): void
    {
        $case = $enum::cases()[0];

        $this->assertSame($enum::tryFrom($case->value), $enum::tryFromInt($case->value));
    }
}
