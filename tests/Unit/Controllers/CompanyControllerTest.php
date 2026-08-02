<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\CompanyController;
use App\Core\Config;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Marker exception thrown by the testable subclass's redirect*() overrides.
 * Named distinctly per test file (rather than shared) so this file stays
 * runnable in isolation without depending on class declarations loaded by a
 * sibling test file.
 */
final class CompanyHalt extends RuntimeException
{
}

/**
 * Testable subclass: capture render()/redirect*() instead of the real side
 * effects, no-op requirePermission() (PHP dispatches it virtually even when
 * called from the parent constructor, so this is in effect before any
 * CompanyController code runs), and make logException() rethrow the Halt
 * marker untouched -- see the class docblock below for why that's needed.
 */
final class CompanyControllerTestable extends CompanyController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;
    public ?string $redirectType = null;

    protected function requirePermission(string $permission): void
    {
        // no-op in tests
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirect(string $url): never
    {
        $this->redirectUrl = $url;
        $this->redirectType = 'plain';

        throw new CompanyHalt('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'success';

        throw new CompanyHalt('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new CompanyHalt('halt:error');
    }

    protected function logException(\Throwable $e, string $context): void
    {
        if ($e instanceof CompanyHalt) {
            // delete()'s success path calls redirectWithSuccess() from inside
            // the same try block CompanyController wraps with
            // catch(\Throwable); since the override above must throw to halt
            // execution, that throw would otherwise be intercepted here and
            // reformatted into a *different* redirectWithError() call,
            // masking the real success message. Rethrowing the marker
            // untouched lets the originally-captured success url/message
            // survive intact.
            throw $e;
        }

        parent::logException($e, $context);
    }
}

/**
 * Behavioural tests for CompanyController.
 *
 * requirePermission('view_companies') is called directly inside
 * CompanyController::__construct(), before any test code gets a chance to
 * intervene -- this works only because PHP dispatches $this->requirePermission()
 * virtually, so the *subclass's* no-op override is already what runs, even
 * though the call originates in the parent constructor.
 *
 * BaseController::__construct() (invoked with no AuthMiddleware, since
 * CompanyController doesn't accept one) still builds a real AuthMiddleware;
 * that construction alone is harmless (just assigns `new User()` and
 * `SettingsService::getInstance()`, never queries), so SettingsService/
 * LoggerService singletons are seeded with mocks beforehand and the whole
 * reachable class chain is declared per CLAUDE.md's guidance.
 *
 * index()'s catch(\Throwable) calls the STATIC `SecurityService::getInstance()`
 * directly (not the DI-injected instance other controllers use), so that
 * singleton is seeded with a mock too.
 *
 * KNOWN BUG (not fixed, not enshrined in a test -- reported instead):
 * CompanyController::create() and ::update() validate 'phone' with the rule
 * string 'regex:/^[+]?[0-9()-\s]{10,}$/' (see lines ~143 and ~241), but
 * 'regex' is NOT one of Validator::AVAILABLE_RULES (src/Utils/Validator.php).
 * Validator::fails() throws InvalidArgumentException("Unknown validation
 * rule: regex") for EVERY create()/update() POST request that reaches that
 * rule -- i.e. always, since the rule list is static regardless of whether
 * a phone value was even submitted. That exception is caught by the
 * controller's own catch (InvalidArgumentException) block and surfaces to
 * the user as "Unknown validation rule: regex" instead of any real
 * validation feedback, meaning company create/update is completely broken
 * in production right now. Because of this, create()/update()'s POST
 * success path is unreachable and is not tested here.
 *
 * SEPARATELY UNCOVERABLE (independent of the bug above): create()'s POST
 * success path and update()'s POST success/InvalidArgumentException/
 * Throwable branches all call raw header()+exit directly (lines ~167-168,
 * ~264-265, ~270-271, ~275-276) instead of routing through BaseController's
 * overridable redirect*() methods, so even without the validator bug they
 * could not be exercised in-process without killing the PHPUnit run. Only
 * the non-POST branches of create()/update() (which delegate to
 * createForm()/editForm()) are exercised.
 */
#[CoversClass(CompanyController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
final class CompanyControllerTest extends TestCase
{
    /** @var Company&\PHPUnit\Framework\MockObject\MockObject */
    private $companyModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var SecurityService&\PHPUnit\Framework\MockObject\MockObject */
    private $securityServiceSingleton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyModel = $this->createMock(Company::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->userModel = $this->createMock(User::class);
        $this->securityServiceSingleton = $this->createMock(SecurityService::class);

        $this->seedSingleton(SettingsService::class, $this->createMock(SettingsService::class));
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(SecurityService::class, $this->securityServiceSingleton);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $this->seedSingleton(SettingsService::class, null);
        $this->seedSingleton(LoggerService::class, null);
        $this->seedSingleton(SecurityService::class, null);

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    private function controller(): CompanyControllerTestable
    {
        return new CompanyControllerTestable($this->companyModel, $this->projectModel, $this->userModel);
    }

    private function company(int $id, bool $deleted = false): \stdClass
    {
        $c = new \stdClass();
        $c->id = $id;
        $c->name = "Company {$id}";
        $c->is_deleted = $deleted;

        return $c;
    }

    // -------------------------------------------------------------------- index()

    public function testIndexRendersPaginatedCompaniesWithSummaryCounts(): void
    {
        $this->companyModel->method('getAll')
            ->with(['is_deleted' => 0], 1, 10)
            ->willReturn(['records' => [$this->company(1)], 'total' => 1]);
        $this->userModel->method('count')->with(['is_deleted' => 0])->willReturn(5);
        $this->projectModel->method('count')->with(['status_id' => 2, 'is_deleted' => 0])->willReturn(3);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Companies/index', $c->renderedView);
        $this->assertCount(1, $c->renderedData['companies']);
        $this->assertSame(1, $c->renderedData['totalCompanies']);
        $this->assertSame(5, $c->renderedData['totalUsers']);
        $this->assertSame(3, $c->renderedData['activeProjects']);
        $this->assertSame(1.0, $c->renderedData['totalPages']);
    }

    public function testIndexAppliesSearchFilterFromGetParameter(): void
    {
        $_GET['search'] = '  acme  ';
        $this->companyModel->expects($this->once())
            ->method('getAll')
            ->with(['is_deleted' => 0, 'search' => 'acme'], 1, 10)
            ->willReturn(['records' => [], 'total' => 0]);
        $this->userModel->method('count')->willReturn(0);
        $this->projectModel->method('count')->willReturn(0);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Companies/index', $c->renderedView);
    }

    public function testIndexUsesRequestedPageNumber(): void
    {
        $this->companyModel->expects($this->once())
            ->method('getAll')
            ->with(['is_deleted' => 0], 3, 10)
            ->willReturn(['records' => [], 'total' => 0]);
        $this->userModel->method('count')->willReturn(0);
        $this->projectModel->method('count')->willReturn(0);

        $c = $this->controller();
        $c->index('GET', ['page' => '3']);
    }

    public function testIndexOnExceptionDelegatesToSecurityServiceAndRedirects(): void
    {
        $this->companyModel->method('getAll')->willThrowException(new RuntimeException('db exploded'));
        $this->securityServiceSingleton->expects($this->once())
            ->method('handleError')
            ->with($this->isInstanceOf(RuntimeException::class), 'CompanyController::index', $this->anything())
            ->willReturn('safe index error');

        $c = $this->controller();

        try {
            $c->index('GET', []);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('safe index error', $c->redirectMessage);
    }

    // --------------------------------------------------------------------- view()

    public function testViewWithMissingIdRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->view('GET', []);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('/companies', $c->redirectUrl);
        $this->assertSame('Invalid company ID', $c->redirectMessage);
    }

    public function testViewWithUnknownCompanyRedirectsWithError(): void
    {
        $this->companyModel->method('find')->with(99)->willReturn(false);

        $c = $this->controller();

        try {
            $c->view('GET', ['id' => '99']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Company not found', $c->redirectMessage);
    }

    public function testViewWithDeletedCompanyRedirectsWithError(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5, true));

        $c = $this->controller();

        try {
            $c->view('GET', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Company not found', $c->redirectMessage);
    }

    public function testViewWithValidCompanyRendersDetailWithProjectsAndUsers(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));
        $this->companyModel->method('getProjects')->willReturn([(object)['id' => 1]]);
        $this->companyModel->method('getUsers')->with(5)->willReturn([(object)['id' => 2]]);

        $c = $this->controller();
        $c->view('GET', ['id' => '5']);

        $this->assertSame('Companies/view', $c->renderedView);
        $this->assertSame(5, $c->renderedData['company']->id);
        $this->assertCount(1, $c->renderedData['projects']);
        $this->assertCount(1, $c->renderedData['users']);
    }

    public function testViewOnExceptionLogsAndRedirectsWithGenericMessage(): void
    {
        $this->companyModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();

        try {
            $c->view('GET', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('/companies', $c->redirectUrl);
        $this->assertSame('An error occurred while fetching company details.', $c->redirectMessage);
    }

    // ---------------------------------------------------------------- createForm()

    public function testCreateFormRendersCreateView(): void
    {
        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Companies/create', $c->renderedView);
    }

    // -------------------------------------------------------------------- create()

    public function testCreateNonPostDelegatesToCreateForm(): void
    {
        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('Companies/create', $c->renderedView);
    }

    // --------------------------------------------------------------- editForm()

    public function testEditFormWithMissingIdRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->editForm('GET', []);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('/companies', $c->redirectUrl);
        $this->assertSame('Invalid company ID', $c->redirectMessage);
    }

    public function testEditFormWithUnknownCompanyRedirectsWithError(): void
    {
        $this->companyModel->method('find')->willReturn(false);

        $c = $this->controller();

        try {
            $c->editForm('GET', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Company not found', $c->redirectMessage);
    }

    public function testEditFormWithValidCompanyRendersEditView(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));

        $c = $this->controller();
        $c->editForm('GET', ['id' => '5']);

        $this->assertSame('Companies/edit', $c->renderedView);
        $this->assertSame(5, $c->renderedData['company']->id);
    }

    public function testEditFormOnExceptionLogsAndRedirectsWithGenericMessage(): void
    {
        $this->companyModel->method('find')->willThrowException(new RuntimeException('boom'));

        $c = $this->controller();

        try {
            $c->editForm('GET', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('An error occurred while loading the edit form.', $c->redirectMessage);
    }

    // -------------------------------------------------------------------- update()

    public function testUpdateNonPostDelegatesToEditForm(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));

        $c = $this->controller();
        $c->update('GET', ['id' => '5']);

        $this->assertSame('Companies/edit', $c->renderedView);
    }

    // -------------------------------------------------------------------- delete()

    public function testDeleteNonPostRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->delete('GET', []);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('/companies', $c->redirectUrl);
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testDeleteWithMissingIdRedirectsWithError(): void
    {
        $c = $this->controller();

        try {
            $c->delete('POST', []);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Invalid company ID', $c->redirectMessage);
    }

    public function testDeleteWithUnknownCompanyRedirectsWithError(): void
    {
        $this->companyModel->method('find')->willReturn(false);

        $c = $this->controller();

        try {
            $c->delete('POST', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Company not found', $c->redirectMessage);
    }

    public function testDeleteWithActiveProjectsIsRejected(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));
        $this->projectModel->method('count')->with(['company_id' => 5, 'is_deleted' => 0])->willReturn(2);

        $c = $this->controller();

        try {
            $c->delete('POST', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Cannot delete company with active projects', $c->redirectMessage);
    }

    public function testDeleteWithActiveUsersIsRejected(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));
        $this->projectModel->method('count')->willReturn(0);
        $this->userModel->method('count')->with(['company_id' => 5, 'is_deleted' => 0])->willReturn(1);

        $c = $this->controller();

        try {
            $c->delete('POST', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('Cannot delete company with active users', $c->redirectMessage);
    }

    public function testDeleteWithNoBlockersSoftDeletesAndRedirectsWithSuccess(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));
        $this->projectModel->method('count')->willReturn(0);
        $this->userModel->method('count')->willReturn(0);
        $this->companyModel->expects($this->once())->method('update')->with(5, ['is_deleted' => true]);

        $c = $this->controller();

        try {
            $c->delete('POST', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('/companies', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
        $this->assertSame('Company deleted successfully.', $c->redirectMessage);
    }

    public function testDeleteOnExceptionLogsAndRedirectsWithGenericMessage(): void
    {
        $this->companyModel->method('find')->with(5)->willReturn($this->company(5));
        $this->projectModel->method('count')->willReturn(0);
        $this->userModel->method('count')->willReturn(0);
        $this->companyModel->method('update')->willThrowException(new RuntimeException('write failed'));

        $c = $this->controller();

        try {
            $c->delete('POST', ['id' => '5']);
            $this->fail('Expected halt exception');
        } catch (CompanyHalt $e) {
            // expected
        }

        $this->assertSame('error', $c->redirectType);
        $this->assertSame('An error occurred while deleting the company.', $c->redirectMessage);
    }
}
