<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\FavoritesController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Favorite;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural tests for FavoritesController.
 *
 * UNCOVERABLE: every one of FavoritesController's five public methods
 * (index, add, remove, updateOrder, check) terminates EVERY branch --
 * success and failure alike -- by calling the static Response::json(),
 * whose final statement is a bare `exit;` (src/Core/Response.php). exit is a
 * language construct, not an overridable/mockable call site (FavoritesController
 * calls `Response::json(...)` directly, not through any DI seam or
 * overridable protected method), and it terminates the whole PHPUnit
 * process, not just the current test -- there is no seam like the
 * redirect*() override pattern used for the other five controllers in this
 * batch. Per the task brief this is reported rather than chased with
 * process isolation, exactly like the pre-existing ~69% cap documented for
 * src/Core/Response and src/Core/ApiResponse in CLAUDE.md.
 *
 * What IS safely testable without invoking any public method:
 * - the constructor's dependency injection (?Favorite $favoriteModel), and
 * - the private validateCsrfToken() helper, which does not itself call
 *   Response::json() or exit -- exercised directly via ReflectionMethod for
 *   every combination of missing/present/matching/mismatched tokens.
 */
#[CoversClass(FavoritesController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
final class FavoritesControllerTest extends TestCase
{
    /** @var Favorite&\PHPUnit\Framework\MockObject\MockObject */
    private $favoriteModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->favoriteModel = $this->createMock(Favorite::class);

        $this->seedSingleton(SettingsService::class, $this->createMock(SettingsService::class));
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
    }

    protected function tearDown(): void
    {
        unset($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'], $_POST['csrf_token']);
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    private function controller(): FavoritesController
    {
        return new FavoritesController($this->favoriteModel);
    }

    private function invokeValidateCsrfToken(FavoritesController $c): bool
    {
        return (new ReflectionMethod($c, 'validateCsrfToken'))->invoke($c);
    }

    public function testConstructorAcceptsAnInjectedFavoriteModel(): void
    {
        $c = $this->controller();

        $this->assertInstanceOf(FavoritesController::class, $c);
    }

    public function testValidateCsrfTokenFalseWhenSessionTokenMissing(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'abc';
        unset($_SESSION['csrf_token']);

        $this->assertFalse($this->invokeValidateCsrfToken($this->controller()));
    }

    public function testValidateCsrfTokenFalseWhenRequestTokenMissing(): void
    {
        // Leaving BOTH $_SERVER['HTTP_X_CSRF_TOKEN'] and $_POST['csrf_token']
        // unset would fall through to the third `??` branch, getallheaders()
        // -- a function the CLI SAPI (which PHPUnit runs under) does not
        // define at all, unlike Apache/CGI/FPM. Setting $_POST['csrf_token']
        // to an empty string still represents "no token provided" (empty()
        // treats it the same as missing) while avoiding that undefined-function
        // fatal, since `??` only falls through on null/unset, not on ''.
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST['csrf_token'] = '';
        $_SESSION['csrf_token'] = 'abc';

        $this->assertFalse($this->invokeValidateCsrfToken($this->controller()));
    }

    public function testValidateCsrfTokenFalseWhenTokensMismatch(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'abc';
        $_SESSION['csrf_token'] = 'xyz';

        $this->assertFalse($this->invokeValidateCsrfToken($this->controller()));
    }

    public function testValidateCsrfTokenTrueWhenHeaderTokenMatchesSessionToken(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'matching-token';
        $_SESSION['csrf_token'] = 'matching-token';

        $this->assertTrue($this->invokeValidateCsrfToken($this->controller()));
    }

    public function testValidateCsrfTokenTrueWhenPostTokenMatchesSessionToken(): void
    {
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST['csrf_token'] = 'matching-token';
        $_SESSION['csrf_token'] = 'matching-token';

        $this->assertTrue($this->invokeValidateCsrfToken($this->controller()));
    }
}
