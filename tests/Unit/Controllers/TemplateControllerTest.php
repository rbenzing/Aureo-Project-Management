<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\TemplateController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Template;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Marker thrown by the testable subclass's redirect*() overrides. Extends
 * \Error so the controller's inner catch (\Exception) { rollBack(); throw; }
 * handlers do not mistake a successful redirect for a failure and roll back the
 * transaction that just committed.
 */
final class TemplateHalt extends \Error
{
}

final class TemplateControllerTestable extends TemplateController
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

        throw new TemplateHalt('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'success';

        throw new TemplateHalt('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new TemplateHalt('halt:error');
    }

    protected function logException(\Throwable $e, string $context): void
    {
        if ($e instanceof TemplateHalt) {
            throw $e;
        }

        parent::logException($e, $context);
    }
}

/**
 * Behavioural tests for TemplateController — 183 statements, none previously
 * executed by any test.
 *
 * NOT COVERED: getTemplate() ends every one of its three paths with exit, so
 * calling it kills the PHPUnit process. It is the last action in the codebase
 * still doing that — the rest were converted to return an HttpResponse in
 * 1.3.0 — and covering it means converting it too, which is a change to
 * production behaviour rather than a test.
 */
#[CoversClass(TemplateController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Template::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
final class TemplateControllerTest extends TestCase
{
    /** @var Template&\PHPUnit\Framework\MockObject\MockObject */
    private $templateModel;
    /** @var Company&\PHPUnit\Framework\MockObject\MockObject */
    private $companyModel;
    /** @var SecurityService&\PHPUnit\Framework\MockObject\MockObject */
    private $security;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateModel = $this->createMock(Template::class);
        $this->companyModel = $this->createMock(Company::class);
        $this->security = $this->createMock(SecurityService::class);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturnCallback(function (string $sql) {
            $statement = $this->createMock(\PDOStatement::class);
            $statement->method('fetchColumn')->willReturn(str_contains($sql, 'as cnt') ? 1 : 0);

            return $statement;
        });

        $settings = $this->createMock(SettingsService::class);
        $settings->method('getResultsPerPage')->willReturn(10);

