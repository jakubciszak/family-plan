<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\Service\ChannelResolver;
use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Infrastructure\Persistence\InMemoryNotificationPolicyRepository;
use App\Notifications\Communication\EventSubscriber\TaskApprovedEventSubscriber;
use App\Notifications\Communication\EventSubscriber\TaskCompletedEventSubscriber;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use DateTimeImmutable;
use App\UserManagement\Domain\ValueObject\Role;

class TaskExecutionNotificationTest extends IntegrationTestCase
{
    private InMemoryNotificationAdapter $sentMail;

    private NotificationOrchestrator $orchestrator;

    private TaskExecutionRepositoryInterface $executions;

    private InMemoryNotificationPolicyRepository $policies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentMail = new InMemoryNotificationAdapter();
        $this->executions = $this->service(TaskExecutionRepositoryInterface::class);
        $this->policies = new InMemoryNotificationPolicyRepository();
        $this->orchestrator = new NotificationOrchestrator(
            new NotificationFacade([$this->sentMail]),
            $this->service(UserRepositoryInterface::class),
            $this->service(UserSettingsRepositoryInterface::class),
            new NotificationPolicyProvider($this->policies),
            new ChannelResolver()
        );
    }

    public function testAdminsAreToldWhenSomebodyFinishesTheirTask(): void
    {
        $doer = $this->user('Dziecko');
        $admin = $this->user('Administrator', Role::ADMIN);
        $execution = $this->takenTask($doer);

        $subscriber = new TaskCompletedEventSubscriber(
            $this->orchestrator,
            $this->executions,
            $this->service(UserRepositoryInterface::class)
        );

        $subscriber->onTaskCompleted(
            new TaskExecutionCompleted($execution->id(), $doer->id(), new DateTimeImmutable())
        );

        $mail = $this->mailTo($admin);
        $this->assertNotNull($mail);
        $this->assertStringContainsString('Dziecko', $mail['message']);
        $this->assertStringContainsString('Zmywanie po obiedzie', $mail['message']);
    }

    public function testTheDoerIsToldWhenTheirTaskIsApproved(): void
    {
        $doer = $this->user('Dziecko');
        $execution = $this->takenTask($doer);

        $subscriber = new TaskApprovedEventSubscriber($this->orchestrator, $this->executions);

        $subscriber->onTaskApproved(
            new TaskExecutionApproved($execution->id(), Uuid::generate(), new DateTimeImmutable())
        );

        $mail = $this->mailTo($doer);
        $this->assertNotNull($mail);
        $this->assertStringContainsString('40 points', $mail['message']);
    }

    public function testNothingIsSentForAnExecutionThatIsGone(): void
    {
        $subscriber = new TaskApprovedEventSubscriber($this->orchestrator, $this->executions);

        $subscriber->onTaskApproved(
            new TaskExecutionApproved(Uuid::generate(), Uuid::generate(), new DateTimeImmutable())
        );

        $this->assertCount(0, $this->sentMail->getSentNotifications());
    }

    public function testAnEventTheAdminSwitchedOffIsNotSent(): void
    {
        $doer = $this->user('Dziecko');
        $execution = $this->takenTask($doer);

        $this->policies->save(NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::taskApproved(),
            NotificationChannels::none()
        ));

        $subscriber = new TaskApprovedEventSubscriber($this->orchestrator, $this->executions);

        $subscriber->onTaskApproved(
            new TaskExecutionApproved($execution->id(), Uuid::generate(), new DateTimeImmutable())
        );

        $this->assertCount(0, $this->sentMail->getSentNotifications());
    }

    private function takenTask(User $doer): TaskExecution
    {
        $template = TaskTemplate::create(
            Uuid::generate(),
            TaskName::fromString('Zmywanie po obiedzie'),
            'opis',
            Points::fromInt(40),
            Frequency::fromString('daily'),
            ScheduleConfig::daily()
        );
        $this->service(TaskTemplateRepositoryInterface::class)->save($template);

        $execution = TaskExecution::takeFromTemplate(
            Uuid::generate(),
            $template->id(),
            TaskName::fromString('Zmywanie po obiedzie'),
            'opis',
            Points::fromInt(40),
            $doer->id(),
            new DateTimeImmutable()
        );

        $this->executions->save($execution);

        return $execution;
    }

    private function mailTo(User $user): ?array
    {
        foreach ($this->sentMail->getSentNotifications() as $sent) {
            if ($sent['recipient'] === $user->email()->value()) {
                return $sent;
            }
        }

        return null;
    }
}
