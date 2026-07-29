<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Task;
use App\Utils\Breadcrumb;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for Breadcrumb: route resolution (exact match and
 * traversal), URL param substitution, HTML rendering (first/middle/last
 * item branches, icons, copy-to-clipboard), and the task/sprint specific
 * renderers including the parent-task lookup and its error path.
 *
 * Task::find() is exercised through renderTaskBreadcrumb(), so the process
 * wide Database singleton is swapped for a mock via reflection (mirroring
 * the pattern used by BaseModelQueryBuilderTest) to guarantee no real MySQL
 * connection is attempted, and restored afterwards so it cannot leak into
 * other test files.
 */
#[CoversClass(Breadcrumb::class)]
#[UsesClass(Task::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Database::class)]
final class BreadcrumbTest extends TestCase
{
    private ?array $serverBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDatabaseSingleton(null);
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $this->setDatabaseSingleton(null);

        if ($this->serverBackup !== null) {
            $_SERVER = $this->serverBackup;
        }

        parent::tearDown();
    }

    private function setDatabaseSingleton(?Database $db): void
    {
        $property = (new ReflectionClass(Database::class))->getProperty('instance');
        $property->setValue(null, $db);
    }

    // ------------------------------------------------------------- generate()

    public function testGenerateExactMatchReturnsDashboardAndCrumbWithReplacedParams(): void
    {
        $path = Breadcrumb::generate('projects/view', ['id' => 42]);

        $this->assertCount(2, $path);
        $this->assertSame('Dashboard', $path[0]['name']);
        $this->assertSame('Project Details', $path[1]['name']);
        $this->assertSame('/projects/view/42', $path[1]['url']);
    }

    public function testGenerateTraversesPartsWhenNoExactMatchExists(): void
    {
        // 'tasks/view/edit' has no direct key, so generate() must fall back
        // to walking each accumulated segment ('tasks', then 'tasks/view').
        $path = Breadcrumb::generate('tasks/view/edit', ['id' => 7]);

        $this->assertCount(3, $path);
        $this->assertSame('Dashboard', $path[0]['name']);
        $this->assertSame('All Tasks', $path[1]['name']);
        $this->assertSame('Task Details', $path[2]['name']);
        $this->assertSame('/tasks/view/7', $path[2]['url']);
    }

    public function testGenerateWithCompletelyUnknownRouteReturnsOnlyDashboard(): void
    {
        $path = Breadcrumb::generate('foo/bar');

        $this->assertCount(1, $path);
        $this->assertSame('Dashboard', $path[0]['name']);
    }

    public function testGenerateSkipsNonScalarParamsWithoutCastingThem(): void
    {
        // replaceUrlParams() must leave the placeholder untouched for
        // array/object values instead of fatally casting them to string.
        $path = Breadcrumb::generate('projects/view', ['id' => ['nested' => 'array']]);

        $this->assertSame('/projects/view/{id}', $path[1]['url']);
    }

    public function testGenerateReplacesNullParamWithEmptyString(): void
    {
        $path = Breadcrumb::generate('projects/view', ['id' => null]);

        $this->assertSame('/projects/view/', $path[1]['url']);
    }

    // --------------------------------------------------------------- render()

    public function testRenderProducesFirstMiddleAndLastItemMarkup(): void
    {
        $html = Breadcrumb::render('tasks/view/edit', ['id' => 9]);

        $this->assertStringContainsString('<nav class="flex mb-5" aria-label="Breadcrumb">', $html);
        // First item: plain anchor with the "home" icon.
        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('href="/dashboard"', $html);
        // Middle item: anchor wrapped with a chevron separator.
        $this->assertStringContainsString('All Tasks', $html);
        $this->assertStringContainsString('href="/tasks"', $html);
        // Last item: aria-current, no anchor for the crumb itself.
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Task Details', $html);
    }


    // ------------------------------------------------------ renderTaskBreadcrumb()

    public function testRenderTaskBreadcrumbForNonSubtaskSkipsParentLookup(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/tasks/view/3';
        unset($_SERVER['HTTPS']);

        $task = (object)['id' => 3, 'is_subtask' => false, 'parent_task_id' => null];

        $html = Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view');

        $this->assertStringContainsString('Task #3', $html);
        $this->assertStringContainsString('http://example.test/tasks/view/3', $html);
        $this->assertStringContainsString('copyToClipboard', $html);
        // No parent task was looked up, so exactly one "Task #" crumb exists.
        $this->assertSame(1, substr_count($html, 'Task #'));
    }

    public function testRenderTaskBreadcrumbUsesHttpsSchemeWhenServerHttpsIsOn(): void
    {
        $_SERVER['HTTP_HOST'] = 'secure.test';
        $_SERVER['REQUEST_URI'] = '/tasks/view/4';
        $_SERVER['HTTPS'] = 'on';

        $task = (object)['id' => 4, 'is_subtask' => false, 'parent_task_id' => null];

        $html = Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view');

        $this->assertStringContainsString('https://secure.test/tasks/view/4', $html);
    }

    public function testRenderTaskBreadcrumbAddsParentTaskWhenFoundAndNotDeleted(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/tasks/view/10';
        unset($_SERVER['HTTPS']);

        $parent = (object)['id' => 2, 'is_deleted' => 0];
        $this->setDatabaseSingleton($this->makeDatabaseReturningTask($parent));

        $task = (object)['id' => 10, 'is_subtask' => true, 'parent_task_id' => 2];

        $html = Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view');

        $this->assertStringContainsString('Task #2', $html);
        $this->assertStringContainsString('href="/tasks/view/2"', $html);
        $this->assertStringContainsString('Task #10', $html);
    }

    public function testRenderTaskBreadcrumbSkipsParentWhenItIsSoftDeleted(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/tasks/view/11';
        unset($_SERVER['HTTPS']);

        $parent = (object)['id' => 5, 'is_deleted' => 1];
        $this->setDatabaseSingleton($this->makeDatabaseReturningTask($parent));

        $task = (object)['id' => 11, 'is_subtask' => true, 'parent_task_id' => 5];

        $html = Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view');

        $this->assertStringNotContainsString('Task #5', $html);
        $this->assertStringContainsString('Task #11', $html);
    }

    public function testRenderTaskBreadcrumbSkipsParentWhenNotFound(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/tasks/view/12';
        unset($_SERVER['HTTPS']);

        $this->setDatabaseSingleton($this->makeDatabaseReturningTask(false));

        $task = (object)['id' => 12, 'is_subtask' => true, 'parent_task_id' => 999];

        $html = Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view');

        $this->assertStringContainsString('Task #12', $html);
    }

    public function testRenderTaskBreadcrumbContinuesWithoutParentWhenLookupThrows(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/tasks/view/13';
        unset($_SERVER['HTTPS']);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willThrowException(new \RuntimeException('DB unavailable'));
        $this->setDatabaseSingleton($db);

        $task = (object)['id' => 13, 'is_subtask' => true, 'parent_task_id' => 6];

        // The catch(\Exception) block must swallow the failure and still
        // render the breadcrumb for the current task.
        $html = Breadcrumb::renderTaskBreadcrumb($task, 'tasks/view');

        $this->assertStringContainsString('Task #13', $html);
    }

    private function makeDatabaseReturningTask(object|false $result): Database
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetch')->willReturn($result);

        $db = $this->createMock(Database::class);
        $db->method('executeQuery')->willReturn($statement);

        return $db;
    }

    // ---------------------------------------------------- renderSprintBreadcrumb()

    public function testRenderSprintBreadcrumbWithoutProjectUsesGeneralSprintsLink(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/sprints/view/1';
        unset($_SERVER['HTTPS']);

        $sprint = (object)['id' => 1, 'name' => 'Sprint Alpha'];

        $html = Breadcrumb::renderSprintBreadcrumb($sprint, null, 'sprints/view');

        $this->assertStringContainsString('href="/sprints"', $html);
        $this->assertStringContainsString('Sprint Alpha', $html);
        $this->assertStringContainsString('copyToClipboard', $html);
    }

    public function testRenderSprintBreadcrumbWithProjectAddsProjectContext(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/sprints/view/2';
        unset($_SERVER['HTTPS']);

        $sprint = (object)['id' => 2, 'name' => 'Sprint Beta'];
        $project = (object)['id' => 77, 'name' => 'Apollo'];

        $html = Breadcrumb::renderSprintBreadcrumb($sprint, $project, 'sprints/view');

        $this->assertStringContainsString('href="/projects"', $html);
        $this->assertStringContainsString('Apollo', $html);
        $this->assertStringContainsString('href="/projects/view/77"', $html);
        $this->assertStringContainsString('href="/sprints/project/77"', $html);
        $this->assertStringContainsString('Sprint Beta', $html);
    }

    public function testRenderSprintBreadcrumbEscapesProjectNameInMiddleItem(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/sprints/view/4';
        unset($_SERVER['HTTPS']);

        // Unlike the generic map-driven render(), the project name here is
        // caller-supplied data landing on a *middle* (non-last) crumb, so
        // its href is actually rendered — a genuine spot to prove the
        // htmlspecialchars() sanitation runs on dynamic content.
        $sprint = (object)['id' => 4, 'name' => 'Sprint Gamma'];
        $project = (object)['id' => 88, 'name' => '<script>alert(1)</script>'];

        $html = Breadcrumb::renderSprintBreadcrumb($sprint, $project, 'sprints/view');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('href="/projects/view/88"', $html);
    }

    public function testRenderSprintBreadcrumbFallsBackToSprintIdWhenNameMissing(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/sprints/view/3';
        unset($_SERVER['HTTPS']);

        $sprint = (object)['id' => 3];

        $html = Breadcrumb::renderSprintBreadcrumb($sprint);

        $this->assertStringContainsString('Sprint #3', $html);
    }
}
