<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\SettingsController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Testable subclass: capture render()/redirect*() instead of the real
 * side effects (header()+exit, HTML include).
 */
final class SettingsControllerTestable extends SettingsController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;
    public ?string $redirectType = null;

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirect(string $url): never
    {
        $this->redirectUrl = $url;
        $this->redirectType = 'plain';

        throw new RuntimeException('halt:redirect');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new RuntimeException('halt:error');
    }
}

/**
 * Behavioural tests for SettingsController.
 *
 * SettingsController does not accept AuthMiddleware via its own constructor
 * (only ?Setting), so parent::__construct() always builds a real
 * AuthMiddleware internally. That construction alone is harmless (it just
 * assigns `new User()` and SettingsService::getInstance()`, never queries
 * anything), so it's allowed to happen for real; but index()/update() call
 * `$this->authMiddleware->hasAnyPermission(...)` DIRECTLY (not through the
 * overridable requirePermission() wrapper this codebase's other controllers
 * use), so BaseController's protected $authMiddleware property is swapped
 * for a mock via reflection immediately after construction -- the only seam
 * available here. SettingsService/LoggerService singletons are seeded with
 * mocks before construction (CLAUDE.md-documented pattern) so the real
 * AuthMiddleware's internal `SettingsService::getInstance()` call and
 * BaseController's own singleton lookups never touch a real Setting/DB/log
 * file.
 *
 * Neither index() nor update() branches on hasAnyPermission()'s return
 * value (production relies entirely on AuthMiddleware's own exit-on-denial
 * side effect, already covered by AuthMiddlewareTest) -- so what's actually
 * verified here is that the controller asks for the *correct* permission
 * set, not the pass/fail branching itself.
 */
#[CoversClass(SettingsController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
final class SettingsControllerTest extends TestCase
{
    /** @var Setting&\PHPUnit\Framework\MockObject\MockObject */
    private $settingModel;
    /** @var AuthMiddleware&\PHPUnit\Framework\MockObject\MockObject */
    private $authMiddleware;
    /** @var SettingsService&\PHPUnit\Framework\MockObject\MockObject */
    private $settingsServiceSingleton;
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingModel = $this->createMock(Setting::class);
        $this->authMiddleware = $this->createMock(AuthMiddleware::class);
        $this->settingsServiceSingleton = $this->createMock(SettingsService::class);

