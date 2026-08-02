<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\AuthController;
use App\Controllers\BaseController;
use App\Core\Config;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Marker exception thrown by the testable subclass's redirect*() overrides
 * in place of the real header()+exit. Kept as a distinct subclass (rather
 * than a bare RuntimeException) so a mocked collaborator sitting between a
 * genuine success-path redirect and the controller's own outer
 * catch(\Throwable) can recognise "this is our halt signal, not a real
 * error" and rethrow it untouched -- see the class docblock below for why
 * that's needed.
 */
final class Halt extends RuntimeException
{
}

/**
 * Testable subclass: capture render()/redirect*() instead of performing the
 * real side effects (header()+exit, HTML include) so branching logic can be
 * asserted without a DB, HTTP, or killing the PHPUnit process.
 */
final class AuthControllerTestable extends AuthController
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

        throw new Halt('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'success';

        throw new Halt('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new Halt('halt:error');
    }
}

/**
 * Behavioural tests for AuthController.
 *
 * AuthController takes AuthMiddleware/User/SecurityService via constructor DI,
 * so those are always mocked directly -- no reflection needed for them.
 * BaseController::__construct() still reaches SettingsService::getInstance()
 * and LoggerService::getInstance() directly, so those process-wide singletons
 * are seeded with mocks via reflection before construction and reset to null
 * afterward (see CLAUDE.md and the established AuthMiddlewareTest/TimeTest
 * pattern), avoiding any real Setting/Database/log-file access.
 *
 * login()/register()/resetPassword()/forgotPassword() construct a real
 * Validator (not injectable). Validator::__construct() always calls
 * Database::getInstance(), so the Database singleton is also seeded with a
 * mock. register()'s 'unique:users,email' rule additionally issues a real
 * query through that mock when the email field is non-empty, so the mock's
 * executeQuery()/fetchColumn() are configured to simulate "no existing row".
 *
 * resetPassword()'s success branch calls redirectWithSuccess() from inside
 * the SAME try block that AuthController wraps with catch(\Throwable) --
 * since the override above must throw to halt execution (PHP enforces that
 * a `never`-returning method's override also either throws or exits), that
 * throw is intercepted by the controller's own catch(\Throwable), which
 * would normally reformat it via $this->securityService->handleError() and
 * issue a second, different redirect. The mocked handleError() is
 * configured in that one test to recognise the Halt marker and rethrow it
 * untouched, so the originally-captured success url/message survive.
 *
 * UNCOVERABLE PATHS (documented, not chased):
 * - login() success branch and activate()'s GET-valid-token success branch
 *   both call the non-injectable SessionMiddleware::saveSession(), which
 *   unconditionally calls session_regenerate_id(true) and issues real
 *   Database queries via its own private static cache. session_regenerate_id()
 *   raises E_WARNING ("session is not active") because this CLI test process
 *   never calls session_start() (matching bootstrap.php / AuthMiddlewareTest's
 *   convention of never starting a real session) -- fatal under
 *   failOnWarning=true. Every other login()/activate() branch is covered.
 * - logout() calls the non-injectable SessionMiddleware::destroySession(),
 *   which unconditionally calls session_destroy() -- same "session not
 *   active" warning problem, with no seam to inject a fake session state.
 *   Left entirely uncovered rather than risk order-dependent flakiness.
 * - register()'s and forgotPassword()'s success branches call the
 *   non-injectable, non-mockable static Email::sendActivationEmail() /
 *   sendPasswordResetEmail(), which construct a real PHPMailer/SMTP client
 *   and attempt a real network connection. Left uncovered; every validation
 *   failure and Throwable-catch branch that returns before reaching Email is
 *   covered instead.
 */
#[CoversClass(AuthController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Validator::class)]
final class AuthControllerTest extends TestCase
{
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var SecurityService&\PHPUnit\Framework\MockObject\MockObject */
    private $securityService;
    /** @var AuthMiddleware&\PHPUnit\Framework\MockObject\MockObject */
    private $authMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel = $this->createMock(User::class);
        $this->securityService = $this->createMock(SecurityService::class);
        $this->authMiddleware = $this->createMock(AuthMiddleware::class);

