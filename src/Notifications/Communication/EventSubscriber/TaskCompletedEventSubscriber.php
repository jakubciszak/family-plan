<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class TaskCompletedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator,
        private TaskExecutionRepositoryInterface $executionRepository,
        private TaskTemplateRepositoryInterface $templateRepository,
        private TeamMembershipRepositoryInterface $memberships,
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
            '%s czeka na akceptację zadania "%s".',
            $user->name(),
            $execution->name()->value()
        );

        foreach ($this->adminsResponsibleFor($event->userId(), $execution->taskTemplateId()) as $adminId) {
            $this->notificationOrchestrator->notifyUser(
                NotificationEvent::taskCompleted(),
                $adminId,
                $message,
                'Zadanie do akceptacji',
                [
                    'task_id' => $execution->id()->value(),
                    'task_name' => $execution->name()->value(),
                    'user_id' => $user->id()->value(),
                    'user_name' => $user->name(),
                    'url' => '/tasks',
                    'tag' => 'task-' . $execution->id()->value(),
                ]
            );
        }
    }

    /**
     * @return list<Uuid>
     */
    private function adminsResponsibleFor(Uuid $userId, ?Uuid $templateId): array
    {
        $admins = [];

        foreach ($this->teamsOf($userId, $templateId) as $teamId) {
            foreach ($this->memberships->ofTeam($teamId) as $membership) {
                /** @var TeamMembership $membership */
                if (!$membership->isAdmin() || $membership->userId()->equals($userId)) {
                    continue;
                }

                $admins[$membership->userId()->value()] = $membership->userId();
            }
        }

        return array_values($admins);
    }

    /**
     * @return list<Uuid>
     */
    private function teamsOf(Uuid $userId, ?Uuid $templateId): array
    {
        $template = $templateId === null ? null : $this->templateRepository->findById($templateId);
        $teamId = $template?->teamId();

        if ($teamId !== null) {
            return [$teamId];
        }

        return array_map(
            static fn (TeamMembership $membership) => $membership->teamId(),
            $this->memberships->ofUser($userId)
        );
    }
}