        $this->seedSingleton(SettingsService::class, $this->settingsServiceSingleton);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));

        $this->originalEnv = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? null,
            'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? null,
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    private function controller(): SettingsControllerTestable
    {
        $c = new SettingsControllerTestable($this->settingModel);

        (new ReflectionClass(BaseController::class))->getProperty('authMiddleware')->setValue($c, $this->authMiddleware);

        return $c;
    }

    private function invokeSanitize(SettingsControllerTestable $c, string $key, mixed $value): string
    {
        return (new ReflectionMethod($c, 'sanitizeSettingValue'))->invoke($c, $key, $value);
    }

    // -------------------------------------------------------------------- index()

    public function testIndexChecksTheFullSettingsPermissionSet(): void
    {
        $this->settingModel->method('getAllGrouped')->willReturn([]);
        $this->authMiddleware->expects($this->once())
            ->method('hasAnyPermission')
            ->with([
                'view_settings', 'edit_settings', 'edit_security_settings',
                'manage_sprint_settings', 'manage_task_settings',
                'manage_milestone_settings', 'manage_project_settings',
            ]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Settings/index', $c->renderedView);
    }

    public function testIndexMergesStoredSettingsOverDefaultsWithoutOverwritingThem(): void
    {
        $this->settingModel->method('getAllGrouped')->willReturn([
            'general' => ['results_per_page' => '50'],
        ]);

        $c = $this->controller();
        $c->index('GET', []);

        $settings = $c->renderedData['settings'];
        $this->assertSame('50', $settings['general']['results_per_page']);
        $this->assertSame('Y-m-d', $settings['general']['date_format']);
        $this->assertSame('task', $settings['projects']['default_task_type']);
        $this->assertSame('Lax', $settings['security']['session_samesite']);
        $this->assertSame([], $c->renderedData['errors']);
        $this->assertSame('http://localhost:8081', $c->renderedData['pmaUrl']);
    }

    public function testIndexIsDevEnvTrueWhenAppEnvIsNotProduction(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'false';
        $this->settingModel->method('getAllGrouped')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertTrue($c->renderedData['isDevEnv']);
    }

    public function testIndexIsDevEnvFalseWhenProductionAndNotDebug(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'false';
        $this->settingModel->method('getAllGrouped')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertFalse($c->renderedData['isDevEnv']);
    }

    public function testIndexIsDevEnvTrueWhenProductionButDebugEnabled(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'true';
        $this->settingModel->method('getAllGrouped')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertTrue($c->renderedData['isDevEnv']);
    }

    public function testIndexOnExceptionLogsAndRedirectsToDashboard(): void
    {
        $this->settingModel->method('getAllGrouped')->willThrowException(new RuntimeException('db exploded'));

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('An error occurred while loading settings.', $c->redirectMessage);
    }

    // ------------------------------------------------------------------- update()

    public function testUpdateChecksTheEditableSettingsPermissionSet(): void
    {
        $_SESSION['csrf_token'] = 'good-token';
        $_SESSION['user']['permissions'] = [];
        $this->authMiddleware->expects($this->once())
            ->method('hasAnyPermission')
            ->with([
                'edit_settings', 'edit_security_settings',
                'manage_sprint_settings', 'manage_task_settings',
                'manage_milestone_settings', 'manage_project_settings',
            ]);

        $c = $this->controller();

        try {
            $c->update('POST', ['csrf_token' => 'good-token']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }
    }

    public function testUpdateWithMissingCsrfTokenRedirectsWithError(): void
    {
        $_SESSION['csrf_token'] = 'good-token';

        $c = $this->controller();

        try {
            $c->update('POST', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/settings', $c->redirectUrl);
        $this->assertSame('Invalid security token. Please try again.', $c->redirectMessage);
    }

    public function testUpdateWithMismatchedCsrfTokenRedirectsWithError(): void
    {
        $_SESSION['csrf_token'] = 'good-token';

        $c = $this->controller();

        try {
            $c->update('POST', ['csrf_token' => 'wrong-token']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('Invalid security token. Please try again.', $c->redirectMessage);
    }

    public function testUpdateOnlyPersistsCategoriesTheUserHasPermissionFor(): void
    {
        $_SESSION['csrf_token'] = 'good-token';
        $_SESSION['user']['permissions'] = ['edit_settings'];

        $this->settingModel->expects($this->once())
            ->method('updateSetting')
            ->with('general', 'date_format', $this->anything());
        $this->settingsServiceSingleton->expects($this->once())->method('clearCache');

        $c = $this->controller();

        try {
            $c->update('POST', [
                'csrf_token' => 'good-token',
                'general' => ['date_format' => 'd/m/Y'],
                // 'security' requires edit_security_settings, which the user lacks.
                'security' => ['session_samesite' => 'Strict'],
            ]);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/settings', $c->redirectUrl);
        $this->assertSame('plain', $c->redirectType);
        $this->assertSame('Settings updated successfully.', $_SESSION['success']);
    }

    public function testUpdateSanitizesEachValueBeforePersisting(): void
    {
        $_SESSION['csrf_token'] = 'good-token';
        $_SESSION['user']['permissions'] = ['edit_settings'];

        $this->settingModel->expects($this->once())
            ->method('updateSetting')
            ->with('general', 'time_unit', 'minutes'); // 'bogus' sanitizes to the 'minutes' default

        $c = $this->controller();

        try {
            $c->update('POST', [
                'csrf_token' => 'good-token',
                'general' => ['time_unit' => 'bogus'],
            ]);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }
    }

    public function testUpdateOnExceptionSetsFlashErrorButStillRedirects(): void
    {
        $_SESSION['csrf_token'] = 'good-token';
        $_SESSION['user']['permissions'] = ['edit_settings'];
        $this->settingModel->method('updateSetting')->willThrowException(new RuntimeException('write failed'));

        $c = $this->controller();

        try {
            $c->update('POST', [
                'csrf_token' => 'good-token',
                'general' => ['date_format' => 'd/m/Y'],
            ]);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/settings', $c->redirectUrl);
        $this->assertSame('An error occurred while updating settings.', $_SESSION['error']);
        $this->assertArrayNotHasKey('success', $_SESSION);
    }

    public function testUpdateIgnoresNonArrayCategoryValues(): void
    {
        $_SESSION['csrf_token'] = 'good-token';
        $_SESSION['user']['permissions'] = ['edit_settings'];
        $this->settingModel->expects($this->never())->method('updateSetting');

        $c = $this->controller();

        try {
            // 'general' present but not an array -> is_array() guard skips it.
            $c->update('POST', ['csrf_token' => 'good-token', 'general' => 'not-an-array']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }
    }

    // ----------------------------------------------------------------- pmaStatus()

    public function testPmaStatusReturns404OutsideDevEnv(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'false';

        $c = $this->controller();

        ob_start();
        $c->pmaStatus('GET', []);
        $body = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertSame(['error' => 'not available'], json_decode($body, true));
    }

    public function testPmaStatusReturnsRunningPayloadInDevEnv(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'true';

        $c = $this->controller();

        ob_start();
        $c->pmaStatus('GET', []);
        $body = ob_get_clean();

        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('running', $decoded);
        $this->assertIsBool($decoded['running']);
        $this->assertSame('http://localhost:8081', $decoded['url']);
        $this->assertSame('composer pma', $decoded['launchCommand']);
    }

    // ---------------------------------------------------------- sanitizeSettingValue()

    public function testSanitizeTimeUnitAcceptsAllowedValuesAndFallsBackOtherwise(): void
    {
        $c = $this->controller();

        $this->assertSame('hours', $this->invokeSanitize($c, 'time_unit', 'hours'));
        $this->assertSame('minutes', $this->invokeSanitize($c, 'time_unit', 'fortnights'));
    }

    public function testSanitizeTimePrecisionClampsBetween1And60(): void
    {
        $c = $this->controller();

        $this->assertSame('1', $this->invokeSanitize($c, 'time_precision', '-5'));
        $this->assertSame('60', $this->invokeSanitize($c, 'time_precision', '999'));
        $this->assertSame('15', $this->invokeSanitize($c, 'time_precision', '15'));
    }

    public function testSanitizeDefaultTaskTypeAcceptsAllowedValuesAndFallsBackOtherwise(): void
    {
        $c = $this->controller();

        $this->assertSame('bug', $this->invokeSanitize($c, 'default_task_type', 'bug'));
        $this->assertSame('task', $this->invokeSanitize($c, 'default_task_type', 'nonsense'));
    }

    public function testSanitizeDefaultPriorityAcceptsAllowedValuesAndFallsBackOtherwise(): void
    {
        $c = $this->controller();

        $this->assertSame('high', $this->invokeSanitize($c, 'default_priority', 'high'));
        $this->assertSame('medium', $this->invokeSanitize($c, 'default_priority', 'urgent'));
    }

    public function testSanitizeBooleanLikeKeysAcceptOnlyZeroOrOne(): void
    {
        $c = $this->controller();

        foreach (['auto_assign_creator', 'story_points_enabled', 'enable_csp', 'log_security_events'] as $key) {
            $this->assertSame('1', $this->invokeSanitize($c, $key, '1'));
            $this->assertSame('0', $this->invokeSanitize($c, $key, '0'));
            $this->assertSame('0', $this->invokeSanitize($c, $key, 'yes'));
        }
    }

    public function testSanitizeMilestoneNotificationDaysClampsBetween1And30(): void
    {
        $c = $this->controller();

        $this->assertSame('1', $this->invokeSanitize($c, 'milestone_notification_days', '0'));
        $this->assertSame('30', $this->invokeSanitize($c, 'milestone_notification_days', '365'));
    }

    public function testSanitizeDefaultSprintLengthClampsBetween1And30(): void
    {
        $c = $this->controller();

        $this->assertSame('1', $this->invokeSanitize($c, 'default_sprint_length', '0'));
        $this->assertSame('30', $this->invokeSanitize($c, 'default_sprint_length', '365'));
    }

    public function testSanitizeEstimationMethodAcceptsAllowedValuesAndFallsBackOtherwise(): void
    {
        $c = $this->controller();

        $this->assertSame('story_points', $this->invokeSanitize($c, 'estimation_method', 'story_points'));
        $this->assertSame('hours', $this->invokeSanitize($c, 'estimation_method', 'guesswork'));
    }

    public function testSanitizeTeamCapacityHoursClampsBetween1And200(): void
    {
        $c = $this->controller();

        $this->assertSame('1', $this->invokeSanitize($c, 'team_capacity_hours', '0'));
        $this->assertSame('200', $this->invokeSanitize($c, 'team_capacity_hours', '999'));
    }

    public function testSanitizeTeamCapacityStoryPointsClampsBetween1And100(): void
    {
        $c = $this->controller();

        $this->assertSame('1', $this->invokeSanitize($c, 'team_capacity_story_points', '0'));
        $this->assertSame('100', $this->invokeSanitize($c, 'team_capacity_story_points', '999'));
    }

    public function testSanitizeWorkingDaysFiltersInvalidDaysAndFallsBackWhenNoneValid(): void
    {
        $c = $this->controller();

        $this->assertSame(
            'monday,friday',
            $this->invokeSanitize($c, 'working_days', 'monday,funday,friday')
        );
        $this->assertSame(
            'monday,tuesday,wednesday,thursday,friday',
            $this->invokeSanitize($c, 'working_days', 'funday,someday')
        );
    }

    public function testSanitizeCsrfTokenLifetimeClampsBetween30MinutesAnd24Hours(): void
    {
        $c = $this->controller();

        $this->assertSame('1800', $this->invokeSanitize($c, 'csrf_token_lifetime', '10'));
        $this->assertSame('86400', $this->invokeSanitize($c, 'csrf_token_lifetime', '999999'));
    }

    public function testSanitizeMaxInputSizeClampsBetween256KbAnd10Mb(): void
    {
        $c = $this->controller();

        $this->assertSame('262144', $this->invokeSanitize($c, 'max_input_size', '10'));
        $this->assertSame('10485760', $this->invokeSanitize($c, 'max_input_size', '999999999'));
    }

    public function testSanitizeRateLimitAttemptsClampsBetween0And1000(): void
    {
        $c = $this->controller();

        $this->assertSame('0', $this->invokeSanitize($c, 'rate_limit_attempts', '-5'));
        $this->assertSame('1000', $this->invokeSanitize($c, 'rate_limit_attempts', '999999'));
    }

    public function testSanitizeSessionSamesiteAcceptsAllowedValuesAndFallsBackOtherwise(): void
    {
        $c = $this->controller();

        $this->assertSame('Strict', $this->invokeSanitize($c, 'session_samesite', 'Strict'));
        $this->assertSame('Lax', $this->invokeSanitize($c, 'session_samesite', 'Bogus'));
    }

    public function testSanitizeCspPolicyAcceptsAllowedValuesAndFallsBackOtherwise(): void
    {
        $c = $this->controller();

        $this->assertSame('strict', $this->invokeSanitize($c, 'csp_policy', 'strict'));
        $this->assertSame('moderate', $this->invokeSanitize($c, 'csp_policy', 'bogus'));
    }

    public function testSanitizeAllowedRedirectDomainsFiltersInvalidEntries(): void
    {
        $c = $this->controller();

        $this->assertSame(
            "example.com\nfoo.example.org",
            $this->invokeSanitize($c, 'allowed_redirect_domains', "example.com\nnot a domain\nfoo.example.org")
        );
    }

    public function testSanitizeUnknownKeyFallsBackToHtmlspecialchars(): void
    {
        $c = $this->controller();

        $this->assertSame(
            '&lt;script&gt;',
            $this->invokeSanitize($c, 'some_unknown_setting', '<script>')
        );
    }
}
