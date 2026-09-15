<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\SprintTemplateController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Project;
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
 * \Error so it travels through catch (\Exception) handlers the way a real
 * never-returning redirect does.
 */
final class SprintTemplateHalt extends \Error
{
}

final class SprintTemplateControllerTestable extends SprintTemplateController
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

    /**
     * Only the first redirect is recorded. applyTemplate() wraps its
     * redirect() in catch (\Throwable) and does not route through
     * logException(), so the halt marker is caught there and turned into a
     * second, error redirect — which would otherwise overwrite the real one.
     * Production exits on the first redirect, so first-wins matches it.
     */
    private function recordRedirect(string $url, string $type, ?string $message = null): never
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
            $this->redirectType = $type;
            $this->redirectMessage = $message;
        }

        throw new SprintTemplateHalt('halt:' . $type);
    }

    protected function redirect(string $url): never
    {
        $this->recordRedirect($url, 'plain');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->recordRedirect($url, 'success', $message);
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->recordRedirect($url, 'error', $message);
    }

    protected function logException(\Throwable $e, string $context): void
    {
        if ($e instanceof SprintTemplateHalt) {
            throw $e;
        }

        parent::logException($e, $context);
    }
}

/**
 * Behavioural tests for SprintTemplateController — 225 statements, none
 * previously executed. The last of the five controllers H3 names.
 *
 * Its two JSON actions do not exit, unlike TemplateController::getTemplate(),
 * so they are exercised here with output buffering rather than skipped.
 */