        $this->seedSingleton(SettingsService::class, $this->createMock(SettingsService::class));
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(Database::class, $this->mockDatabaseAllowingUnique());
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);
        $this->seedSingleton(Database::class, null);

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    /**
     * A Database mock whose executeQuery() always returns a statement whose
     * fetchColumn() is 0, i.e. "unique:users,email" never finds an existing row.
     */
    private function mockDatabaseAllowingUnique(): Database
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($stmt);

        return $db;
    }

    private function controller(): AuthControllerTestable
    {
        return new AuthControllerTestable($this->authMiddleware, $this->userModel, $this->securityService);
    }

    private function activeUser(string $password = 'Sup3r$ecret!'): \stdClass
    {
        $u = new \stdClass();
        $u->id = 42;
        $u->first_name = 'Ada';
        $u->last_name = 'Lovelace';
        $u->email = 'ada@example.com';
        $u->phone = null;
        $u->company_id = 1;
        $u->is_active = true;
        $u->password_hash = password_hash($password, PASSWORD_ARGON2ID);

        return $u;
    }

    // ------------------------------------------------------------ loginForm()

    public function testLoginFormRendersLoginViewWithCompanyName(): void
    {
        $c = $this->controller();
        $c->loginForm('GET', []);

        $this->assertSame('Auth/login', $c->renderedView);
        $this->assertSame('Aureo', $c->renderedData['companyName']);
    }

    // ----------------------------------------------------------------- login()

    public function testLoginNonPostDelegatesToLoginForm(): void
    {
        $c = $this->controller();
        $c->login('GET', []);

        $this->assertSame('Auth/login', $c->renderedView);
        $this->assertNull($c->redirectUrl);
    }

    public function testLoginPostWithMissingFieldsRedirectsWithValidationError(): void
    {
        $c = $this->controller();

        try {
            $c->login('POST', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            $this->assertSame('halt:error', $e->getMessage());
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
        $this->assertStringContainsString('required', $c->redirectMessage);
    }

    public function testLoginPostWithUnknownEmailRejectsInvalidCredentials(): void
    {
        $this->userModel->method('findByEmail')->willReturn(null);

        $c = $this->controller();

        try {
            $c->login('POST', ['email' => 'nope@example.com', 'password' => 'whatever']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('Invalid email or password', $c->redirectMessage);
    }

    public function testLoginPostWithWrongPasswordRejectsInvalidCredentials(): void
    {
        $this->userModel->method('findByEmail')->willReturn($this->activeUser('correct-password'));

        $c = $this->controller();

        try {
            $c->login('POST', ['email' => 'ada@example.com', 'password' => 'wrong-password']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('Invalid email or password', $c->redirectMessage);
    }

    public function testLoginPostWithInactiveAccountIsRejected(): void
    {
        $user = $this->activeUser('correct-password');
        $user->is_active = false;
        $this->userModel->method('findByEmail')->willReturn($user);

        $c = $this->controller();

        try {
            $c->login('POST', ['email' => 'ada@example.com', 'password' => 'correct-password']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame(
            'Account not activated. Please check your email for activation instructions',
            $c->redirectMessage
        );
    }

    public function testLoginPostWithThrowableDelegatesToSecurityServiceHandleError(): void
    {
        $this->userModel->method('findByEmail')->willThrowException(new RuntimeException('db down'));
        $this->securityService->expects($this->once())
            ->method('handleError')
            ->with($this->isInstanceOf(RuntimeException::class), 'AuthController::login', $this->anything())
            ->willReturn('safe login error');

        $c = $this->controller();

        try {
            $c->login('POST', ['email' => 'ada@example.com', 'password' => 'x']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('safe login error', $c->redirectMessage);
    }

    // ------------------------------------------------------------ registerForm()

    public function testRegisterFormRendersRegisterView(): void
    {
        $c = $this->controller();
        $c->registerForm('GET', []);

        $this->assertSame('Auth/register', $c->renderedView);
    }

    // ---------------------------------------------------------------- register()

    public function testRegisterNonPostDelegatesToRegisterForm(): void
    {
        $c = $this->controller();
        $c->register('GET', []);

        $this->assertSame('Auth/register', $c->renderedView);
    }

    public function testRegisterPostWithValidationFailureStoresErrorAndFormDataThenRedirects(): void
    {
        $c = $this->controller();

        $data = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'not-an-email',
            'password' => 'weak',
            'confirm_password' => 'weak',
        ];

        try {
            $c->register('POST', $data);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            $this->assertSame('halt:redirect', $e->getMessage());
        }

        $this->assertSame('/register', $c->redirectUrl);
        $this->assertNotEmpty($_SESSION['error']);
        $this->assertSame($data, $_SESSION['form_data']);
    }

    public function testRegisterPostWithThrowableFromCreateDelegatesToSecurityServiceHandleError(): void
    {
        $this->userModel->method('create')->willThrowException(new RuntimeException('insert failed'));
        $this->securityService->expects($this->once())
            ->method('handleError')
            ->with($this->isInstanceOf(RuntimeException::class), 'AuthController::register', $this->anything())
            ->willReturn('safe register error');

        $c = $this->controller();

        $data = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Sup3r$ecret!',
            'confirm_password' => 'Sup3r$ecret!',
        ];

        try {
            $c->register('POST', $data);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/register', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
        $this->assertSame('safe register error', $c->redirectMessage);
    }

    // ------------------------------------------------------------- resetPassword()

    // resetPassword() unconditionally calls filter_var(..., FILTER_SANITIZE_STRING)
    // as its first statement -- a constant deprecated since PHP 8.1 that AuthController
    // still uses. Every resetPassword() test therefore triggers that pre-existing
    // production deprecation; #[IgnoreDeprecations] documents that this is expected
    // and out of scope (src/ is off-limits) rather than silently masking it.
    #[IgnoreDeprecations]
    public function testResetPasswordWithMissingTokenRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->resetPassword('GET', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('Invalid or expired password reset link', $c->redirectMessage);
    }

    #[IgnoreDeprecations]
    public function testResetPasswordWithUnknownTokenRedirectsWithError(): void
    {
        $this->userModel->method('findByResetToken')->with('bad-token')->willReturn(null);

        $c = $this->controller();

        try {
            $c->resetPassword('GET', ['token' => 'bad-token']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('Invalid or expired password reset link', $c->redirectMessage);
    }

    #[IgnoreDeprecations]
    public function testResetPasswordGetWithValidTokenRendersForm(): void
    {
        $this->userModel->method('findByResetToken')->willReturn((object)['id' => 5]);

        $c = $this->controller();
        $c->resetPassword('GET', ['token' => 'good-token']);

        $this->assertSame('Auth/reset-password', $c->renderedView);
        $this->assertNull($c->redirectUrl);
    }

    #[IgnoreDeprecations]
    public function testResetPasswordPostWithWeakPasswordRedirectsWithValidationError(): void
    {
        $this->userModel->method('findByResetToken')->willReturn((object)['id' => 5]);

        $c = $this->controller();

        try {
            $c->resetPassword('POST', [
                'token' => 'good-token',
                'password' => 'weak',
                'confirm_password' => 'weak',
            ]);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
    }

    #[IgnoreDeprecations]
    public function testResetPasswordPostWithValidPasswordUpdatesAndRedirectsWithSuccess(): void
    {
        $this->userModel->method('findByResetToken')->willReturn((object)['id' => 5]);
        $this->userModel->expects($this->once())
            ->method('update')
            ->with(5, $this->callback(fn ($data) => isset($data['password_hash'])));
        $this->userModel->expects($this->once())->method('clearPasswordResetToken')->with(5);
        // Rethrow the Halt marker untouched instead of reformatting it, so the
        // outer catch(\Throwable) doesn't mask the success redirect -- see the
        // class docblock.
        $this->securityService->method('handleError')->willReturnCallback(
            function (\Throwable $e) {
                throw $e;
            }
        );

        $c = $this->controller();

        try {
            $c->resetPassword('POST', [
                'token' => 'good-token',
                'password' => 'Sup3r$ecret!',
                'confirm_password' => 'Sup3r$ecret!',
            ]);
            $this->fail('Expected halt exception');
        } catch (Halt $e) {
            $this->assertSame('halt:success', $e->getMessage());
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
        $this->assertSame('Password reset successfully.', $c->redirectMessage);
    }

    // ------------------------------------------------------------------ activate()
    //
    // activate()'s GET branch unconditionally calls filter_var(..., FILTER_SANITIZE_STRING)
    // as its first statement (same pre-existing production deprecation as
    // resetPassword() above), so every GET-branch test below is annotated
    // #[IgnoreDeprecations]. A POST-method test is deliberately not included:
    // AuthController::activate() skips its entire processing block for a
    // non-GET request and falls straight through to
    // `$this->render('Auth/login', compact('companyName'))` with $companyName
    // never defined in that branch, which raises a genuine PHP E_WARNING
    // ("compact(): Undefined variable $companyName") on every call -- with no
    // corresponding #[IgnoreWarnings] mechanism available (only deprecations
    // are ignorable per-test), that branch cannot be exercised without
    // failing the strict suite, so it is left uncovered.

    #[IgnoreDeprecations]
    public function testActivateGetWithMissingTokenRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->activate('GET', []);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/login', $c->redirectUrl);
        $this->assertSame('Invalid or missing token', $c->redirectMessage);
    }

    #[IgnoreDeprecations]
    public function testActivateGetWithUnknownTokenRedirectsWithError(): void
    {
        $this->userModel->method('findByActivationToken')->willReturn(null);

        $c = $this->controller();

        try {
            $c->activate('GET', ['token' => 'bad']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('Invalid or expired activation token', $c->redirectMessage);
    }

    #[IgnoreDeprecations]
    public function testActivateGetWithThrowableDelegatesToSecurityServiceHandleError(): void
    {
        $this->userModel->method('findByActivationToken')->willThrowException(new RuntimeException('lookup failed'));
        $this->securityService->expects($this->once())
            ->method('handleError')
            ->with($this->isInstanceOf(RuntimeException::class), 'AuthController::activate', $this->anything())
            ->willReturn('safe activate error');

        $c = $this->controller();

        try {
            $c->activate('GET', ['token' => 'x']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('safe activate error', $c->redirectMessage);
    }

    // -------------------------------------------------------------- forgotPassword()

    public function testForgotPasswordGetRendersForm(): void
    {
        $c = $this->controller();
        $c->forgotPassword('GET', []);

        $this->assertSame('Auth/forgot-password', $c->renderedView);
        $this->assertNull($c->redirectUrl);
    }

    public function testForgotPasswordPostWithInvalidEmailRedirectsWithValidationError(): void
    {
        $c = $this->controller();

        try {
            $c->forgotPassword('POST', ['email' => 'not-an-email']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/forgot-password', $c->redirectUrl);
        $this->assertSame('error', $c->redirectType);
    }

    public function testForgotPasswordPostWithUnknownEmailRedirectsWithError(): void
    {
        $this->userModel->method('findByEmail')->willReturn(null);

        $c = $this->controller();

        try {
            $c->forgotPassword('POST', ['email' => 'nobody@example.com']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('No account found with that email address', $c->redirectMessage);
    }

    public function testForgotPasswordPostWithThrowableDelegatesToSecurityServiceHandleError(): void
    {
        $this->userModel->method('findByEmail')->willThrowException(new RuntimeException('lookup failed'));
        $this->securityService->expects($this->once())
            ->method('handleError')
            ->with($this->isInstanceOf(RuntimeException::class), 'AuthController::forgotPassword')
            ->willReturn('safe forgot error');

        $c = $this->controller();

        try {
            $c->forgotPassword('POST', ['email' => 'ada@example.com']);
            $this->fail('Expected halt exception');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('/forgot-password', $c->redirectUrl);
        $this->assertSame('safe forgot error', $c->redirectMessage);
    }
}
