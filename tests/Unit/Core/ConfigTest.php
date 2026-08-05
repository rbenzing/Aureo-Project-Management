<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use App\Services\SettingsService;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Tests\Unit\Core\Support\ConfigBuiltinToggles;

require_once __DIR__ . '/Support/ConfigBuiltinToggles.php';
require_once __DIR__ . '/Support/ConfigBuiltinOverrides.php';

/**
 * Config::init() transitively calls the real (deprecated, no-DI)
 * SettingsService::getInstance(). Every test that reaches init() pre-seeds
 * that singleton with a mock via reflection so nothing here ever attempts a
 * real database connection, regardless of whether MySQL happens to be
 * reachable on the machine running the suite.
 */
#[CoversClass(Config::class)]
#[UsesClass(SettingsService::class)]
final class ConfigTest extends TestCase
{
    private array $envBackup = [];

    private string $originalTimezone;

    private string|false $originalLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
        $this->originalLocale = setlocale(LC_ALL, '0');

        foreach (['APP_DEBUG', 'DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD', 'TIMEZONE', 'PAGE_LIMIT'] as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['DB_HOST'] = '127.0.0.1:3306';
        $_ENV['DB_NAME'] = 'pms_test';
        $_ENV['DB_USERNAME'] = 'root';
        $_ENV['DB_PASSWORD'] = '';
        unset($_ENV['TIMEZONE'], $_ENV['PAGE_LIMIT']);

        ConfigBuiltinToggles::reset();
        $this->resetConfigState();
        $this->seedSettingsService($this->benignSettingsServiceMock());
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        ConfigBuiltinToggles::reset();
        $this->resetConfigState();
        $this->seedSettingsService(null);

        date_default_timezone_set($this->originalTimezone);
        if ($this->originalLocale !== false) {
            setlocale(LC_ALL, $this->originalLocale);
        }

        parent::tearDown();
    }

    private function resetConfigState(): void
    {
        $ref = new ReflectionClass(Config::class);

        $configProp = $ref->getProperty('config');
        $configProp->setValue(null, []);

        $initProp = $ref->getProperty('isInitialized');
        $initProp->setValue(null, false);
    }

    private function seedSettingsService(?SettingsService $service): void
    {
        $ref = new ReflectionClass(SettingsService::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $service);
    }

    private function benignSettingsServiceMock(): SettingsService
    {
        $mock = $this->createMock(SettingsService::class);
        $mock->method('getDefaultTimezone')->willReturn('UTC');
        $mock->method('getResultsPerPage')->willReturn(10);

        return $mock;
    }

    private function callPrivateStatic(string $method, array $args = [])
    {
        $ref = new ReflectionClass(Config::class);
        $m = $ref->getMethod($method);

        return $m->invokeArgs(null, $args);
    }

    // --- init() happy path / idempotency ---

    public function testInitPopulatesExpectedConfigurationKeys(): void
    {
        Config::init();

        $all = Config::all();

        $this->assertArrayHasKeys(
            ['debug', 'max_pages', 'timezone', 'domain', 'company_name', 'scheme', 'locale', 'currency_format', 'base_url'],
            $all
        );
        $this->assertTrue($all['debug']);
        $this->assertSame('http', $all['scheme']);
        $this->assertSame('aureo', $all['domain']);
        $this->assertSame('http://aureo', $all['base_url']);
    }

    public function testInitIsIdempotent(): void
    {
        Config::init();
        $first = Config::all();

        // A second call must short-circuit on $isInitialized rather than
        // re-running loadEnvironment()/validateEnvironment() again.
        Config::init();
        $second = Config::all();

        $this->assertSame($first, $second);
    }

    public function testInitUsesSettingsServiceForTimezoneAndPageLimitWhenAvailable(): void
    {
        $mock = $this->createMock(SettingsService::class);
        $mock->method('getDefaultTimezone')->willReturn('America/Chicago');
        $mock->method('getResultsPerPage')->willReturn(77);
        $this->seedSettingsService($mock);

        Config::init();

        $this->assertSame('America/Chicago', date_default_timezone_get());
        $this->assertSame(77, Config::all()['max_pages']);
    }

