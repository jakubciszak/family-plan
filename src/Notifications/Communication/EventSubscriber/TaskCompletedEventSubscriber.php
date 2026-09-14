<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class TaskCompletedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator,
        private TaskExecutionRepositoryInterface $executionRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutionCompleted::class => 'onTaskCompleted',
        ];
    }

    public function onTaskCompleted(TaskExecutionCompleted $event): void
    {
        $execution = $this->executionRepository->findById($event->executionId());

        if ($execution === null || $execution->name() === null) {
            return;
        }

        $user = $this->userRepository->findById($event->userId());

        if ($user === null) {
            return;
        }

        $message = sprintf(
            'User %s has completed task "%s".',
            $user->name(),
            $execution->name()->value()
        );

        foreach ($this->userRepository->findAdmins() as $admin) {
            $this->notificationOrchestrator->notifyUser(
                NotificationEvent::taskCompleted(),
                $admin->id(),
                $message,
                'Task Completed',
                [
                    'task_id' => $execution->id()->value(),
                    'task_name' => $execution->name()->value(),
                    'user_id' => $user->id()->value(),
                    'user_name' => $user->name(),
                ]
            );
        }
    }
}
