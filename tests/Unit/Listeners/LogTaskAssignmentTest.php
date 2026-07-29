<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Event;
use App\Events\TaskAssigned;
use App\Listeners\LogTaskAssignment;
use App\Services\LoggerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogTaskAssignment::class)]
#[UsesClass(Event::class)]
#[UsesClass(TaskAssigned::class)]
class LogTaskAssignmentTest extends TestCase
{
    /** @var LoggerService&MockObject */
    private LoggerService $loggerMock;

    private LogTaskAssignment $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->createMock(LoggerService::class);
        $this->listener = new LogTaskAssignment($this->loggerMock);
    }

    public function testHandleLogsInfoWithTaskAssignmentDetails(): void
    {
        $event = new TaskAssigned(taskId: 42, userId: 7, assignedBy: 3);

        $this->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                'Task assigned',
                $this->callback(function (array $context) use ($event) {
                    return $context['task_id'] === 42
                        && $context['user_id'] === 7
                        && $context['assigned_by'] === 3
                        && $context['timestamp'] === date('Y-m-d H:i:s', (int) $event->getTimestamp());
                })
            );

        $this->listener->handle($event);
    }

    public function testHandleNeverCallsErrorOrWarning(): void
    {
        $this->loggerMock->expects($this->never())->method('error');
        $this->loggerMock->expects($this->never())->method('warning');

        $this->listener->handle(new TaskAssigned(1, 2, 3));
    }
}
