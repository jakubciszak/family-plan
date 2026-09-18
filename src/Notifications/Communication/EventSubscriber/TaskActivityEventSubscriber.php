<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Service\TaskActivityNotifier;
use App\TaskManagement\Domain\Event\TaskExecutionCreated;
use App\TaskManagement\Domain\Event\TaskExecutionAssigned;
use App\TaskManagement\Domain\Event\TaskExecutionRejected;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class TaskActivityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TaskActivityNotifier $notifications,
        private TaskExecutionRepositoryInterface $executions,
        private TaskTemplateRepositoryInterface $templates
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [TaskExecutionCreated::class => 'assigned', TaskExecutionAssigned::class => 'assigned', TaskExecutionRejected::class => 'rejected'];
    }

    public function assigned(TaskExecutionCreated|TaskExecutionAssigned $event): void
    {
        $execution = $this->executions->findById($event->executionId());
        if ($execution === null || $execution->assignedUserId() === null) {
            return;
        }

        $template = $execution->taskTemplateId() === null ? null : $this->templates->findById($execution->taskTemplateId());
        $name = $execution->name()?->value() ?? $template?->name()->value();
        $this->notifications->changed($execution, 'task_assigned', 'Przypisano zadanie', sprintf('Zadanie „%s” zostało przypisane do wykonania.', $name));
    }

    public function rejected(TaskExecutionRejected $event): void
    {
        $execution = $this->executions->findById($event->executionId());
        if ($execution === null) {
            return;
        }

        $this->notifications->changed($execution, 'task_rejected', 'Zadanie cofnięte', sprintf('Zadanie „%s” zostało cofnięte do poprawy. Powód: %s', $execution->name()?->value(), $execution->rejectionReason()));
    }
}
