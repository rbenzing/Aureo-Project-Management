<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use App\Controllers\TimeTrackingController;
use App\Core\Config;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Models\BaseModel;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Testable subclass: capture render()/redirect*() instead of performing their
 * real side effects, same rationale as ProjectControllerTestable (see that
 * file). Real render() emits HTML; real redirect*() is header()+exit and
 * would kill the runner, so each override throws instead.
 */
final class TimeTrackingControllerTestable extends TimeTrackingController
{
    public ?string $renderedView = null;
    public array $renderedData = [];
    public ?string $redirectUrl = null;
    public ?string $redirectMessage = null;
    public ?string $redirectType = null;
    /** @var string[] */
    public array $requiredPermissions = [];

    protected function requirePermission(string $permission): void
    {
        $this->requiredPermissions[] = $permission;
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
    }

    /**
     * Only the FIRST redirect is recorded - see ProjectControllerTestable for
     * why: the real helpers are `never`, so simulating that with a throw means
     * a redirect fired inside the controller's own try lands in its
     * catch (\Throwable), which then issues a second, error redirect. Assert
     * on redirectType/redirectUrl/redirectMessage, not on the exception that
     * escapes expectHalt() - for a successful action that escaping exception
     * is the artefact second redirect, not the real outcome.
     */
    private function recordRedirect(string $url, ?string $message, string $type): void
    {
        if ($this->redirectUrl === null) {
            $this->redirectUrl = $url;
            $this->redirectMessage = $message;
            $this->redirectType = $type;
        }
    }

    protected function redirect(string $url): never
    {
        $this->recordRedirect($url, null, 'plain');

        throw new RuntimeException('halt:redirect');
    }

    protected function redirectWithSuccess(string $url, string $message): never
    {
        $this->recordRedirect($url, $message, 'success');

        throw new RuntimeException('halt:success');
    }

    protected function redirectWithError(string $url, string $message): never
    {
        $this->recordRedirect($url, $message, 'error');

        throw new RuntimeException('halt:error');
    }
}

/**
 * Behavioural tests for TimeTrackingController's edit/update actions and the
 * assertMayModify ownership rule.
 *
 * delete() is deliberately NOT exercised here: every ApiResponse method ends
 * in exit(), a real process-terminating language construct that cannot be
 * caught or overridden away in a subclass, so calling delete() with a real
 * outcome would kill the PHPUnit runner. That caps delete() coverage at its
 * pre-ApiResponse lines - the same documented src/Core ceiling as
 * ApiResponse/Response themselves.
 *
 * SettingsService/LoggerService/Database are process-wide singletons reached
 * indirectly (AuthMiddleware and TimeTrackingController's own constructor
 * touch them without accepting them as constructor args), so they are seeded
 * with mocks via reflection per test and reset in tearDown, matching
 * ProjectControllerTest's approach.
 */
#[CoversClass(TimeTrackingController::class)]
#[UsesClass(AuthMiddleware::class)]
#[UsesClass(BaseController::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(LoggerService::class)]
#[UsesClass(Project::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(Task::class)]
#[UsesClass(TimeEntry::class)]
#[UsesClass(User::class)]
final class TimeTrackingControllerTest extends TestCase
{
    /** @var Task&\PHPUnit\Framework\MockObject\MockObject */
    private $taskModel;
    /** @var Project&\PHPUnit\Framework\MockObject\MockObject */
    private $projectModel;
    /** @var User&\PHPUnit\Framework\MockObject\MockObject */
    private $userModel;
    /** @var TimeEntry&\PHPUnit\Framework\MockObject\MockObject */
    private $timeEntryModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taskModel = $this->createMock(Task::class);
        $this->projectModel = $this->createMock(Project::class);
        $this->userModel = $this->createMock(User::class);
        $this->timeEntryModel = $this->createMock(TimeEntry::class);

        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getResultsPerPage')->willReturn(10);
        $this->seedSingleton(SettingsService::class, $settingsService);
        $this->seedSingleton(LoggerService::class, $this->createMock(LoggerService::class));
        $this->seedSingleton(Database::class, null);

        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        foreach ([SettingsService::class, LoggerService::class, Database::class] as $class) {
            $this->seedSingleton($class, null);
        }

