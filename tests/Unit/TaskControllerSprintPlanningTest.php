<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\TaskController;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Testable subclass: capture render() args and neutralize permission/redirect
 * side effects so the branching logic can be unit-tested without a DB or HTTP.
 */
final class SprintPlanningTestController extends TaskController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectError = null;

    protected function requirePermission(string $permission): void
    {
        // no-op in tests
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->redirectError = $message;
        throw new \RuntimeException('redirect:' . $message);
    }
}

class TaskControllerSprintPlanningTest extends TestCase
{
    /** @var Task&\PHPUnit\Framework\MockObject\MockObject */
    private $taskModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var Sprint&\PHPUnit\Framework\MockObject\MockObject */
    private $sprintModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taskModel = $this->createMock(Task::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->userModel = $this->createMock(User::class);
        $this->sprintModel = $this->createMock(Sprint::class);
        $_SESSION['user']['profile']['id'] = 7;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    private function controller(): SprintPlanningTestController
    {
        return new SprintPlanningTestController(
            $this->taskModel,
            $this->projectModel,
            $this->userModel,
            $this->sprintModel
        );
    }

    private function project(int $id, bool $deleted = false): \stdClass
    {
        $p = new \stdClass();
        $p->id = $id;
        $p->name = "Project {$id}";
        $p->is_deleted = $deleted;

        return $p;
    }

    private function sprint(int $id, int $statusId): \stdClass
    {
        $s = new \stdClass();
        $s->id = $id;
        $s->status_id = $statusId;

        return $s;
    }

    public function testNoProjectIdShowsSelectionView(): void
    {
        unset($_GET['project_id']);
        $this->userModel->method('getUserProjects')->willReturn([$this->project(3)]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);

        $c = $this->controller();
        $c->sprintPlanning('GET', []);

        $this->assertSame('Tasks/sprint-planning', $c->renderedView);
        $this->assertSame('sprint_planning_selection', $c->renderedData['viewType'] ?? null);
        $this->assertArrayHasKey('projects', $c->renderedData);
        $this->assertArrayNotHasKey('project', $c->renderedData);
    }

    public function testValidProjectLoadsBoardWithActiveSprintsOnly(): void
    {
        $_GET['project_id'] = '3';
        $this->userModel->method('getUserProjects')->willReturn([$this->project(3)]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->projectModel->method('findWithDetails')->with(3)->willReturn($this->project(3));
        $this->sprintModel->method('getByProjectId')->with(3)->willReturn([
            $this->sprint(10, 2), // active
            $this->sprint(11, 1), // planning -> filtered out
            $this->sprint(12, 2), // active
        ]);
        $this->taskModel->method('getProductBacklog')->willReturn([(object) ['id' => 99]]);

        $c = $this->controller();
        $c->sprintPlanning('GET', []);

        $this->assertSame('Tasks/sprint-planning', $c->renderedView);
        $this->assertArrayNotHasKey('viewType', $c->renderedData);
        $this->assertSame(3, $c->renderedData['project']->id);
        $this->assertCount(2, $c->renderedData['activeSprints']);
        // Only status_id == 2 survive, reindexed via array_values: [10, 12].
        $this->assertSame(10, $c->renderedData['activeSprints'][0]->id);
        $this->assertSame(12, $c->renderedData['activeSprints'][1]->id);
        $this->assertArrayHasKey('availableTasks', $c->renderedData);

        unset($_GET['project_id']);
    }

    public function testProjectNotInUserProjectsFallsBackToSelectionWithError(): void
    {
        $_GET['project_id'] = '999';
        $this->userModel->method('getUserProjects')->willReturn([$this->project(3)]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);

        $c = $this->controller();
        $c->sprintPlanning('GET', []);

        $this->assertSame('sprint_planning_selection', $c->renderedData['viewType'] ?? null);
        $this->assertNotEmpty($c->renderedData['error'] ?? null);
        $this->assertArrayNotHasKey('project', $c->renderedData);

        unset($_GET['project_id']);
    }

    public function testDeletedProjectFallsBackToSelectionWithError(): void
    {
        $_GET['project_id'] = '3';
        $this->userModel->method('getUserProjects')->willReturn([$this->project(3)]);
        $this->taskModel->method('getTaskStatuses')->willReturn([]);
        $this->projectModel->method('findWithDetails')->with(3)->willReturn($this->project(3, true));

        $c = $this->controller();
        $c->sprintPlanning('GET', []);

        $this->assertSame('sprint_planning_selection', $c->renderedData['viewType'] ?? null);
        $this->assertNotEmpty($c->renderedData['error'] ?? null);

        unset($_GET['project_id']);
    }
}
