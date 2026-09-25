<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Notifications\Communication\EventSubscriber\TaskCompletedEventSubscriber;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Event\TaskExecutionRejected;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Both parents hear that a task waits for approval; once one of them handles it, the other has nothing waiting.
 */
class HandledNotificationsFlowTest extends IntegrationTestCase
{
    public function testTheOtherParentHasNothingWaitingOnceTheTaskIsApproved(): void
    {
        [$mum, $dad, $execution] = $this->submittedTask();
        $this->assertSame(1, $this->notifications()->countUnreadFor($mum->id()));
        $this->assertSame(1, $this->notifications()->countUnreadFor($dad->id()));

        $this->dispatch(new TaskExecutionApproved($execution->id(), $mum->id(), new DateTimeImmutable()));

        $this->assertSame(0, $this->notifications()->countUnreadFor($mum->id()));
        $this->assertSame(0, $this->notifications()->countUnreadFor($dad->id()));
        $this->assertNotNull($this->notifications()->recentFor($dad->id(), 1)[0]->resolvedAt());
    }

    public function testSendingItBackForCorrectionAlsoEndsTheWait(): void
    {
        [, $dad, $execution] = $this->submittedTask();

        $this->dispatch(new TaskExecutionRejected($execution->id(), new DateTimeImmutable()));

        $this->assertSame(0, $this->notifications()->countUnreadFor($dad->id()));
    }

    /**
     * @return array{User, User, TaskExecution}
     */
    private function submittedTask(): array
    {
        $mum = $this->user('Mama');
        $dad = $this->user('Tata');
        $child = $this->user('Ola');

        $team = Team::create(Uuid::generate(), TeamName::fromString('Rodzina'), null, $mum->id());
        $this->service(TeamRepositoryInterface::class)->save($team);
        $memberships = $this->service(TeamMembershipRepositoryInterface::class);
        $memberships->join($team->id(), $mum->id(), TeamRole::admin());
        $memberships->join($team->id(), $dad->id(), TeamRole::admin());
        $memberships->join($team->id(), $child->id(), TeamRole::member());

        $template = TaskTemplate::create(
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            'opis',
            Points::fromInt(10),
            Frequency::fromString('daily'),
            ScheduleConfig::daily(),
            null,
            null,
            $team->id()
        );
        $this->service(TaskTemplateRepositoryInterface::class)->save($template);

        $execution = TaskExecution::takeFromTemplate(Uuid::generate(), $template->id(), TaskName::fromString('Zmywanie'), 'opis', Points::fromInt(10), $child->id(), new DateTimeImmutable());
        $this->service(TaskExecutionRepositoryInterface::class)->save($execution);

        $this->service(TaskCompletedEventSubscriber::class)->onTaskCompleted(
            new TaskExecutionCompleted($execution->id(), $child->id(), new DateTimeImmutable())
        );

        return [$mum, $dad, $execution];
    }

    private function dispatch(object $event): void
    {
        $this->service(EventDispatcherInterface::class)->dispatch($event);
    }

    private function notifications(): InAppNotificationRepositoryInterface
    {
        return $this->service(InAppNotificationRepositoryInterface::class);
    }
}