    public function testInitFallsBackToEnvironmentWhenSettingsServiceTimezoneLookupThrows(): void
    {
        $mock = $this->createMock(SettingsService::class);
        $mock->method('getDefaultTimezone')->willThrowException(new Exception('settings unavailable'));
        $mock->method('getResultsPerPage')->willReturn(5);
        $this->seedSettingsService($mock);

        Config::init();

        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function testInitFallsBackToDefaultPageLimitWhenSettingsServiceThrows(): void
    {
        $mock = $this->createMock(SettingsService::class);
        $mock->method('getDefaultTimezone')->willReturn('UTC');
        $mock->method('getResultsPerPage')->willThrowException(new Exception('settings unavailable'));
        $this->seedSettingsService($mock);

        Config::init();

        // No PAGE_LIMIT env var and no working settings service -> DEFAULTS['PAGE_LIMIT'].
        $this->assertSame(10, Config::all()['max_pages']);
    }

    public function testInitSkipsSettingsServiceEntirelyWhenClassUnavailable(): void
    {
        ConfigBuiltinToggles::$forceSettingsServiceMissing = true;

        Config::init();

        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame(10, Config::all()['max_pages']);
    }

    public function testInitThrowsWhenEnvFileMissing(): void
    {
        ConfigBuiltinToggles::$forceEnvFileMissing = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/\.env file not found at/');

        Config::init();
    }

    public function testValidateEnvironmentThrowsWhenRequiredVariableMissing(): void
    {
        unset($_ENV['DB_NAME'], $_ENV['DB_PASSWORD']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required environment variables: DB_NAME, DB_PASSWORD');

        // Call the private validator directly so Dotenv never runs and
        // silently repopulates DB_NAME/DB_PASSWORD from the real .env file.
        $this->callPrivateStatic('validateEnvironment');
    }

    // --- get()/set()/has() ---

    public function testSetThenGetIsCaseInsensitiveOnKey(): void
    {
        Config::set('Custom_Key', 'value-1');

        $this->assertSame('value-1', Config::get('CUSTOM_KEY'));
        $this->assertSame('value-1', Config::get('custom_key'));
        $this->assertTrue(Config::has('custom_key'));
    }

    public function testHasReturnsFalseForUnknownKey(): void
    {
        $this->assertFalse(Config::has('TOTALLY_UNKNOWN_KEY_XYZ'));
    }

    public function testGetFallsBackToDefaultsConstantWhenUnset(): void
    {
        $this->assertSame('UTC', Config::get('TIMEZONE'));
        $this->assertSame('http', Config::get('SCHEME'));
    }

    public function testGetReturnsSuppliedDefaultWhenKeyEntirelyMissing(): void
    {
        $this->assertSame('fallback', Config::get('TOTALLY_UNKNOWN_KEY_XYZ', 'fallback'));
        $this->assertNull(Config::get('TOTALLY_UNKNOWN_KEY_XYZ'));
    }

    public function testGetPrefersEnvOverSetConfigOverDefaults(): void
    {
        Config::set('PAGE_LIMIT', 5);
        $this->assertSame(5, Config::get('PAGE_LIMIT'));

        $_ENV['PAGE_LIMIT'] = '99';
        $this->assertSame('99', Config::get('PAGE_LIMIT'));

        unset($_ENV['PAGE_LIMIT']);
    }

    // --- isDebug() / isProduction() ---

    public function testIsDebugReadsAppDebugEnvVarWhenNotInitialized(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $this->assertTrue(Config::isDebug());
        $this->assertFalse(Config::isProduction());

        $_ENV['APP_DEBUG'] = 'false';
        $this->assertFalse(Config::isDebug());
        $this->assertTrue(Config::isProduction());
    }

    public function testIsDebugFallsBackToNonStringDefaultWhenAppDebugUnset(): void
    {
        unset($_ENV['APP_DEBUG']);

        // getEnvBoolean()'s $_ENV[$key] ?? $default leaves $value as the raw
        // (non-string) `false` default, exercising its is_string()-false
        // "return (bool) $value" branch rather than the string-parsing one.
        $this->assertFalse(Config::isDebug());
    }

    public function testIsDebugAcceptsVariousTruthyStringForms(): void
    {
        foreach (['1', 'yes', 'on', 'TRUE'] as $truthy) {
            $_ENV['APP_DEBUG'] = $truthy;
            $this->assertTrue(Config::isDebug(), "Expected '{$truthy}' to be treated as debug=true");
        }

        foreach (['0', 'no', 'off', ''] as $falsy) {
            $_ENV['APP_DEBUG'] = $falsy;
            $this->assertFalse(Config::isDebug(), "Expected '{$falsy}' to be treated as debug=false");
        }
    }

    public function testIsDebugPrefersInitializedConfigValueOverEnv(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        Config::init();

        // Flip the raw env var after init(); isDebug() should keep returning
        // the value baked into self::$config by initializeSettings().
        $_ENV['APP_DEBUG'] = 'false';

        $this->assertTrue(Config::isDebug());
    }

    // --- getErrorMessage() ---

    public function testGetErrorMessageIncludesDebugDetailsWhenDebugEnabled(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $exception = new Exception('boom');

        $message = Config::getErrorMessage($exception, 'Ctx::method', 'Something went wrong', 'entity-42');

        $this->assertStringStartsWith('DEBUG: boom', $message);
        $this->assertStringContainsString('ID: entity-42', $message);
    }

    public function testGetErrorMessageReturnsUserMessageWhenDebugDisabled(): void
    {
        $_ENV['APP_DEBUG'] = 'false';
        $exception = new Exception('boom');

        $message = Config::getErrorMessage($exception, 'Ctx::method', 'Something went wrong');

        $this->assertSame('Something went wrong', $message);
    }

    // --- SMTP / email getters ---

    public function testSmtpGettersReturnEnvironmentOverridesWhenSet(): void
    {
        $_ENV['SMTP_HOST'] = 'smtp.custom.test';
        $_ENV['SMTP_USERNAME'] = 'user@custom.test';
        $_ENV['SMTP_PASSWORD'] = 'secret';
        $_ENV['SMTP_PORT'] = '2525';
        $_ENV['SMTP_DEBUG'] = '2';
        $_ENV['EMAIL_FROM'] = 'from@custom.test';
        $_ENV['EMAIL_FROM_NAME'] = 'Custom App';

        try {
            $this->assertSame('smtp.custom.test', Config::getSmtpHost());
            $this->assertSame('user@custom.test', Config::getSmtpUsername());
            $this->assertSame('secret', Config::getSmtpPassword());
            $this->assertSame(2525, Config::getSmtpPort());
            $this->assertSame(2, Config::getSmtpDebug());
            $this->assertSame('from@custom.test', Config::getEmailFrom());
            $this->assertSame('Custom App', Config::getEmailFromName());
        } finally {
            unset(
                $_ENV['SMTP_HOST'],
                $_ENV['SMTP_USERNAME'],
                $_ENV['SMTP_PASSWORD'],
                $_ENV['SMTP_PORT'],
                $_ENV['SMTP_DEBUG'],
                $_ENV['EMAIL_FROM'],
                $_ENV['EMAIL_FROM_NAME']
            );
        }
    }

    public function testSmtpGettersReturnHardcodedDefaultsWhenUnset(): void
    {
        $keys = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_PORT', 'SMTP_DEBUG', 'EMAIL_FROM', 'EMAIL_FROM_NAME'];
        $backup = [];
        foreach ($keys as $key) {
            $backup[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key]);
        }

        try {
            $this->assertSame('localhost', Config::getSmtpHost());
            $this->assertSame('', Config::getSmtpUsername());
            $this->assertSame('', Config::getSmtpPassword());
            $this->assertSame(587, Config::getSmtpPort());
            $this->assertSame(0, Config::getSmtpDebug());
            $this->assertSame('noreply@example.com', Config::getEmailFrom());
            $this->assertSame('Application', Config::getEmailFromName());
        } finally {
            foreach ($backup as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    /**
     * @param array<int, string> $keys
     * @param array<string, mixed> $array
     */
    private function assertArrayHasKeys(array $keys, array $array): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    // --- basePath()/setBasePath() ---

    public function testBasePathDefaultsToEmptyString(): void
    {
        Config::setBasePath('');

        $this->assertSame('', Config::basePath());
    }

    public function testBasePathRoundTripsSubdirectoryMount(): void
    {
        Config::setBasePath('/aureo');

        $this->assertSame('/aureo', Config::basePath());

        Config::setBasePath('');
    }

    /**
     * A trailing slash would produce '//assets/...' once asset() concatenates.
     */
    public function testBasePathStripsTrailingSlash(): void
    {
        Config::setBasePath('/aureo/');

        $this->assertSame('/aureo', Config::basePath());

        Config::setBasePath('');
    }
}
