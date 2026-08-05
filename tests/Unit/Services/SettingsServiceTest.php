<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Models\Setting;
use App\Services\SettingsService;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for SettingsService
 *
 * The Setting model is mocked for every test that exercises normal
 * settings-resolution behaviour, so no live MySQL connection is required.
 * A dedicated test exercises the deprecated getInstance()/no-arg
 * constructor path, which transitively constructs real App\Models\Setting
 * and App\Core\Database instances (see testGetInstanceReturnsSingleton);
 * that path never queries the database, only builds credential state, so
 * it stays safe without a live server.
 */
#[CoversClass(SettingsService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(Database::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
final class SettingsServiceTest extends TestCase
{
    private Setting $settingModelMock;
    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingModelMock = $this->createMock(Setting::class);
        $this->service = new SettingsService($this->settingModelMock);

        // The settings cache is a static, so it must be cleared before every
        // test or later tests would silently reuse an earlier test's data.
        $this->service->clearCache();
    }

    protected function tearDown(): void
    {
        $this->service->clearCache();

        parent::tearDown();
    }

    private function configureSettings(array $settings): void
    {
        $this->settingModelMock
            ->method('getAllGrouped')
            ->willReturn($settings);
    }

    public function testGetAllSettingsQueriesModelOnceAndCachesResult(): void
    {
        $this->settingModelMock
            ->expects($this->once())
            ->method('getAllGrouped')
            ->willReturn(['general' => ['results_per_page' => '50']]);

        $first = $this->service->getAllSettings();
        $second = $this->service->getAllSettings();

        $this->assertSame($first, $second);
        $this->assertSame('50', $first['general']['results_per_page']);
    }

    public function testGetAllSettingsAppliesDefaultsForMissingCategoriesAndKeys(): void
    {
        $this->configureSettings([]);

        $settings = $this->service->getAllSettings();

        $this->assertSame('25', $settings['general']['results_per_page']);
        $this->assertSame('task', $settings['projects']['default_task_type']);
        $this->assertSame('Lax', $settings['security']['session_samesite']);
        $this->assertSame('1', $settings['templates']['project_show_quick_templates']);
    }

    public function testGetAllSettingsDoesNotOverrideExistingValuesWithDefaults(): void
    {
        $this->configureSettings(['general' => ['results_per_page' => '99']]);

        $settings = $this->service->getAllSettings();

        $this->assertSame('99', $settings['general']['results_per_page']);
        // Sibling default keys within the same category must still be filled in.
        $this->assertSame('Y-m-d', $settings['general']['date_format']);
    }

    public function testClearCacheForcesModelToBeQueriedAgain(): void
    {
        $this->settingModelMock
            ->expects($this->exactly(2))
            ->method('getAllGrouped')
            ->willReturn([]);

        $this->service->getAllSettings();
        $this->service->clearCache();
        $this->service->getAllSettings();
    }

    public function testGetSettingReturnsStoredValue(): void
    {
        $this->configureSettings(['general' => ['date_format' => 'd/m/Y']]);

        $this->assertSame('d/m/Y', $this->service->getSetting('general', 'date_format'));
    }

    public function testGetSettingReturnsDefaultWhenCategoryMissing(): void
    {
        $this->configureSettings([]);

        $this->assertSame('fallback', $this->service->getSetting('unknown_category', 'unknown_key', 'fallback'));
    }

    public function testGetSettingBoolTreatsOneAndTrueStringAsTrue(): void
    {
        $this->configureSettings([
            'custom' => ['flag_one' => '1', 'flag_true' => 'true', 'flag_off' => '0', 'flag_other' => 'yes'],
        ]);

        $this->assertTrue($this->service->getSettingBool('custom', 'flag_one'));
        $this->assertTrue($this->service->getSettingBool('custom', 'flag_true'));
        $this->assertFalse($this->service->getSettingBool('custom', 'flag_off'));
        $this->assertFalse($this->service->getSettingBool('custom', 'flag_other'));
    }

    public function testGetSettingBoolUsesProvidedDefaultWhenKeyMissing(): void
    {
        $this->configureSettings([]);

        $this->assertTrue($this->service->getSettingBool('custom', 'missing', true));
        $this->assertFalse($this->service->getSettingBool('custom', 'missing', false));
    }

    public function testGetSettingIntCastsStoredStringToInteger(): void
    {
        $this->configureSettings(['custom' => ['limit' => '42']]);

        $this->assertSame(42, $this->service->getSettingInt('custom', 'limit'));
    }

    public function testGetSettingIntUsesDefaultWhenKeyMissing(): void
    {
        $this->configureSettings([]);

        $this->assertSame(7, $this->service->getSettingInt('custom', 'missing', 7));
    }

    public function testGetGeneralSettingsReturnsExpectedKeysWithOverrides(): void
    {
        $this->configureSettings(['general' => ['results_per_page' => '10', 'time_unit' => 'hours']]);

        $result = $this->service->getGeneralSettings();

        $this->assertSame(10, $result['results_per_page']);
        $this->assertSame('hours', $result['time_unit']);
        $this->assertSame('Y-m-d', $result['date_format']);
        $this->assertSame(3600, $result['session_timeout']);
    }

    public function testGetTimeIntervalSettingsDerivesFromGeneralSettings(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'days', 'time_precision' => '30']]);

        $result = $this->service->getTimeIntervalSettings();

        $this->assertSame(['time_unit' => 'days', 'time_precision' => 30], $result);
    }

    public function testGetProjectSettingsReturnsExpectedDefaults(): void
    {
        $this->configureSettings([]);

        $result = $this->service->getProjectSettings();

        $this->assertSame('task', $result['default_task_type']);
        $this->assertTrue($result['auto_assign_creator']);
        $this->assertFalse($result['require_project_for_tasks']);
    }

    public function testGetTaskSettingsReturnsExpectedDefaults(): void
    {
        $this->configureSettings([]);

        $result = $this->service->getTaskSettings();

        $this->assertSame('medium', $result['default_priority']);
        $this->assertFalse($result['auto_estimate_enabled']);
        $this->assertTrue($result['story_points_enabled']);
    }

    public function testGetMilestoneSettingsReturnsExpectedDefaults(): void
    {
        $this->configureSettings([]);

        $result = $this->service->getMilestoneSettings();

        $this->assertFalse($result['auto_create_from_sprints']);
        $this->assertSame(7, $result['milestone_notification_days']);
    }

    public function testGetSprintSettingsReturnsExpectedDefaults(): void
    {
        $this->configureSettings([]);

        $result = $this->service->getSprintSettings();

        $this->assertSame(14, $result['default_sprint_length']);
        $this->assertSame('hours', $result['estimation_method']);
        $this->assertSame(40, $result['team_capacity_hours']);
        $this->assertTrue($result['velocity_tracking_enabled']);
        $this->assertSame('monday,tuesday,wednesday,thursday,friday', $result['working_days']);
        $this->assertTrue($result['retrospective_enabled']);
    }

    public function testGetTemplateSettingsReturnsAllFourEntityGroups(): void
    {
        $this->configureSettings([]);

        $result = $this->service->getTemplateSettings();

        foreach (['project', 'task', 'milestone', 'sprint'] as $entity) {
            $this->assertArrayHasKey($entity, $result);
            $this->assertTrue($result[$entity]['show_quick_templates']);
            $this->assertTrue($result[$entity]['show_custom_templates']);
        }
    }

    public function testGetSecuritySettingsReturnsExpectedDefaults(): void
    {
        $this->configureSettings([]);

        $result = $this->service->getSecuritySettings();

        $this->assertSame('Lax', $result['session_samesite']);
        $this->assertTrue($result['csrf_protection_enabled']);
        $this->assertSame(3600, $result['csrf_token_lifetime']);
        $this->assertSame(1048576, $result['max_input_size']);
        $this->assertSame(60, $result['rate_limit_attempts']);
    }

    public function testGetSecuritySettingReturnsStoredValue(): void
    {
        $this->configureSettings(['security' => ['csp_policy' => 'strict']]);

        $this->assertSame('strict', $this->service->getSecuritySetting('csp_policy'));
    }

    public function testGetSecuritySettingReturnsDefaultForUnknownKey(): void
    {
        $this->configureSettings([]);

        $this->assertSame('fallback', $this->service->getSecuritySetting('does_not_exist', 'fallback'));
        $this->assertNull($this->service->getSecuritySetting('does_not_exist'));
    }

    public function testIsSecurityFeatureEnabledReflectsStoredBooleanSetting(): void
    {
        $this->configureSettings(['security' => ['enable_csp' => '0']]);

        $this->assertFalse($this->service->isSecurityFeatureEnabled('enable_csp'));
    }

    public function testIsSecurityFeatureEnabledDefaultsToTrueWhenUnset(): void
    {
        $this->configureSettings([]);

        $this->assertTrue($this->service->isSecurityFeatureEnabled('some_new_feature'));
    }

    public function testGetAllowedRedirectDomainsReturnsEmptyArrayWhenSettingIsEmpty(): void
    {
        $this->configureSettings(['security' => ['allowed_redirect_domains' => '']]);

        $this->assertSame([], $this->service->getAllowedRedirectDomains());
    }

    public function testGetAllowedRedirectDomainsFiltersInvalidAndBlankEntries(): void
    {
        $this->configureSettings([
            'security' => [
                'allowed_redirect_domains' => "example.com\n\n bad domain \nsub.example.org",
            ],
        ]);

        $result = $this->service->getAllowedRedirectDomains();

        $this->assertSame(['example.com', 'sub.example.org'], array_values($result));
    }

    public function testGetContentSecurityPolicyReturnsEmptyStringWhenCspDisabled(): void
    {
        $this->configureSettings(['security' => ['enable_csp' => '0']]);

        $this->assertSame('', $this->service->getContentSecurityPolicy());
    }

    public function testGetContentSecurityPolicyReturnsStrictPolicy(): void
    {
        $this->configureSettings(['security' => ['enable_csp' => '1', 'csp_policy' => 'strict']]);

        $policy = $this->service->getContentSecurityPolicy();

        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function testGetContentSecurityPolicyReturnsPermissivePolicy(): void
    {
        $this->configureSettings(['security' => ['enable_csp' => '1', 'csp_policy' => 'permissive']]);

        $policy = $this->service->getContentSecurityPolicy();

        $this->assertStringContainsString("'unsafe-eval'", $policy);
    }

    public function testGetContentSecurityPolicyReturnsModeratePolicyByDefault(): void
    {
        $this->configureSettings(['security' => ['enable_csp' => '1', 'csp_policy' => 'moderate']]);

        $policy = $this->service->getContentSecurityPolicy();

        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
    }

    public function testGetContentSecurityPolicyFallsBackToModerateForUnknownPolicyName(): void
    {
        $this->configureSettings(['security' => ['enable_csp' => '1', 'csp_policy' => 'totally-unknown']]);

        $policy = $this->service->getContentSecurityPolicy();

        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
    }

    public function testConvertTimeHandlesSecondsUnit(): void
    {
        $this->configureSettings([]);

        $result = $this->service->convertTime(90, 'seconds');

        $this->assertSame(['value' => 90, 'unit' => 'seconds', 'formatted' => '90s'], $result);
    }

    public function testConvertTimeHandlesHoursUnit(): void
    {
        $this->configureSettings([]);

        $result = $this->service->convertTime(7200, 'hours');

        $this->assertSame(['value' => 2.0, 'unit' => 'hours', 'formatted' => '2h'], $result);
    }

    public function testConvertTimeHandlesDaysUnit(): void
    {
        $this->configureSettings([]);

        $result = $this->service->convertTime(172800, 'days');

        $this->assertSame(['value' => 2.0, 'unit' => 'days', 'formatted' => '2d'], $result);
    }

    public function testConvertTimeDefaultsToMinutesUnit(): void
    {
        $this->configureSettings([]);

        $result = $this->service->convertTime(120, 'unrecognized-unit');

        $this->assertSame(['value' => 2.0, 'unit' => 'minutes', 'formatted' => '2m'], $result);
    }

    public function testConvertTimeFallsBackToConfiguredTimeUnitWhenTargetUnitOmitted(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'seconds']]);

        $result = $this->service->convertTime(45);

        $this->assertSame('seconds', $result['unit']);
    }

    public function testGetTimeStepForSecondsUnit(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'seconds', 'time_precision' => '15']]);

        $this->assertSame('15', $this->service->getTimeStep());
    }

    public function testGetTimeStepForHoursUnitDividesPrecisionBySixty(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'hours', 'time_precision' => '60']]);

        $this->assertSame('1', $this->service->getTimeStep());
    }

    public function testGetTimeStepForDaysUnitDividesPrecisionByOneThousandFourHundredForty(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'days', 'time_precision' => '1440']]);

        $this->assertSame('1', $this->service->getTimeStep());
    }

    public function testGetTimeStepDefaultsToRawPrecisionForMinutesUnit(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'minutes', 'time_precision' => '15']]);

        $this->assertSame('15', $this->service->getTimeStep());
    }

    public function testGetTimeUnitLabelForEachKnownUnit(): void
    {
        foreach (['seconds', 'hours', 'days', 'minutes'] as $unit) {
            $this->service->clearCache();
            $this->settingModelMock = $this->createMock(Setting::class);
            $this->service = new SettingsService($this->settingModelMock);
            $this->configureSettings(['general' => ['time_unit' => $unit]]);

            $this->assertSame($unit, $this->service->getTimeUnitLabel());
        }
    }

    public function testGetTimeUnitLabelDefaultsToMinutesForUnknownUnit(): void
    {
        $this->configureSettings(['general' => ['time_unit' => 'fortnights']]);

        $this->assertSame('minutes', $this->service->getTimeUnitLabel());
    }

    public function testGetResultsPerPageReturnsConfiguredInteger(): void
    {
        $this->configureSettings(['general' => ['results_per_page' => '100']]);

        $this->assertSame(100, $this->service->getResultsPerPage());
    }

    public function testGetDateFormatReturnsConfiguredValue(): void
    {
        $this->configureSettings(['general' => ['date_format' => 'd-m-Y']]);

        $this->assertSame('d-m-Y', $this->service->getDateFormat());
    }

    public function testGetDefaultTimezoneReturnsConfiguredValue(): void
    {
        $this->configureSettings(['general' => ['default_timezone' => 'UTC']]);

        $this->assertSame('UTC', $this->service->getDefaultTimezone());
    }

    public function testGetAutosaveIntervalReturnsConfiguredValue(): void
    {
        $this->configureSettings(['general' => ['autosave_interval' => '30']]);

        $this->assertSame(30, $this->service->getAutosaveInterval());
    }

    public function testGetSessionTimeoutReturnsConfiguredValue(): void
    {
        $this->configureSettings(['general' => ['session_timeout' => '1800']]);

        $this->assertSame(1800, $this->service->getSessionTimeout());
    }

    public function testFormatDateReturnsEmptyStringForEmptyInput(): void
    {
        $this->configureSettings([]);

        $this->assertSame('', $this->service->formatDate(''));
        $this->assertSame('', $this->service->formatDate(null));
    }

    public function testFormatDateConvertsUtcDateTimeInstanceToConfiguredTimezone(): void
    {
        $this->configureSettings([
            'general' => ['date_format' => 'Y-m-d H:i', 'default_timezone' => 'America/New_York'],
        ]);

        $date = new DateTime('2024-01-01 12:00:00', new DateTimeZone('UTC'));

        $result = $this->service->formatDate($date);

        $this->assertSame('2024-01-01 07:00', $result);
    }

    public function testFormatDateLeavesNonUtcDateTimeInstanceTimezoneUnchanged(): void
    {
        $this->configureSettings([
            'general' => ['date_format' => 'Y-m-d H:i', 'default_timezone' => 'America/New_York'],
        ]);

        $date = new DateTime('2024-01-01 12:00:00', new DateTimeZone('Europe/London'));

        $result = $this->service->formatDate($date);

        $this->assertSame('2024-01-01 12:00', $result);
    }

    public function testFormatDateParsesStringAndConvertsToConfiguredTimezone(): void
    {
        $this->configureSettings([
            'general' => ['date_format' => 'Y-m-d H:i', 'default_timezone' => 'America/New_York'],
        ]);

        $result = $this->service->formatDate('2024-01-01 12:00:00 UTC');

        $this->assertSame('2024-01-01 07:00', $result);
    }

    public function testFormatDateFallsBackToOriginalValueWhenStringIsUnparseable(): void
    {
        $this->configureSettings([
            'general' => ['date_format' => 'Y-m-d', 'default_timezone' => 'UTC'],
        ]);

        $result = $this->service->formatDate('not-a-real-date-xyz');

        $this->assertSame('not-a-real-date-xyz', $result);
    }

    public function testFormatDateCastsNonStringNonDateTimeValueToString(): void
    {
        $this->configureSettings([]);

        $this->assertSame('12345', $this->service->formatDate(12345));
    }

    public function testGetCurrentDateTimeUsesConfiguredFormatAndTimezone(): void
    {
        $this->configureSettings([
            'general' => ['date_format' => 'Y', 'default_timezone' => 'UTC'],
        ]);

        $result = $this->service->getCurrentDateTime();

        $this->assertSame((new DateTime('now', new DateTimeZone('UTC')))->format('Y'), $result);
    }

    public function testGetCurrentDateTimeFallsBackToDefaultFormatWhenTimezoneIsInvalid(): void
    {
        $this->configureSettings([
            'general' => ['date_format' => 'Y', 'default_timezone' => 'Not/A/Real/Zone'],
        ]);

        $result = $this->service->getCurrentDateTime();

        $this->assertSame(date('Y'), $result);
    }

    /**
     * The deprecated singleton accessor still constructs a real Setting and
     * Database instance when none is injected. That path only builds
     * credential/config state (App\Core\Database never opens a PDO
     * connection until a query is executed), so it stays safe without a
     * live MySQL server as long as no settings are actually fetched here.
     */
    public function testGetInstanceReturnsSameSingletonInstanceOnEachCall(): void
    {
        $reflection = new ReflectionClass(SettingsService::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setValue(null, null);

        try {
            $first = SettingsService::getInstance();
            $second = SettingsService::getInstance();

            $this->assertInstanceOf(SettingsService::class, $first);
            $this->assertSame($first, $second);
        } finally {
            $instanceProperty->setValue(null, null);
        }
    }
}
