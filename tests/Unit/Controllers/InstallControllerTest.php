<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\InstallController;
use App\Core\InstallGate;
use App\Services\ExposureProbe;
use App\Services\InstallerService;
use App\Services\PreflightService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Captures render()/redirect() instead of performing them: the real redirect
 * is header()+exit, which would kill the runner, and the real render() emits
 * HTML, which trips beStrictAboutOutputDuringTests.
 *
 * Only the FIRST redirect is recorded. A controller that redirects and then
 * falls through to a trailing error redirect would otherwise be reported as
 * having failed when it actually succeeded.
 */
final class TestableInstallController extends InstallController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirect(string $url): never
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
        }

        throw new RuntimeException('halt:redirect');
    }
}

#[CoversClass(InstallController::class)]
#[UsesClass(InstallGate::class)]
#[UsesClass(InstallerService::class)]
#[UsesClass(PreflightService::class)]
#[UsesClass(ExposureProbe::class)]
final class InstallControllerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_POST = [];
        $_SERVER['HTTP_HOST'] = 'example.test';

        $this->root = sys_get_temp_dir() . '/aureo-install-ctl-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];

        $this->removeTree($this->root);

        parent::tearDown();
    }

    private function removeTree(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }

        rmdir($path);
    }

    private function controller(
        ?PreflightService $preflight = null,
        ?ExposureProbe $probe = null
    ): TestableInstallController {
        return new TestableInstallController(
            $this->root,
            '',
            $preflight ?? new PreflightService('8.2.0', static fn (string $e): bool => true, static fn (string $p): bool => true, static fn (string $p): bool => true, '/tmp'),
            $probe ?? new ExposureProbe(static fn (string $url): ?int => 403),
            new InstallerService($this->root)
        );
    }

    /** Runs a handler that is expected to redirect, and returns the target. */
    private function expectRedirect(TestableInstallController $c, string $method, array $segments): string
    {
        try {
            $c->handle($method, $segments);
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'halt:redirect') {
                throw $e;
            }
        }

        $this->assertNotNull($c->redirectUrl, 'Expected a redirect but none was recorded');

        return $c->redirectUrl;
    }

    private function seedSession(array $overrides = []): void
    {
        $_SESSION['aureo_install'] = array_merge([
            'exposure_ok' => true,
            'db_host' => 'localhost:3306',
            'db_name' => 'aureo',
            'db_user' => 'aureo',
            'db_password' => 'secret',
            'users_before_install' => 0,
        ], $overrides);
    }

    private function withCsrf(TestableInstallController $c): void
    {
        $c->handle('GET', ['install']);
        $_POST['install_csrf'] = $_SESSION['aureo_install_csrf'];
    }

    public function testTheBareInstallRouteRendersPreflight(): void
    {
        $c = $this->controller();
        $c->handle('GET', ['install']);

        $this->assertSame('Install/preflight', $c->renderedView);
    }

    public function testAnUnknownStepRedirectsToTheStart(): void
    {
        $this->assertSame('/install', $this->expectRedirect($this->controller(), 'GET', ['install', 'nonsense']));
    }

    public function testPreflightPassesTheCheckListToTheView(): void
    {
        $c = $this->controller();
        $c->handle('GET', ['install']);

        $this->assertArrayHasKey('checks', $c->renderedData);
        $this->assertNotSame([], $c->renderedData['checks']);
    }

    public function testPreflightMarksFailuresSoTheViewCanBlockContinuing(): void
    {
        $failing = new PreflightService('8.1.0', static fn (string $e): bool => true, static fn (string $p): bool => true, static fn (string $p): bool => true, '/tmp');

        $c = $this->controller(preflight: $failing);
        $c->handle('GET', ['install']);

        $this->assertTrue($c->renderedData['blocked']);
    }

    public function testEveryRenderSuppliesACsrfTokenAndAssetBase(): void
    {
        $c = $this->controller();
        $c->handle('GET', ['install']);

        $this->assertNotSame('', $c->renderedData['csrf']);
        $this->assertArrayHasKey('assetBase', $c->renderedData);
    }

    public function testTheCsrfTokenIsStableAcrossRequests(): void
    {
        $c = $this->controller();
        $c->handle('GET', ['install']);
        $first = $c->renderedData['csrf'];

        $c->handle('GET', ['install']);

        $this->assertSame($first, $c->renderedData['csrf']);
    }

    public function testAPostWithoutAValidCsrfTokenIsRefused(): void
    {
        $c = $this->controller();
        $c->handle('GET', ['install']);
        $_POST = ['install_csrf' => 'wrong'];

        $this->assertSame('/install', $this->expectRedirect($c, 'POST', ['install', 'database']));
        $this->assertArrayNotHasKey('db_name', $_SESSION['aureo_install'] ?? []);
    }

    public function testTheExposureStepReportsAVerifiedHostAsSafe(): void
    {
        $c = $this->controller(probe: new ExposureProbe(static fn (string $url): ?int => 403));
        $c->handle('GET', ['install', 'exposure']);

        $this->assertSame('Install/exposure', $c->renderedView);
        $this->assertSame([], $c->renderedData['exposed']);
        $this->assertTrue($c->renderedData['verified']);
        $this->assertFalse($c->renderedData['blocked']);
    }

    /**
     * A readable .env blocks installation outright. It is the one preflight
     * result that no acknowledgement can override.
     */
    public function testAnExposedFileBlocksTheExposureStep(): void
    {
        $probe = new ExposureProbe(static fn (string $url): ?int => str_ends_with($url, '/.env') ? 200 : 403);

        $c = $this->controller(probe: $probe);
        $c->handle('GET', ['install', 'exposure']);

        $this->assertSame(['/.env'], $c->renderedData['exposed']);
        $this->assertTrue($c->renderedData['blocked']);
    }

    public function testAnUnverifiableHostIsNotBlockedButRequiresAcknowledgement(): void
    {
        $c = $this->controller(probe: new ExposureProbe(static fn (string $url): ?int => null));
        $c->handle('GET', ['install', 'exposure']);

        $this->assertFalse($c->renderedData['verified']);
        $this->assertFalse($c->renderedData['blocked']);
        $this->assertTrue($c->renderedData['needsAcknowledgement']);
    }

    public function testAcknowledgingAnUnverifiableHostAdvancesToTheDatabaseStep(): void
    {
        $c = $this->controller(probe: new ExposureProbe(static fn (string $url): ?int => null));
        $this->withCsrf($c);
        $_POST['acknowledge'] = '1';

        $this->assertSame('/install/database', $this->expectRedirect($c, 'POST', ['install', 'exposure']));
        $this->assertTrue($_SESSION['aureo_install']['exposure_ok']);
    }

    public function testNotAcknowledgingAnUnverifiableHostStaysOnTheExposureStep(): void
    {
        $c = $this->controller(probe: new ExposureProbe(static fn (string $url): ?int => null));
        $this->withCsrf($c);

        $this->assertSame('/install/exposure', $this->expectRedirect($c, 'POST', ['install', 'exposure']));
        $this->assertArrayNotHasKey('exposure_ok', $_SESSION['aureo_install'] ?? []);
    }

    /**
     * The security requirement in the design: nothing reaches disk before the
     * exposure step resolves. Asserted here rather than left to review,
     * because it is invisible in a passing happy path.
     */
    public function testTheDatabaseStepIsUnreachableBeforeTheExposureStepResolves(): void
    {
        $c = $this->controller();

        $this->assertSame('/install/exposure', $this->expectRedirect($c, 'GET', ['install', 'database']));
    }

    public function testTheAdministratorStepIsUnreachableWithoutDatabaseAnswers(): void
    {
        $_SESSION['aureo_install'] = ['exposure_ok' => true];

        $this->assertSame(
            '/install/database',
            $this->expectRedirect($this->controller(), 'GET', ['install', 'administrator'])
        );
    }

    public function testTheDatabaseStepRendersOnceExposureHasResolved(): void
    {
        $_SESSION['aureo_install'] = ['exposure_ok' => true];

        $c = $this->controller();
        $c->handle('GET', ['install', 'database']);

        $this->assertSame('Install/database', $c->renderedView);
    }

    public function testAWeakAdministratorPasswordIsRejected(): void
    {
        $this->seedSession();
        $c = $this->controller();
        $this->withCsrf($c);
        $_POST += [
            'email' => 'admin@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'password' => 'short',
            'password_confirm' => 'short',
        ];

        $this->assertSame('/install/administrator', $this->expectRedirect($c, 'POST', ['install', 'administrator']));
        $this->assertArrayNotHasKey('admin_password', $_SESSION['aureo_install']);
    }

    public function testMismatchedAdministratorPasswordsAreRejected(): void
    {
        $this->seedSession();
        $c = $this->controller();
        $this->withCsrf($c);
        $_POST += [
            'email' => 'admin@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'password' => 'Correct-Horse-9',
            'password_confirm' => 'Correct-Horse-8',
        ];

        $this->assertSame('/install/administrator', $this->expectRedirect($c, 'POST', ['install', 'administrator']));
        $this->assertArrayNotHasKey('admin_password', $_SESSION['aureo_install']);
    }

    public function testAnInvalidAdministratorEmailIsRejected(): void
    {
        $this->seedSession();
        $c = $this->controller();
        $this->withCsrf($c);
        $_POST += [
            'email' => 'not-an-email',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'password' => 'Correct-Horse-9',
            'password_confirm' => 'Correct-Horse-9',
        ];

        $this->assertSame('/install/administrator', $this->expectRedirect($c, 'POST', ['install', 'administrator']));
    }

    public function testValidAdministratorDetailsAdvanceToSettings(): void
    {
        $this->seedSession();
        $c = $this->controller();
        $this->withCsrf($c);
        $_POST += [
            'email' => 'admin@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'password' => 'Correct-Horse-9',
            'password_confirm' => 'Correct-Horse-9',
        ];

        $this->assertSame('/install/settings', $this->expectRedirect($c, 'POST', ['install', 'administrator']));
        $this->assertSame('admin@example.com', $_SESSION['aureo_install']['admin_email']);
    }

    public function testTheRefusedViewIsRenderedForARefusedGate(): void
    {
        $c = $this->controller();
        $c->refuse('Aureo is already installed.');

        $this->assertSame('Install/refused', $c->renderedView);
        $this->assertSame('Aureo is already installed.', $c->renderedData['reason']);
    }

    public function testRepeatedDatabasePostsAreRateLimited(): void
    {
        $_SESSION['aureo_install'] = ['exposure_ok' => true];
        $c = $this->controller();
        $this->withCsrf($c);
        $token = $_POST['install_csrf'];

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $_POST = ['install_csrf' => $token, 'db_host' => 'localhost', 'db_name' => 'x', 'db_user' => 'u', 'db_password' => 'p'];

            try {
                $c->handle('POST', ['install', 'database']);
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'halt:redirect') {
                    throw $e;
                }
            }
        }

        $this->assertSame('Install/refused', $c->renderedView);
    }

    public function testTheAssetBaseHonoursTheMountPoint(): void
    {
        $c = new TestableInstallController(
            $this->root,
            '/public',
            new PreflightService('8.2.0', static fn (string $e): bool => true, static fn (string $p): bool => true, static fn (string $p): bool => true, '/tmp'),
            new ExposureProbe(static fn (string $url): ?int => 403),
            new InstallerService($this->root)
        );

        $c->handle('GET', ['install']);

        $this->assertSame('/public', $c->renderedData['assetBase']);
    }
}
