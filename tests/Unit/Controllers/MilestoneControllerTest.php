<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\MilestoneController;
use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Milestone;
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
 * \Error so it travels through the controller's catch (\Exception) handlers the
 * way a real never-returning redirect does.
 */
final class MilestoneHalt extends \Error
{
}

final class MilestoneControllerTestable extends MilestoneController
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

        throw new MilestoneHalt('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'success';

        throw new MilestoneHalt('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectUrl = $url;
        $this->redirectMessage = $message;
        $this->redirectType = 'error';

        throw new MilestoneHalt('halt:error');
    }

    protected function logException(\Throwable $e, string $context): void
    {
        if ($e instanceof MilestoneHalt) {
            throw $e;
        }

        parent::logException($e, $context);
    }
}

/**
 * Behavioural tests for MilestoneController — 205 statements, none previously
 * executed by any test.
 *
 * create() guards its circular epic-reference check with `isset($id)`, and $id
 * is never defined in create(), so that check never runs. It is dead code, not
 * a missing guard: checkCircularEpicReference($currentId, $newParentId) asks
 * whether $currentId is already a descendant of $newParentId, and a milestone
 * that does not exist yet has no descendants and cannot be in a cycle. The
 * test below pins the absence so nobody "restores" a call that would only ever
 * query for a row id that has not been assigned.
 */
#[CoversClass(MilestoneController::class)]
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
final class MilestoneControllerTest extends TestCase
{
    /** @var Milestone&\PHPUnit\Framework\MockObject\MockObject */
    private $milestoneModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var Template&\PHPUnit\Framework\MockObject\MockObject */
    private $templateModel;
    /** @var SecurityService&\PHPUnit\Framework\MockObject\MockObject */
    private $security;