#[CoversClass(SprintTemplateController::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(User::class)]
#[UsesClass(Validator::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(SecurityService::class)]
final class SprintTemplateControllerTest extends TestCase
{
    /** @var Template&\PHPUnit\Framework\MockObject\MockObject */
    private $templateModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var Company&\PHPUnit\Framework\MockObject\MockObject */
    private $companyModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateModel = $this->createMock(Template::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->companyModel = $this->createMock(Company::class);

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
        $this->seedSingleton(SecurityService::class, $this->createMock(SecurityService::class));
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

    private function controller(): SprintTemplateControllerTestable
    {
        return new SprintTemplateControllerTestable($this->templateModel, $this->projectModel, $this->companyModel);
    }

    private function halt(SprintTemplateControllerTestable $c, string $action, string $method, array $data): SprintTemplateControllerTestable
    {
        try {
            $c->{$action}($method, $data);
            $this->fail("{$action}() was expected to redirect.");
        } catch (SprintTemplateHalt) {
            // expected
        }

        return $c;
    }

    /** @return array<string,mixed> the decoded JSON an action echoed */
    private function json(SprintTemplateControllerTestable $c, string $action, array $data): array
    {
        ob_start();
        $c->{$action}('GET', $data);

        return (array) json_decode((string) ob_get_clean(), true);
    }

    private function template(int $id, string $type = 'sprint', bool $deleted = false): \stdClass
    {
        $template = new \stdClass();
        $template->id = $id;
        $template->name = "Sprint Template {$id}";
        $template->description = 'Two week cadence';
        $template->template_type = $type;
        $template->is_deleted = $deleted;

        return $template;
    }

    private function config(): \stdClass
    {
        $config = new \stdClass();
        $config->sprint_length = 3;
        $config->estimation_method = 'story_points';
        $config->default_capacity = 60;

        return $config;
    }

    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Two week sprint',
            'description' => 'Standard cadence',
            'sprint_length' => '2',
            'estimation_method' => 'hours',
            'default_capacity' => '40',
        ];
    }

    // ---- index() / createForm() ------------------------------------------

    public function testIndexRendersTheSprintTemplates(): void
    {
        $this->templateModel->method('getSprintTemplates')->willReturn([$this->template(1)]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('SprintTemplates/index', $c->renderedView);
        $this->assertCount(1, $c->renderedData['templates']);
    }

    /**
     * Templates are scoped by the session's company and the requested project;
     * dropping either would show one company's templates to another.
     */
    public function testIndexScopesTemplatesToTheSessionCompanyAndRequestedProject(): void
    {
        $_SESSION['user']['company_id'] = 3;
        $_GET['project_id'] = '12';
        $this->templateModel->expects($this->once())
            ->method('getSprintTemplates')
            ->with(3, 12)
            ->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $this->controller()->index('GET', []);
    }

    public function testIndexRedirectsToTheDashboardOnFailure(): void
    {
        $this->templateModel->method('getSprintTemplates')->willThrowException(new RuntimeException('db down'));

        $c = $this->halt($this->controller(), 'index', 'GET', []);

        $this->assertSame('/dashboard', $c->redirectUrl);
    }

    public function testCreateFormRendersCompaniesProjectsAndTemplates(): void
    {
        $this->companyModel->method('getAllCompanies')->willReturn([(object) ['id' => 1]]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);
        $this->templateModel->method('getSprintTemplates')->willReturn([]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('SprintTemplates/create', $c->renderedView);
        $this->assertCount(1, $c->renderedData['companies']);
    }

    public function testCreateOnAGetRequestShowsTheFormInstead(): void
    {
        $this->companyModel->method('getAllCompanies')->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);
        $this->templateModel->method('getSprintTemplates')->willReturn([]);

        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('SprintTemplates/create', $c->renderedView);
    }

    // ---- create() --------------------------------------------------------

    public function testCreatePersistsTheTemplateAndItsConfiguration(): void
    {
        $this->templateModel->expects($this->once())
            ->method('createSprintTemplate')
            ->with(
                $this->callback(static fn (array $t): bool => $t['template_type'] === 'sprint'),
                $this->callback(static fn (array $c): bool => $c['sprint_length'] === 2 && $c['default_capacity'] === 40)
            )
            ->willReturn(5);

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload());

        $this->assertSame('/sprint-templates', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * The length bound is the guard against a sprint template that would
     * generate a sprint of zero or half a year.
     */
    public function testCreateRejectsASprintLengthOutsideTheAllowedRange(): void
    {
        $this->templateModel->expects($this->never())->method('createSprintTemplate');

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload(['sprint_length' => '52']));

        $this->assertSame('/sprint-templates/create', $c->redirectUrl);
        $this->assertSame('52', $_SESSION['form_data']['sprint_length']);
    }

    public function testCreateRejectsAnUnknownEstimationMethod(): void
    {
        $this->templateModel->expects($this->never())->method('createSprintTemplate');

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload(['estimation_method' => 'tarot']));

        $this->assertSame('/sprint-templates/create', $c->redirectUrl);
    }

    /**
     * Ceremony settings are assembled by hand from a flat form. Every ceremony
     * must appear whether or not it was ticked, or the edit form has nothing to
     * re-render from.
     */
    public function testCreateRecordsEveryCeremonyEvenWhenUnticked(): void
    {
        $captured = null;
        $this->templateModel->method('createSprintTemplate')
            ->willReturnCallback(static function (array $t, array $c) use (&$captured): int {
                $captured = $c['ceremony_settings'];

                return 5;
            });

        $this->halt($this->controller(), 'create', 'POST', $this->validPayload());

        $this->assertSame(
            ['planning', 'daily_standup', 'review', 'retrospective'],
            array_keys((array) $captured)
        );
        $this->assertFalse($captured['planning']['enabled']);
    }

    /**
     * Stand-ups are measured in minutes; the other ceremonies in hours. Sharing
     * one form field between the two is exactly the kind of thing that silently
     * turns a 15-minute stand-up into a 15-hour one.
     */
    public function testCreateRecordsTheStandupInMinutesAndOthersInHours(): void
    {
        $captured = null;
        $this->templateModel->method('createSprintTemplate')
            ->willReturnCallback(static function (array $t, array $c) use (&$captured): int {
                $captured = $c['ceremony_settings'];

                return 5;
            });

        $this->halt($this->controller(), 'create', 'POST', $this->validPayload([
            'ceremony_daily_standup_enabled' => '1',
            // Deliberately not 15: that is the fallback the code uses when the
            // field is absent, so asserting on it could not tell a read value
            // from a hardcoded default.
            'ceremony_daily_standup_duration' => '25',
            'ceremony_planning_duration' => '4',
        ]));

        $this->assertSame(25, $captured['daily_standup']['duration_minutes']);
        $this->assertArrayNotHasKey('duration_hours', $captured['daily_standup']);
        $this->assertSame(4.0, $captured['planning']['duration_hours']);
    }

    // ---- editForm() / update() / delete() --------------------------------

    public function testEditFormRendersTheTemplateAndItsConfiguration(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6));
        $this->templateModel->method('getSprintTemplateConfiguration')->willReturn($this->config());
        $this->companyModel->method('getAllCompanies')->willReturn([]);
        $this->projectModel->method('getAllWithDetails')->willReturn([]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '6']);

        $this->assertSame('SprintTemplates/edit', $c->renderedView);
        $this->assertSame(3, $c->renderedData['config']->sprint_length);
    }

    /**
     * These routes administer sprint templates only. A plain template reached
     * through this controller would be edited with sprint semantics it has no
     * configuration row for.
     */
    public function testEditFormRefusesATemplateThatIsNotASprintTemplate(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6, 'project'));

        $c = $this->halt($this->controller(), 'editForm', 'GET', ['id' => '6']);

        $this->assertSame('Sprint template not found', $c->redirectMessage);
    }

    public function testDeleteRefusesANonPostRequest(): void
    {
        $c = $this->halt($this->controller(), 'delete', 'GET', ['id' => '6']);

        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testDeleteSoftDeletesTheTemplate(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6));
        $this->templateModel->expects($this->once())->method('update')->with(6, ['is_deleted' => true]);

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '6']);

        $this->assertSame('success', $c->redirectType);
    }

    public function testDeleteRefusesATemplateOfAnotherType(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6, 'project'));
        $this->templateModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '6']);

        $this->assertSame('Sprint template not found', $c->redirectMessage);
    }

    // ---- applyTemplate() -------------------------------------------------

    public function testApplyTemplateRedirectsToSprintCreationCarryingTheConfiguration(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6));
        $this->templateModel->method('getSprintTemplateConfiguration')->willReturn($this->config());

        $c = $this->halt($this->controller(), 'applyTemplate', 'POST', [
            'template_id' => '6',
            'project_id' => '12',
        ]);

        $this->assertStringStartsWith('/sprints/create/12?', (string) $c->redirectUrl);

        parse_str((string) parse_url((string) $c->redirectUrl, PHP_URL_QUERY), $query);

        $this->assertSame('3', $query['sprint_length']);
        $this->assertSame('story_points', $query['estimation_method']);
        $this->assertSame('60', $query['capacity']);
    }

    public function testApplyTemplateRequiresBothIds(): void
    {
        $c = $this->halt($this->controller(), 'applyTemplate', 'POST', ['template_id' => '6']);

        $this->assertSame('/sprint-templates', $c->redirectUrl);
        $this->assertSame('Template ID and Project ID are required', $c->redirectMessage);
    }

    public function testApplyTemplateFailsWhenTheConfigurationIsMissing(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6));
        $this->templateModel->method('getSprintTemplateConfiguration')->willReturn(null);

        $c = $this->halt($this->controller(), 'applyTemplate', 'POST', [
            'template_id' => '6',
            'project_id' => '12',
        ]);

        $this->assertSame('Template or configuration not found', $c->redirectMessage);
    }

    // ---- JSON actions ----------------------------------------------------

    public function testGetTemplateReturnsTheTemplateAndConfigAsJson(): void
    {
        $this->templateModel->method('find')->willReturn($this->template(6));
        $this->templateModel->method('getSprintTemplateConfiguration')->willReturn($this->config());

        $body = $this->json($this->controller(), 'getTemplate', ['id' => '6']);

        $this->assertTrue($body['success']);
        $this->assertSame('Sprint Template 6', $body['template']['name']);
        $this->assertSame(3, $body['template']['config']['sprint_length']);
    }

    public function testGetTemplateReportsAMissingTemplateAsJson(): void
    {
        $this->templateModel->method('find')->willReturn(false);

        $body = $this->json($this->controller(), 'getTemplate', ['id' => '6']);

        $this->assertFalse($body['success']);
        $this->assertSame('Sprint template not found', $body['message']);
        $this->assertSame(400, http_response_code());

        http_response_code(200);
    }

    public function testTemplatesApiReturnsTheScopedTemplates(): void
    {
        $_SESSION['user']['company_id'] = 3;
        $_GET['project_id'] = '12';
        $this->templateModel->expects($this->once())
            ->method('getSprintTemplates')
            ->with(3, 12)
            ->willReturn([['id' => 1]]);

        $body = $this->json($this->controller(), 'getTemplatesApi', []);

        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['templates']);
    }

    public function testTemplatesApiReportsAFailureAsJson(): void
    {
        $this->templateModel->method('getSprintTemplates')->willThrowException(new RuntimeException('db down'));

        $body = $this->json($this->controller(), 'getTemplatesApi', []);

        $this->assertFalse($body['success']);
        $this->assertSame(400, http_response_code());

        http_response_code(200);
    }
}
