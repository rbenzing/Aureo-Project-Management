<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Event;
use App\Events\TaskAssigned;
use App\Listeners\SendTaskAssignmentEmail;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Utils\Email;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Email is injected so the delivery path can be asserted without opening an SMTP
 * connection. It stays null-by-default in the listener rather than being
 * constructed eagerly, so production behaviour is unchanged: Email is still built
 * only when a message is actually about to be sent.
 */
#[CoversClass(SendTaskAssignmentEmail::class)]
#[UsesClass(Event::class)]
#[UsesClass(TaskAssigned::class)]
class SendTaskAssignmentEmailTest extends TestCase
{
    /** @var Task&MockObject */
    private Task $taskMock;

    /** @var User&MockObject */
    private User $userMock;

    /** @var LoggerService&MockObject */
    private LoggerService $loggerMock;

    /** @var Email&MockObject */
    private Email $emailMock;

    private SendTaskAssignmentEmail $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taskMock = $this->createMock(Task::class);
        $this->userMock = $this->createMock(User::class);
        $this->loggerMock = $this->createMock(LoggerService::class);
        $this->emailMock = $this->createMock(Email::class);

        $this->listener = new SendTaskAssignmentEmail(
            $this->taskMock,
            $this->userMock,
            $this->loggerMock,
            $this->emailMock
        );
    }

    /**
     * @return object A task row as BaseModel::find() returns it (stdClass).
     */
    private function taskRow(?string $dueDate = '2026-08-01'): object
    {
        return (object) ['id' => 7, 'title' => 'Ship the gate', 'due_date' => $dueDate];
    }

    private function userRow(): object
    {
        return (object) [
            'id' => 3,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
        ];
    }

    /**
     * @param Task|User&MockObject $model
     */
    private function primeLookups(?object $task, ?object $user): void
    {
        $this->taskMock->method('find')->willReturn($task);
        $this->userMock->method('find')->willReturn($user);
    }

    public function testHandleReturnsEarlyWhenTaskNotFound(): void
    {
        $this->taskMock
            ->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn(false);

        $this->userMock->expects($this->once())->method('find')->with(7);

        $this->loggerMock->expects($this->never())->method('info');
        $this->loggerMock->expects($this->never())->method('error');

        $this->listener->handle(new TaskAssigned(taskId: 42, userId: 7, assignedBy: 1));
    }

    public function testHandleReturnsEarlyWhenUserNotFound(): void
    {
        $task = new \stdClass();
        $task->title = 'Fix login bug';
        $task->due_date = null;

        $this->taskMock->method('find')->willReturn($task);
        $this->userMock
            ->expects($this->once())
            ->method('find')
            ->with(7)
            ->willReturn(false);

        $this->loggerMock->expects($this->never())->method('info');
        $this->loggerMock->expects($this->never())->method('error');

        $this->listener->handle(new TaskAssigned(taskId: 42, userId: 7, assignedBy: 1));
    }

    public function testHandleCatchesExceptionThrownWhileFindingTaskAndLogsError(): void
    {
        $this->taskMock
            ->method('find')
            ->willThrowException(new \RuntimeException('Database unavailable'));

        $this->userMock->expects($this->never())->method('find');

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send task assignment email',
                $this->callback(function (array $context) {
                    return $context['error'] === 'Database unavailable'
                        && $context['task_id'] === 99;
                })
            );

        $this->listener->handle(new TaskAssigned(taskId: 99, userId: 1, assignedBy: 2));
    }

    public function testHandleSendsHtmlEmailToTheAssigneeAndLogsSuccess(): void
    {
        $this->primeLookups($this->taskRow(), $this->userRow());

        $this->emailMock
            ->expects($this->once())
            ->method('sendHtml')
            ->with(
                'ada@example.test',
                'Task Assigned: Ship the gate',
                $this->callback(function (string $body): bool {
                    return str_contains($body, 'Hi Ada Lovelace,')
                        && str_contains($body, '<strong>Ship the gate</strong>')
                        && str_contains($body, '<strong>Due:</strong> 2026-08-01');
                })
            )
            ->willReturn(true);

        $this->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('Task assignment email sent', $this->callback(function (array $context): bool {
                return $context['task_id'] === 7
                    && $context['task_title'] === 'Ship the gate'
                    && $context['user_email'] === 'ada@example.test'
                    && $context['user_name'] === 'Ada Lovelace';
            }));

        $this->listener->handle(new TaskAssigned(taskId: 7, userId: 3, assignedBy: 2));
    }

    public function testHandleLogsFailureWhenDeliveryReturnsFalse(): void
    {
        $this->primeLookups($this->taskRow(), $this->userRow());

        $this->emailMock->method('sendHtml')->willReturn(false);

        $this->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('Task assignment email failed', $this->anything());

        $this->listener->handle(new TaskAssigned(taskId: 7, userId: 3, assignedBy: 2));
    }

    public function testHandleOmitsTheDueDateSectionWhenTaskHasNoDueDate(): void
    {
        $this->primeLookups($this->taskRow(null), $this->userRow());

        $this->emailMock
            ->expects($this->once())
            ->method('sendHtml')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static fn (string $body): bool => !str_contains($body, 'Due:'))
            )
            ->willReturn(true);

        $this->listener->handle(new TaskAssigned(taskId: 7, userId: 3, assignedBy: 2));
    }

    public function testHandleEscapesHtmlInTaskTitleAndUserName(): void
    {
        $task = (object) ['id' => 7, 'title' => '<script>x</script>', 'due_date' => null];
        $user = (object) [
            'id' => 3,
            'first_name' => 'Ada&',
            'last_name' => '"Lovelace"',
            'email' => 'ada@example.test',
        ];
        $this->primeLookups($task, $user);

        $this->emailMock
            ->expects($this->once())
            ->method('sendHtml')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static function (string $body): bool {
                    return !str_contains($body, '<script>')
                        && str_contains($body, '&lt;script&gt;')
                        && str_contains($body, 'Ada&amp; &quot;Lovelace&quot;');
                })
            )
            ->willReturn(true);

        $this->listener->handle(new TaskAssigned(taskId: 7, userId: 3, assignedBy: 2));
    }
}
