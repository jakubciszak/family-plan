<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\EventSubscriber\TaskApprovedEventSubscriber;
use App\Notifications\Communication\EventSubscriber\TaskCompletedEventSubscriber;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TaskExecutionNotificationTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
    }

    public function testAdminsAreToldWhenSomebodyFinishesTheirTask(): void
    {
        $doer = $this->user('Dziecko', 'dziecko@example.com', Role::USER);
        $admin = $this->user('Rodzic', 'rodzic@example.com', Role::ADMIN);
        $execution = $this->execution($doer->id());

        $subscriber = new TaskCompletedEventSubscriber(
            $this->orchestrator([$doer, $admin], [$admin]),
            $this->executionRepository($execution),
            $this->userRepository([$doer, $admin], [$admin])
        );

        $subscriber->onTaskCompleted(
            new TaskExecutionCompleted($execution->id(), $doer->id(), new DateTimeImmutable())
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertSame('rodzic@example.com', $sent[0]['recipient']);
        $this->assertStringContainsString('Dziecko', $sent[0]['message']);
        $this->assertStringContainsString('Zmywanie', $sent[0]['message']);
    }

    public function testTheDoerIsToldWhenTheirTaskIsApproved(): void
    {
        $doer = $this->user('Dziecko', 'dziecko@example.com', Role::USER);
        $execution = $this->execution($doer->id());

        $subscriber = new TaskApprovedEventSubscriber(
            $this->orchestrator([$doer], []),
            $this->executionRepository($execution)
        );

        $subscriber->onTaskApproved(
            new TaskExecutionApproved($execution->id(), Uuid::generate(), new DateTimeImmutable())
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertSame('dziecko@example.com', $sent[0]['recipient']);
        $this->assertStringContainsString('40 points', $sent[0]['message']);
    }

    public function testNothingIsSentForAnExecutionThatIsGone(): void
    {
        $repository = $this->createStub(TaskExecutionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $subscriber = new TaskApprovedEventSubscriber($this->orchestrator([], []), $repository);
        $subscriber->onTaskApproved(
            new TaskExecutionApproved(Uuid::generate(), Uuid::generate(), new DateTimeImmutable())
        );

        $this->assertCount(0, $this->adapter->getSentNotifications());
    }

    private function execution(Uuid $assignee): TaskExecution
    {
        return TaskExecution::takeFromTemplate(
            Uuid::generate(),
            Uuid::generate(),
            TaskName::fromString('Zmywanie po obiedzie'),
            'opis',
            Points::fromInt(40),
            $assignee,
            new DateTimeImmutable()
        );
    }

    private function user(string $name, string $email, Role $role): User
    {
        return User::create(
            Uuid::generate(),
            $name,
            Email::fromString($email),
            password_hash('password123', PASSWORD_BCRYPT),
            $role
        );
    }

    /**
     * @param User[] $users
     * @param User[] $admins
     */
    private function orchestrator(array $users, array $admins): NotificationOrchestrator
    {
        $settings = $this->createStub(UserSettingsRepositoryInterface::class);
        $settings->method('findByUserId')->willReturn(null);

        return new NotificationOrchestrator(
            new NotificationFacade([$this->adapter]),
            $this->userRepository($users, $admins),
            $settings
        );
    }

    /**
     * @param User[] $users
     * @param User[] $admins
     */
    private function userRepository(array $users, array $admins): UserRepositoryInterface
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(
            function (Uuid $id) use ($users): ?User {
                foreach ($users as $user) {
                    if ($user->id()->value() === $id->value()) {
                        return $user;
                    }
                }

                return null;
            }
        );
        $repository->method('findAdmins')->willReturn($admins);

        return $repository;
    }

    private function executionRepository(TaskExecution $execution): TaskExecutionRepositoryInterface
    {
        $repository = $this->createStub(TaskExecutionRepositoryInterface::class);
        $repository->method('findById')->willReturn($execution);

        return $repository;
    }
}