        $_SESSION = [];
        $_POST = [];

        parent::tearDown();
    }

    private function seedSingleton(string $class, ?object $instance): void
    {
        (new ReflectionClass($class))->getProperty('instance')->setValue(null, $instance);
    }

    private function controller(): TimeTrackingControllerTestable
    {
        return new TimeTrackingControllerTestable(
            $this->taskModel,
            $this->projectModel,
            $this->userModel,
            $this->timeEntryModel
        );
    }

    private function expectHalt(TimeTrackingControllerTestable $c, callable $call): RuntimeException
    {
        try {
            $call();
            $this->fail('Expected a redirect halt to be thrown');
        } catch (RuntimeException $e) {
            return $e;
        }
    }

    private function timeEntry(int $id = 1, int $userId = 7, array $extra = []): object
    {
        return (object) array_merge([
            'id' => $id,
            'task_id' => 3,
            'user_id' => $userId,
            'start_time' => '2026-08-01 09:00:00',
            'end_time' => '2026-08-01 10:00:00',
            'duration' => 3600,
            'notes' => 'Initial notes',
            'is_billable' => 1,
        ], $extra);
    }

    // ---------------------------------------------------------- editForm()

    public function testEditFormRendersTheEntryWithTaskOptions(): void
    {
        $_SESSION['user']['id'] = 7;

        $this->timeEntryModel->method('findWithDetails')->willReturn($this->timeEntry());
        $this->taskModel->method('getAll')->willReturn(['total' => 1, 'records' => [(object) ['id' => 3]]]);

        $c = $this->controller();
        $c->editForm('GET', ['id' => '1']);

        $this->assertSame('TimeTracking/edit', $c->renderedView);
        $this->assertContains('edit_time_tracking', $c->requiredPermissions);
        $this->assertSame(1, $c->renderedData['timeEntry']->id);
        $this->assertCount(1, $c->renderedData['tasks']);
    }

    public function testEditFormRedirectsWithAnErrorForAMissingEntry(): void
    {
        $_SESSION['user']['id'] = 7;

        $this->timeEntryModel->method('findWithDetails')->willReturn(null);

        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->editForm('GET', ['id' => '99']));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('/time-tracking', $c->redirectUrl);
        $this->assertSame('Time entry not found', $c->redirectMessage);
    }

    public function testEditFormRedirectsWithAnErrorForANonNumericId(): void
    {
        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->editForm('GET', ['id' => 'abc']));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('Invalid time entry ID', $c->redirectMessage);
    }

    // ------------------------------------------------------------- update()

    public function testUpdateRejectsANonPostMethod(): void
    {
        $c = $this->controller();
        $e = $this->expectHalt($c, fn () => $c->update('GET', ['id' => '1']));

        $this->assertSame('halt:error', $e->getMessage());
        $this->assertSame('Invalid request method.', $c->redirectMessage);
    }

    public function testUpdateRejectsEndTimeNotAfterStartTime(): void
    {
        $_SESSION['user']['id'] = 7;
        $_POST = [
            'start_date' => '2026-08-01', 'start_time' => '10:00',
            'end_date' => '2026-08-01', 'end_time' => '09:00',
        ];

        $this->timeEntryModel->method('find')->willReturn($this->timeEntry());

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', ['id' => '1']));

        $this->assertSame('End time must be after start time.', $c->redirectMessage);
    }

    /**
     * A client-supplied duration must never reach the database: it could
     * disagree with the timestamps it is meant to summarise.
     */
    public function testUpdateDerivesDurationFromTimestampsRatherThanTrustingPost(): void
    {
        $_SESSION['user']['id'] = 7;
        $_POST = [
            'start_date' => '2026-08-01', 'start_time' => '09:00',
            'end_date' => '2026-08-01', 'end_time' => '10:00',
            'duration' => '999999',
            'notes' => 'Updated',
            'is_billable' => '1',
        ];

        $this->timeEntryModel->method('find')->willReturn($this->timeEntry());

        $captured = null;
        $this->timeEntryModel->method('update')->willReturnCallback(
            function (int $id, array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }
        );

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', ['id' => '1']));

        $this->assertSame(3600, $captured['duration']);
        $this->assertSame('Updated', $captured['notes']);
        $this->assertSame(1, $captured['is_billable']);
    }

    /**
     * The form submits the date and time-of-day as four separate fields
     * (edit.php renders distinct <input type="date">/<input type="time">
     * controls). strtotime() on a bare "09:00" silently defaults to today's
     * date, which would move an entry to today every time its notes or
     * billable flag were edited without touching the date pickers. Regression
     * test for that bug: the date fields must be combined with the time
     * fields, not read alone.
     */
    public function testUpdatePreservesTheSubmittedDateRatherThanDefaultingToToday(): void
    {
        $_SESSION['user']['id'] = 7;
        $_POST = [
            'start_date' => '2020-01-15', 'start_time' => '09:00',
            'end_date' => '2020-01-15', 'end_time' => '10:00',
        ];

        $this->timeEntryModel->method('find')->willReturn($this->timeEntry());

        $captured = null;
        $this->timeEntryModel->method('update')->willReturnCallback(
            function (int $id, array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            }
        );

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', ['id' => '1']));

        $this->assertSame('2020-01-15 09:00:00', $captured['start_time']);
        $this->assertSame('2020-01-15 10:00:00', $captured['end_time']);
    }

    public function testUpdateSucceedsAndRedirectsWithSuccess(): void
    {
        $_SESSION['user']['id'] = 7;
        $_POST = [
            'start_date' => '2026-08-01', 'start_time' => '09:00',
            'end_date' => '2026-08-01', 'end_time' => '11:00',
            'notes' => ' trimmed ',
            'is_billable' => '',
        ];

        $this->timeEntryModel->method('find')->willReturn($this->timeEntry());
        $this->timeEntryModel->method('update')->willReturn(true);

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', ['id' => '1']));

        $this->assertSame('success', $c->redirectType);
        $this->assertSame('/time-tracking', $c->redirectUrl);
        $this->assertSame('Time entry updated successfully.', $c->redirectMessage);
    }

    // ------------------------------------------------------- assertMayModify()

    public function testUpdateRequiresManageTimeTrackingForAnotherUsersEntry(): void
    {
        $_SESSION['user']['id'] = 42;
        $_POST = [
            'start_date' => '2026-08-01', 'start_time' => '09:00',
            'end_date' => '2026-08-01', 'end_time' => '10:00',
        ];

        // Entry belongs to user 7; the session user is 42.
        $this->timeEntryModel->method('find')->willReturn($this->timeEntry(1, 7));
        $this->timeEntryModel->method('update')->willReturn(true);

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', ['id' => '1']));

        $this->assertContains('manage_time_tracking', $c->requiredPermissions);
    }

    public function testUpdateDoesNotRequireManageTimeTrackingForOwnEntry(): void
    {
        $_SESSION['user']['id'] = 7;
        $_POST = [
            'start_date' => '2026-08-01', 'start_time' => '09:00',
            'end_date' => '2026-08-01', 'end_time' => '10:00',
        ];

        $this->timeEntryModel->method('find')->willReturn($this->timeEntry(1, 7));
        $this->timeEntryModel->method('update')->willReturn(true);

        $c = $this->controller();
        $this->expectHalt($c, fn () => $c->update('POST', ['id' => '1']));

        $this->assertNotContains('manage_time_tracking', $c->requiredPermissions);
    }
}
