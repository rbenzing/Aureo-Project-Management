<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Models\Task;
use App\Models\User;
use App\Services\LoggerService;
use App\Utils\Email;

/**
 * Send Task Assignment Email Listener
 *
 * Sends email notification when task is assigned
 */
class SendTaskAssignmentEmail
{
    private Task $taskModel;
    private User $userModel;
    private LoggerService $logger;

    /**
     * Left null rather than defaulted so Email is still constructed lazily, only
     * when a message is actually about to be sent. Injecting it is what makes the
     * send path testable without opening an SMTP connection.
     */
    private ?Email $email;

    public function __construct(
        ?Task $taskModel = null,
        ?User $userModel = null,
        ?LoggerService $logger = null,
        ?Email $email = null
    ) {
        $this->taskModel = $taskModel ?? new Task();
        $this->userModel = $userModel ?? new User();
        $this->logger = $logger ?? new LoggerService();
        $this->email = $email;
    }

    /**
     * Handle the TaskAssigned event
     *
     * @param TaskAssigned $event
     */
    public function handle(TaskAssigned $event): void
    {
        try {
            $task = $this->taskModel->find($event->getTaskId());
            $user = $this->userModel->find($event->getUserId());

            if (!$task || !$user) {
                return;
            }

            $userName = "{$user->first_name} {$user->last_name}";
            $subject = "Task Assigned: {$task->title}";

            $dueDate = $task->due_date
                ? '<p><strong>Due:</strong> ' . htmlspecialchars($task->due_date, ENT_QUOTES, 'UTF-8') . '</p>'
                : '';

            $body = '<h2>You have been assigned a task</h2>'
                . '<p>Hi ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>The following task has been assigned to you:</p>'
                . '<p><strong>' . htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                . $dueDate
                . '<p>Please log in to view the full details.</p>';

            $email = $this->email ?? new Email();
            $sent = $email->sendHtml($user->email, $subject, $body);

            $this->logger->info('Task assignment email ' . ($sent ? 'sent' : 'failed'), [
                'task_id' => $event->getTaskId(),
                'task_title' => $task->title,
                'user_email' => $user->email,
                'user_name' => $userName,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send task assignment email', [
                'error' => $e->getMessage(),
                'task_id' => $event->getTaskId(),
            ]);
        }
    }
}
