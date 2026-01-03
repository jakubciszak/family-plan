<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\TaskManagement\Domain\Event\TaskCompleted;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to TaskCompleted events and sends notifications to admin users
 */
final readonly class TaskCompletedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator,
        private TaskRepositoryInterface $taskRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskCompleted::class => 'onTaskCompleted',
        ];
    }

    public function onTaskCompleted(TaskCompleted $event): void
    {
        $task = $this->taskRepository->findById($event->taskId());
        
        if ($task === null) {
            return;
        }

        $user = $this->userRepository->findById($event->userId());
        if ($user === null) {
            return;
        }

        // Get all admin users
        $admins = $this->userRepository->findAdmins();
        
        $message = sprintf(
            'User %s has completed task "%s".',
            $user->name(),
            $task->name()->value()
        );

        foreach ($admins as $admin) {
            $this->notificationOrchestrator->notifyUser(
                $admin->id(),
                $message,
                'Task Completed',
                [
                    'task_id' => $task->id()->value(),
                    'task_name' => $task->name()->value(),
                    'user_id' => $event->userId()->value(),
                    'user_name' => $user->name(),
                ]
            );
        }
    }
}
