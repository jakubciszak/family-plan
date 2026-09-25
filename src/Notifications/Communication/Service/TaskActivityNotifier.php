<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Service;

use App\Notifications\Application\Service\HandledNotifications;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class TaskActivityNotifier
{
    public function __construct(
        private NotificationOrchestrator $notifications,
        private TaskTemplateRepositoryInterface $templates,
        private TeamMembershipRepositoryInterface $memberships,
        private ?HandledNotifications $handled = null
    ) {
    }

    public function changed(TaskExecution $execution, string $event, string $subject, string $message, bool $notifyAdmins = false): void
    {
        // A task that leaves the approval queue any other way than approval or rejection, e.g. removed.
        if ($event === NotificationEvent::TASK_REMOVED || $event === NotificationEvent::TASK_ABANDONED) {
            $this->handled?->approvalHandled($execution->id());
        }

        $assignee = $execution->assignedUserId();
        if ($assignee === null) {
            return;
        }

        $recipients = [$assignee->value() => $assignee];
        $template = $execution->taskTemplateId() === null ? null : $this->templates->findById($execution->taskTemplateId());
        if ($notifyAdmins && $template?->teamId() !== null) {
            foreach ($this->memberships->ofTeam($template->teamId()) as $membership) {
                if ($membership->isAdmin()) {
                    $recipients[$membership->userId()->value()] = $membership->userId();
                }
            }
        }

        foreach ($recipients as $recipient) {
            $this->notifications->notifyUser(NotificationEvent::fromString($event), $recipient, $message, $subject, [
                'event' => $event,
                'task_id' => $execution->id()->value(),
                'task_name' => $execution->name()?->value() ?? $template?->name()->value(),
                'assigned_user_id' => $assignee->value(),
                'reason' => $execution->rejectionReason(),
                'url' => '/tasks',
                'tag' => 'task-' . $execution->id()->value(),
            ]);
        }
    }
}