        $this->seedSingleton(SettingsService::class, $settings);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(SecurityService::class, $this->security);
        $this->seedSingleton(Database::class, $db);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];

        foreach ([SettingsService::class, LoggerService::class, SecurityService::class, Database::class] as $class) {
            $this->seedSingleton($class, null);
        }

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    private function controller(): TemplateControllerTestable
    {
        return new TemplateControllerTestable($this->templateModel, $this->companyModel);
    }

    private function halt(TemplateControllerTestable $c, string $action, string $method, array $data): TemplateControllerTestable
    {
        try {
            $c->{$action}($method, $data);
            $this->fail("{$action}() was expected to redirect.");
        } catch (TemplateHalt) {
            // expected
        }

        return $c;
    }

    private function template(int $id, bool $deleted = false): \stdClass
    {
        $template = new \stdClass();
        $template->id = $id;
        $template->name = "Template {$id}";
        $template->description = 'A template';
        $template->template_type = 'project';
        $template->is_deleted = $deleted;

        return $template;
    }

    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Starter',
            'description' => 'A starter template',
            'template_type' => 'project',
        ];
    }

    // ---- index() ---------------------------------------------------------

    public function testIndexRendersTheTemplateList(): void
    {
        $this->templateModel->method('getAllTemplates')->willReturn([$this->template(1)]);
        $this->templateModel->method('count')->willReturn(1);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Templates/index', $c->renderedView);
        $this->assertCount(1, $c->renderedData['templates']);
    }

    /**
     * The type filter comes straight off the query string, so it is checked
     * against the declared set before reaching the model.
     */
    public function testIndexAppliesAKnownTypeFilter(): void
    {
        $_GET['type'] = 'task';
        $this->templateModel->expects($this->once())
            ->method('getAllTemplates')
            ->with(['template_type' => 'task'], 10, 1)
            ->willReturn([]);
        $this->templateModel->method('count')->willReturn(0);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('task', $c->renderedData['templateType']);
    }

    public function testIndexIgnoresAnUnknownTypeFilter(): void
    {
        $_GET['type'] = 'not-a-type';
        $this->templateModel->expects($this->once())
            ->method('getAllTemplates')
            ->with([], 10, 1)
            ->willReturn([]);
        $this->templateModel->method('count')->willReturn(0);

        $this->controller()->index('GET', []);
    }

    /**
     * The count must exclude soft-deleted rows, or the pager advertises pages
     * that render empty.
     */
    public function testIndexCountsOnlyTemplatesThatAreNotDeleted(): void
    {
        $this->templateModel->method('getAllTemplates')->willReturn([]);
        $this->templateModel->expects($this->once())
            ->method('count')
            ->with(['is_deleted' => 0])
            ->willReturn(0);

        $this->controller()->index('GET', []);
    }

    public function testIndexRoutesFailuresThroughSecurityService(): void
    {
        $this->templateModel->method('getAllTemplates')->willThrowException(new RuntimeException('db down'));
        $this->security->method('handleError')->willReturn('safe message');

        $c = $this->halt($this->controller(), 'index', 'GET', []);

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('safe message', $c->redirectMessage);
    }

    // ---- view() / createForm() / editForm() ------------------------------

    public function testViewRendersTheTemplate(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(2));

        $c = $this->controller();
        $c->view('GET', ['id' => '2']);

        $this->assertSame('Templates/view', $c->renderedView);
        $this->assertSame(2, $c->renderedData['template']->id);
    }

    public function testViewTreatsASoftDeletedTemplateAsMissing(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(2, true));

        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => '2']);

        $this->assertSame('Template not found', $c->redirectMessage);
    }

    public function testViewRejectsAnIdThatIsNotAnInteger(): void
    {
        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => 'abc']);

        $this->assertSame('Invalid template ID', $c->redirectMessage);
    }

    public function testCreateFormRendersTheCompanyList(): void
    {
        $this->companyModel->method('getAllCompanies')->willReturn([(object) ['id' => 1]]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Templates/create', $c->renderedView);
        $this->assertCount(1, $c->renderedData['companies']);
    }

    public function testEditFormRendersTheTemplateAndCompanies(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(3));
        $this->companyModel->method('getAllCompanies')->willReturn([]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '3']);

        $this->assertSame('Templates/edit', $c->renderedView);
        $this->assertSame(3, $c->renderedData['template']->id);
    }

    public function testEditFormRejectsAMissingTemplate(): void
    {
        // find() is declared false|object, never null.
        $this->templateModel->method('find')->willReturn(false);

        $c = $this->halt($this->controller(), 'editForm', 'GET', ['id' => '3']);

        $this->assertSame('Template not found', $c->redirectMessage);
    }

    // ---- create() --------------------------------------------------------

    public function testCreateRedirectsAGetRequestToTheForm(): void
    {
        $c = $this->halt($this->controller(), 'create', 'GET', []);

        $this->assertSame('/templates/create', $c->redirectUrl);
        $this->assertSame('plain', $c->redirectType);
    }

    public function testCreatePersistsTheTemplate(): void
    {
        $this->templateModel->expects($this->once())->method('beginTransaction');
        $this->templateModel->method('create')->willReturn(7);
        $this->templateModel->expects($this->once())->method('commit');
        $this->templateModel->expects($this->never())->method('setDefaultTemplate');

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload());

        $this->assertSame('/templates', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * Marking a template default has to demote the previous one, scoped to the
     * same type and company — otherwise two defaults exist and which one wins
     * is down to row order.
     */
    public function testCreateSetsTheNewTemplateAsDefaultWhenAsked(): void
    {
        $this->templateModel->method('create')->willReturn(7);
        $this->templateModel->expects($this->once())
            ->method('setDefaultTemplate')
            ->with(7, 'project', 4);

        $this->halt($this->controller(), 'create', 'POST', $this->validPayload([
            'is_default' => '1',
            'company_id' => '4',
        ]));
    }

    public function testCreateRollsBackWhenSettingTheDefaultFails(): void
    {
        $this->templateModel->method('create')->willReturn(7);
        $this->templateModel->method('setDefaultTemplate')->willThrowException(new RuntimeException('boom'));
        $this->templateModel->expects($this->once())->method('rollBack');
        $this->templateModel->expects($this->never())->method('commit');

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload(['is_default' => '1']));

        $this->assertSame('/templates/create', $c->redirectUrl);
    }

    public function testCreateKeepsTheSubmissionWhenTheTypeIsNotAllowed(): void
    {
        $this->templateModel->expects($this->never())->method('create');

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload(['template_type' => 'epic']));

        $this->assertSame('/templates/create', $c->redirectUrl);
        $this->assertSame('epic', $_SESSION['form_data']['template_type']);
    }

    // ---- update() --------------------------------------------------------

    public function testUpdateRedirectsAGetRequestToTheList(): void
    {
        $c = $this->halt($this->controller(), 'update', 'GET', []);

        $this->assertSame('/templates', $c->redirectUrl);
    }

    public function testUpdateSavesTheTemplate(): void
    {
        $this->templateModel->expects($this->once())->method('update')->with(8, $this->anything());
        $this->templateModel->expects($this->once())->method('commit');

        $c = $this->halt($this->controller(), 'update', 'POST', $this->validPayload(['id' => '8']));

        $this->assertSame('/templates', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    public function testUpdatePromotesTheTemplateToDefaultWhenAsked(): void
    {
        $this->templateModel->expects($this->once())
            ->method('setDefaultTemplate')
            ->with(8, 'project', null);

        $this->halt($this->controller(), 'update', 'POST', $this->validPayload([
            'id' => '8',
            // renderCheckbox() emits value="1", and Validator's boolean rule is
            // strict: 'on' (a checkbox with no value attribute) would fail it.
            'is_default' => '1',
        ]));
    }

    public function testUpdateRollsBackWhenTheSaveFails(): void
    {
        $this->templateModel->method('update')->willThrowException(new RuntimeException('boom'));
        $this->templateModel->expects($this->once())->method('rollBack');

        $c = $this->halt($this->controller(), 'update', 'POST', $this->validPayload(['id' => '8']));

        $this->assertSame('/templates/edit/8', $c->redirectUrl);
    }

    // ---- delete() --------------------------------------------------------

    public function testDeleteRedirectsAGetRequestToTheList(): void
    {
        $c = $this->halt($this->controller(), 'delete', 'GET', []);

        $this->assertSame('/templates', $c->redirectUrl);
    }

    public function testDeleteSoftDeletesTheTemplate(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(9));
        $this->templateModel->expects($this->once())->method('update')->with(9, ['is_deleted' => true]);

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '9']);

        $this->assertSame('success', $c->redirectType);
    }

    public function testDeleteTreatsAnAlreadyDeletedTemplateAsMissing(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(9, true));
        $this->templateModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '9']);

        $this->assertSame('Template not found', $c->redirectMessage);
    }
}
