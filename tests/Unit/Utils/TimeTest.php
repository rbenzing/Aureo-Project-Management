<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Utils\Time;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for Time: settings-aware and settings-independent
 * formatting, duration math, overdue/remaining-day calculations and the
 * unit conversion helpers, across every branch (including the
 * unrecognised-unit fallthrough to "minutes").
 *
 * Every path with $useSettings=true (the default) or $unit=null reaches
 * SettingsService::getInstance(), so the process-wide singleton is swapped
 * for a mock via reflection and restored afterwards, avoiding any real
 * Setting/Database access.
 */
// Config/Database/Setting are declared because SettingsService::getInstance()
// reaches them transitively. They are process-wide singletons, so only the FIRST
// test in a run to trigger initialization actually executes their bodies —
// meaning which test needs these declarations shifts with execution order.
// Declaring them here keeps the strict-metadata check stable across orderings.
#[CoversClass(Time::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
final class TimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setSettingsServiceSingleton(null);
    }

    protected function tearDown(): void
    {
        $this->setSettingsServiceSingleton(null);

        parent::tearDown();
    }

    private function setSettingsServiceSingleton(?SettingsService $service): void
    {
        $property = (new ReflectionClass(SettingsService::class))->getProperty('instance');
        $property->setValue(null, $service);
    }

    private function mockSettingsService(string $unit, int $precision = 15): SettingsService
    {
        $mock = $this->createMock(SettingsService::class);
        $mock->method('getTimeUnitLabel')->willReturn($unit);
        $mock->method('getTimeIntervalSettings')->willReturn([
            'time_unit' => $unit,
            'time_precision' => $precision,
        ]);
        $mock->method('convertTime')->willReturnCallback(static function (int $seconds) use ($unit): array {
            return match ($unit) {
                'seconds' => ['value' => $seconds, 'unit' => 'seconds', 'formatted' => $seconds . 's'],
                'hours' => ['value' => round($seconds / 3600, 2), 'unit' => 'hours', 'formatted' => round($seconds / 3600, 2) . 'h'],
                'days' => ['value' => round($seconds / 86400, 2), 'unit' => 'days', 'formatted' => round($seconds / 86400, 2) . 'd'],
                default => ['value' => round($seconds / 60, 2), 'unit' => 'minutes', 'formatted' => round($seconds / 60, 2) . 'm'],
            };
        });

        return $mock;
    }

    // ---------------------------------------------------------- formatSeconds()

    public function testFormatSecondsNullWithSettingsUsesUnitLabel(): void
    {
        $this->setSettingsServiceSingleton($this->mockSettingsService('hours'));

        $this->assertSame('0 hours', Time::formatSeconds(null));
    }

    public function testFormatSecondsZeroWithSettingsUsesUnitLabel(): void
    {
        $this->setSettingsServiceSingleton($this->mockSettingsService('days'));

        $this->assertSame('0 days', Time::formatSeconds(0));
    }

    public function testFormatSecondsZeroWithoutSettingsUsesLegacyFormat(): void
    {
        $this->assertSame('0h 0m', Time::formatSeconds(0, false));
        $this->assertSame('0h 0m', Time::formatSeconds(null, false));
    }

    public function testFormatSecondsWithSettingsDelegatesToConvertTime(): void
    {
        $this->setSettingsServiceSingleton($this->mockSettingsService('minutes'));

        $this->assertSame('83.33m', Time::formatSeconds(5000));
    }

    public function testFormatSecondsWithoutSettingsUsesLegacyHoursMinutesFormat(): void
    {
        $this->assertSame('1h 23m', Time::formatSeconds(5000, false));
    }

    // --------------------------------------------------------- formatDuration()

    public function testFormatDurationComputesPartsAndDelegatesFormatting(): void
    {
        $this->setSettingsServiceSingleton($this->mockSettingsService('hours'));

        $result = Time::formatDuration(1000, 1000 + 3665);

        $this->assertSame(1.0, $result['hours']);
        $this->assertSame(1.0, $result['minutes']);
        $this->assertSame(5, $result['seconds']);
        $this->assertSame(3665, $result['total_seconds']);
        $this->assertSame('1.02h', $result['formatted']);
    }

    // ---------------------------------------------------------- daysRemaining()

    public function testDaysRemainingReturnsNullForEmptyDate(): void
    {
        $this->assertNull(Time::daysRemaining(null));
        $this->assertNull(Time::daysRemaining(''));
    }

    public function testDaysRemainingIsPositiveForFutureDate(): void
    {
        $future = (new DateTime('now'))->modify('+10 days')->format('Y-m-d');

        $this->assertGreaterThanOrEqual(9, Time::daysRemaining($future));
    }

    public function testDaysRemainingIsNegativeForPastDate(): void
    {
        $past = (new DateTime('now'))->modify('-10 days')->format('Y-m-d');

        $this->assertLessThanOrEqual(-9, Time::daysRemaining($past));
    }

    // -------------------------------------------------------------- isOverdue()

    public function testIsOverdueFalseForEmptyDate(): void
    {
        $this->assertFalse(Time::isOverdue(null, 1));
        $this->assertFalse(Time::isOverdue('', 1));
    }

    public function testIsOverdueFalseWhenStatusIsInDefaultCompletedList(): void
    {
        $past = (new DateTime('now'))->modify('-5 days')->format('Y-m-d');

        $this->assertFalse(Time::isOverdue($past, 5));
        $this->assertFalse(Time::isOverdue($past, 6));
    }

    public function testIsOverdueTrueForPastDueDateWithIncompleteStatus(): void
    {
        $past = (new DateTime('now'))->modify('-5 days')->format('Y-m-d');

        $this->assertTrue(Time::isOverdue($past, 1));
    }

    public function testIsOverdueFalseForFutureDueDate(): void
    {
        $future = (new DateTime('now'))->modify('+5 days')->format('Y-m-d');

        $this->assertFalse(Time::isOverdue($future, 1));
    }

    public function testIsOverdueRespectsCustomCompletedStatusIds(): void
    {
        $past = (new DateTime('now'))->modify('-5 days')->format('Y-m-d');

        $this->assertFalse(Time::isOverdue($past, 9, [9]));
        $this->assertTrue(Time::isOverdue($past, 9, [10]));
    }

    // ----------------------------------------------------------- convertToSeconds()

    public function testConvertToSecondsWithExplicitUnitDoesNotTouchSettings(): void
    {
        $this->assertSame(90, Time::convertToSeconds(90, 'seconds'));
        $this->assertSame(7200, Time::convertToSeconds(2, 'hours'));
        $this->assertSame(172800, Time::convertToSeconds(2, 'days'));
        $this->assertSame(120, Time::convertToSeconds(2, 'minutes'));
    }

    public function testConvertToSecondsFallsBackToMinutesForUnrecognisedUnit(): void
    {
        $this->assertSame(120, Time::convertToSeconds(2, 'fortnights'));
    }

    public function testConvertToSecondsUsesSettingsWhenUnitIsNull(): void
    {
        $this->setSettingsServiceSingleton($this->mockSettingsService('hours'));

        $this->assertSame(7200, Time::convertToSeconds(2, null));
    }

    // --------------------------------------------------------- convertFromSeconds()

    public function testConvertFromSecondsWithExplicitUnitDoesNotTouchSettings(): void
    {
        $this->assertSame(90.0, Time::convertFromSeconds(90, 'seconds'));
        $this->assertSame(2.0, Time::convertFromSeconds(7200, 'hours'));
        $this->assertSame(2.0, Time::convertFromSeconds(172800, 'days'));
        $this->assertSame(2.0, Time::convertFromSeconds(120, 'minutes'));
    }

    public function testConvertFromSecondsFallsBackToMinutesForUnrecognisedUnit(): void
    {
        $this->assertSame(2.0, Time::convertFromSeconds(120, 'fortnights'));
    }

    public function testConvertFromSecondsUsesSettingsWhenUnitIsNull(): void
    {
        $this->setSettingsServiceSingleton($this->mockSettingsService('days'));

        $this->assertSame(2.0, Time::convertFromSeconds(172800, null));
    }

    // ------------------------------------------------------- parseTimeToSeconds()

    public function testParseTimeToSecondsHandlesHoursAndMinutesFormat(): void
    {
        $this->assertSame(9000, Time::parseTimeToSeconds('2h 30m'));
    }

    public function testParseTimeToSecondsHandlesDecimalHoursFormat(): void
    {
        $this->assertSame(9000, Time::parseTimeToSeconds('2.5h'));
    }

    public function testParseTimeToSecondsHandlesMinutesOnlyFormat(): void
    {
        $this->assertSame(9000, Time::parseTimeToSeconds('150m'));
    }

    public function testParseTimeToSecondsHandlesColonFormat(): void
    {
        $this->assertSame(150, Time::parseTimeToSeconds('02:30'));
    }

    public function testParseTimeToSecondsReturnsZeroForUnrecognisedFormat(): void
    {
        $this->assertSame(0, Time::parseTimeToSeconds('not-a-time'));
    }
}
