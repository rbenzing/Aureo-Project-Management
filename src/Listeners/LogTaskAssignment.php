<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Services\LoggerService;

/**
 * Log Task Assignment Listener
 *
 * Logs task assignments to activity log
 */
class LogTaskAssignment
{
    private LoggerService $logger;

    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
    }

    /**
     * Handle the TaskAssigned event
     *
     * @param TaskAssigned $event
     */
    public function handle(TaskAssigned $event): void
    {
        $this->logger->info('Task assigned', [
            'task_id' => $event->getTaskId(),
            'user_id' => $event->getUserId(),
            'assigned_by' => $event->getAssignedBy(),
            'timestamp' => date('Y-m-d H:i:s', (int)$event->getTimestamp()),
        ]);
    }
}
