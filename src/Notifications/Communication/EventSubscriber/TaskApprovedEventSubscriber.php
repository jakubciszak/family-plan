<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class TaskApprovedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator,
        private TaskExecutionRepositoryInterface $executionRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutionApproved::class => 'onTaskApproved',
        ];
    }

    public function onTaskApproved(TaskExecutionApproved $event): void
    {
        $execution = $this->executionRepository->findById($event->executionId());

        if ($execution === null || $execution->name() === null || $execution->points() === null) {
            return;
        }

        $assignedUserId = $execution->assignedUserId();

        if ($assignedUserId === null) {
            return;
        }

        $message = sprintf(
            'Zadanie „%s” zostało zatwierdzone. Otrzymujesz %d punktów.',
            $execution->name()->value(),
            $execution->points()->value()
        );

        $this->notificationOrchestrator->notifyUser(
            NotificationEvent::taskApproved(),
            $assignedUserId,
            $message,
            'Zadanie zatwierdzone',
            [
                'event' => 'task_approved',
                'url' => '/tasks',
                'tag' => 'task-' . $execution->id()->value(),
                'task_id' => $execution->id()->value(),
                'task_name' => $execution->name()->value(),
                'points' => $execution->points()->value(),
                'admin_id' => $event->adminId()->value(),
            ]
        );
    }
}