    protected function setUp(): void
    {
        parent::setUp();

        $this->milestoneModel = $this->createMock(Milestone::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->templateModel = $this->createMock(Template::class);
        $this->security = $this->createMock(SecurityService::class);

        $db = $this->createMock(Database::class);

        // `unique` wants a zero count, `exists` wants a non-zero one; only
        // validateExists() writes the `as cnt` alias, so that tells them apart.
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

        foreach ([SettingsService::class, LoggerService::class, SecurityService::class, Database::class] as $class) {
            $this->seedSingleton($class, null);
        }

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $value): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $value);
    }

    private function controller(): MilestoneControllerTestable
    {
        return new MilestoneControllerTestable($this->milestoneModel, $this->projectModel, $this->templateModel);
    }

    private function halt(MilestoneControllerTestable $c, string $action, string $method, array $data): MilestoneControllerTestable
    {
        try {
            $c->{$action}($method, $data);
            $this->fail("{$action}() was expected to redirect.");
        } catch (MilestoneHalt) {
            // expected
        }

        return $c;
    }

    private function milestone(int $id, string $type = 'milestone', bool $deleted = false): \stdClass
    {
        $milestone = new \stdClass();
        $milestone->id = $id;
        $milestone->title = "Milestone {$id}";
        $milestone->project_id = 100;
        $milestone->epic_id = null;
        $milestone->milestone_type = $type;
        $milestone->is_deleted = $deleted;

        return $milestone;
    }

    private function project(int $id = 100, bool $deleted = false): \stdClass
    {
        $project = new \stdClass();
        $project->id = $id;
        $project->name = 'Project';
        $project->is_deleted = $deleted;

        return $project;
    }

    /** The fields every validator rule in create()/update() needs to pass. */
    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Ship it',
            'milestone_type' => 'milestone',
            'status_id' => '1',
            'project_id' => '100',
        ];
    }

    // ---- index() ---------------------------------------------------------

    public function testIndexListsEveryMilestoneWhenNoProjectIsGiven(): void
    {
        $this->projectModel->method('getAllWithDetails')->willReturn(['records' => []]);
        $this->milestoneModel->expects($this->once())->method('getAllWithProgress')->willReturn([$this->milestone(1)]);
        $this->milestoneModel->method('count')->willReturn(1);

        $c = $this->controller();
        $c->index('GET', []);

        $this->assertSame('Milestones/index', $c->renderedView);
        $this->assertNull($c->renderedData['project']);
        $this->assertCount(1, $c->renderedData['milestones']);
    }

    /**
     * With a project id the list must be scoped to that project; calling the
     * unscoped query here would leak other projects' milestones onto the page.
     */
    public function testIndexScopesToOneProjectWhenAProjectIsGiven(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn($this->project());
        $this->milestoneModel->expects($this->once())->method('getByProjectId')->with(100)->willReturn([]);
        $this->milestoneModel->expects($this->never())->method('getAllWithProgress');
        $this->milestoneModel->method('count')->willReturn(0);

        $c = $this->controller();
        $c->index('GET', ['id' => '100']);

        $this->assertSame(100, $c->renderedData['project']->id);
    }

    public function testIndexRejectsASoftDeletedProject(): void
    {
        $this->projectModel->method('findWithDetails')->willReturn($this->project(100, true));

        $c = $this->halt($this->controller(), 'index', 'GET', ['id' => '100']);

        $this->assertSame('/milestones', $c->redirectUrl);
        $this->assertSame('Project not found', $c->redirectMessage);
    }

    public function testIndexRoutesUnexpectedFailuresThroughSecurityService(): void
    {
        $this->milestoneModel->method('getAllWithProgress')->willThrowException(new RuntimeException('db down'));
        $this->projectModel->method('getAllWithDetails')->willReturn(['records' => []]);
        $this->security->method('handleError')->willReturn('safe message');

        $c = $this->halt($this->controller(), 'index', 'GET', []);

        $this->assertSame('/dashboard', $c->redirectUrl);
        $this->assertSame('safe message', $c->redirectMessage);
    }

    // ---- view() ----------------------------------------------------------

    public function testViewRendersTheMilestoneAndItsProject(): void
    {
        $this->milestoneModel->method('findWithDetails')->willReturn($this->milestone(5));
        $this->projectModel->method('findWithDetails')->willReturn($this->project());
        $this->milestoneModel->method('getRelatedSprints')->willReturn([]);

        $c = $this->controller();
        $c->view('GET', ['id' => '5']);

        $this->assertSame('Milestones/view', $c->renderedView);
        $this->assertSame(5, $c->renderedData['milestone']->id);
        $this->assertNull($c->renderedData['epic']);
    }

    public function testViewLoadsChildMilestonesForAnEpic(): void
    {
        $this->milestoneModel->method('findWithDetails')->willReturn($this->milestone(5, 'epic'));
        $this->projectModel->method('findWithDetails')->willReturn($this->project());
        $this->milestoneModel->expects($this->once())->method('getEpicMilestones')->with(5)->willReturn([$this->milestone(6)]);
        $this->milestoneModel->method('getRelatedSprints')->willReturn([]);

        $c = $this->controller();
        $c->view('GET', ['id' => '5']);

        $this->assertCount(1, $c->renderedData['relatedMilestones']);
    }

    /**
     * Related sprints are explicitly best-effort: the controller wraps that one
     * call so a failure there degrades to an empty list instead of losing the
     * whole page.
     */
    public function testViewStillRendersWhenRelatedSprintsCannotBeLoaded(): void
    {
        $this->milestoneModel->method('findWithDetails')->willReturn($this->milestone(5));
        $this->projectModel->method('findWithDetails')->willReturn($this->project());
        $this->milestoneModel->method('getRelatedSprints')->willThrowException(new RuntimeException('join blew up'));

        $c = $this->controller();
        $c->view('GET', ['id' => '5']);

        $this->assertSame('Milestones/view', $c->renderedView);
        $this->assertSame([], $c->renderedData['relatedSprints']);
    }

    public function testViewRejectsASoftDeletedMilestone(): void
    {
        $this->milestoneModel->method('findWithDetails')->willReturn($this->milestone(5, 'milestone', true));

        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => '5']);

        $this->assertSame('Milestone not found', $c->redirectMessage);
    }

    public function testViewFailsWhenTheAssociatedProjectIsMissing(): void
    {
        $this->milestoneModel->method('findWithDetails')->willReturn($this->milestone(5));
        $this->projectModel->method('findWithDetails')->willReturn(null);

        $c = $this->halt($this->controller(), 'view', 'GET', ['id' => '5']);

        $this->assertSame('An error occurred while fetching milestone details.', $c->redirectMessage);
    }

    // ---- createForm() / create() -----------------------------------------

    public function testCreateFormRendersProjectsStatusesAndTemplates(): void
    {
        $this->projectModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->milestoneModel->method('getMilestoneStatuses')->willReturn([['id' => 1]]);
        $this->milestoneModel->method('getProjectEpics')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([]);

        $c = $this->controller();
        $c->createForm('GET', []);

        $this->assertSame('Milestones/create', $c->renderedView);
        $this->assertSame([['id' => 1]], $c->renderedData['statuses']);
    }

    /**
     * Templates are scoped by the company on the session, so a user in one
     * company must not be offered another company's templates.
     */
    public function testCreateFormScopesTemplatesToTheSessionCompany(): void
    {
        $_SESSION['user']['profile']['company_id'] = 7;
        $this->projectModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->milestoneModel->method('getMilestoneStatuses')->willReturn([]);
        $this->milestoneModel->method('getProjectEpics')->willReturn([]);
        $this->templateModel->expects($this->once())
            ->method('getAvailableTemplates')
            ->with('milestone', 7)
            ->willReturn([]);

        $this->controller()->createForm('GET', []);
    }

    public function testCreateOnAGetRequestShowsTheFormInstead(): void
    {
        $this->projectModel->method('getAll')->willReturn(['records' => [], 'total' => 0]);
        $this->milestoneModel->method('getMilestoneStatuses')->willReturn([]);
        $this->milestoneModel->method('getProjectEpics')->willReturn([]);
        $this->templateModel->method('getAvailableTemplates')->willReturn([]);

        $c = $this->controller();
        $c->create('GET', []);

        $this->assertSame('Milestones/create', $c->renderedView);
    }

    public function testCreatePersistsTheMilestoneAndRedirectsToIt(): void
    {
        $this->milestoneModel->expects($this->once())->method('create')->willReturn(11);

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload());

        $this->assertSame('/milestones/view/11', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    public function testCreateRejectsAMilestoneTypeOutsideTheAllowedSet(): void
    {
        $this->milestoneModel->expects($this->never())->method('create');

        $c = $this->halt($this->controller(), 'create', 'POST', $this->validPayload(['milestone_type' => 'sprint']));

        $this->assertSame('/milestones/create', $c->redirectUrl);
        $this->assertArrayHasKey('form_data', $_SESSION);
    }

    /**
     * See the class docblock: the guard in create() is unreachable, and would be
     * a no-op even if it were reached, because a milestone with no id yet cannot
     * be a descendant of anything.
     */
    public function testCreateNeverChecksForCircularEpicReferences(): void
    {
        $this->milestoneModel->expects($this->never())->method('checkCircularEpicReference');
        $this->milestoneModel->method('create')->willReturn(12);

        $this->halt($this->controller(), 'create', 'POST', $this->validPayload([
            'milestone_type' => 'epic',
            'epic_id' => '4',
        ]));
    }

    // ---- update() --------------------------------------------------------

    public function testUpdateSavesTheMilestone(): void
    {
        $this->milestoneModel->expects($this->once())->method('update')->with(9, $this->anything());

        $c = $this->halt($this->controller(), 'update', 'POST', $this->validPayload(['id' => '9']));

        $this->assertSame('/milestones/view/9', $c->redirectUrl);
        $this->assertSame('success', $c->redirectType);
    }

    /**
     * Completing a milestone has to stamp the completion date; without it the
     * milestone reads as done but reports no date anywhere it is shown.
     */
    public function testUpdateStampsTheCompletionDateWhenStatusBecomesCompleted(): void
    {
        $today = date('Y-m-d');
        $this->milestoneModel->expects($this->once())
            ->method('update')
            ->with(9, $this->callback(static fn (array $d): bool => ($d['complete_date'] ?? null) === $today));

        $this->halt($this->controller(), 'update', 'POST', $this->validPayload(['id' => '9', 'status_id' => '3']));
    }

    public function testUpdateLeavesTheCompletionDateAloneForOtherStatuses(): void
    {
        $this->milestoneModel->expects($this->once())
            ->method('update')
            ->with(9, $this->callback(static fn (array $d): bool => !array_key_exists('complete_date', $d)));

        $this->halt($this->controller(), 'update', 'POST', $this->validPayload(['id' => '9', 'status_id' => '2']));
    }

    public function testUpdateChecksForCircularEpicReferences(): void
    {
        $this->milestoneModel->expects($this->once())->method('checkCircularEpicReference')->with(9, 4);

        $c = $this->halt($this->controller(), 'update', 'POST', $this->validPayload([
            'id' => '9',
            'milestone_type' => 'epic',
            'epic_id' => '4',
        ]));

        $this->assertSame('success', $c->redirectType, 'Editing an epic must not fail.');
    }

    /**
     * An epic with no parent cannot be part of a cycle, so the check is
     * skipped rather than asked about milestone 0.
     */
    public function testUpdateSkipsTheCircularCheckForAnEpicWithNoParent(): void
    {
        $this->milestoneModel->expects($this->never())->method('checkCircularEpicReference');

        $c = $this->halt($this->controller(), 'update', 'POST', $this->validPayload([
            'id' => '9',
            'milestone_type' => 'epic',
        ]));

        $this->assertSame('success', $c->redirectType);
    }

    // ---- delete() --------------------------------------------------------

    public function testDeleteRefusesANonPostRequest(): void
    {
        $c = $this->halt($this->controller(), 'delete', 'GET', ['id' => '9']);

        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testDeleteSoftDeletesTheMilestone(): void
    {
        $this->milestoneModel->method('find')->willReturn($this->milestone(9));
        $this->milestoneModel->expects($this->once())->method('update')->with(9, ['is_deleted' => true]);

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '9']);

        $this->assertSame('success', $c->redirectType);
    }

    /**
     * Deleting an epic that still owns milestones would orphan them, since the
     * children are not cascaded.
     */
    public function testDeleteRefusesAnEpicThatStillOwnsMilestones(): void
    {
        $this->milestoneModel->method('find')->willReturn($this->milestone(9, 'epic'));
        $this->milestoneModel->method('getEpicMilestones')->willReturn([$this->milestone(10)]);
        $this->milestoneModel->expects($this->never())->method('update');

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '9']);

        $this->assertSame('Cannot delete epic with active milestones', $c->redirectMessage);
    }

    public function testDeleteAllowsAnEmptyEpic(): void
    {
        $this->milestoneModel->method('find')->willReturn($this->milestone(9, 'epic'));
        $this->milestoneModel->method('getEpicMilestones')->willReturn([]);
        $this->milestoneModel->expects($this->once())->method('update')->with(9, ['is_deleted' => true]);

        $c = $this->halt($this->controller(), 'delete', 'POST', ['id' => '9']);

        $this->assertSame('success', $c->redirectType);
    }

    // ---- getProjectEpicsApi() --------------------------------------------

    public function testProjectEpicsApiReturnsTheEpicsAsJson(): void
    {
        $this->milestoneModel->method('getProjectEpics')->willReturn([['id' => 3, 'title' => 'Epic']]);

        ob_start();
        $this->controller()->getProjectEpicsApi('GET', ['id' => '100']);
        $body = (string) ob_get_clean();

        $this->assertSame([['id' => 3, 'title' => 'Epic']], json_decode($body, true));
    }

    public function testProjectEpicsApiRejectsAnInvalidProjectId(): void
    {
        $this->milestoneModel->expects($this->never())->method('getProjectEpics');

        ob_start();
        $this->controller()->getProjectEpicsApi('GET', ['id' => 'abc']);
        $body = (string) ob_get_clean();

        $this->assertSame(['error' => 'Invalid project ID'], json_decode($body, true));
        $this->assertSame(400, http_response_code());

        http_response_code(200);
    }

    public function testProjectEpicsApiReportsAFailureAsJson(): void
    {
        $this->milestoneModel->method('getProjectEpics')->willThrowException(new RuntimeException('boom'));

        ob_start();
        $this->controller()->getProjectEpicsApi('GET', ['id' => '100']);
        $body = (string) ob_get_clean();

        $this->assertSame(['error' => 'Failed to load epics'], json_decode($body, true));
        $this->assertSame(500, http_response_code());

        http_response_code(200);
    }
}
