<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\TaskManagement\Domain\Event\TaskApproved;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Subscribes to TaskApproved events and sends notifications to the user
 */
final readonly class TaskApprovedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator,
        private TaskRepositoryInterface $taskRepository,
        private MessageBusInterface $eventBus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskApproved::class => 'onTaskApproved',
        ];
    }

    public function onTaskApproved(TaskApproved $event): void
    {
        $task = $this->taskRepository->findById($event->taskId());
        
        if ($task === null) {
            return;
        }

        $assignedUserId = $task->assignedUserId();
        if ($assignedUserId === null) {
            return;
        }

        $message = sprintf(
            'Your task "%s" has been approved! You earned %d points.',
            $task->name()->value(),
            $task->points()->value()
        );

        $this->notificationOrchestrator->notifyUser(
            $assignedUserId,
            $message,
            'Task Approved',
            [
                'task_id' => $task->id()->value(),
                'task_name' => $task->name()->value(),
                'points' => $task->points()->value(),
                'admin_id' => $event->adminId()->value(),
            ]
        );
    }
}
