<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication\Application;

use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Infrastructure\Persistence\InMemoryNotificationPolicyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

class NotificationPolicyProviderTest extends TestCase
{
    private InMemoryNotificationPolicyRepository $repository;

    private NotificationPolicyProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new InMemoryNotificationPolicyRepository();
        $this->provider = new NotificationPolicyProvider($this->repository);
    }

    public function testEventWithoutAPolicyKeepsSendingEmail(): void
    {
        $this->assertSame(['email'], $this->provider->channelsFor(NotificationEvent::taskApproved())->toArray());
    }

    public function testStoredPolicyWins(): void
    {
        $this->repository->save(NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::taskApproved(),
            NotificationChannels::fromArray(['in_app'])
        ));

        $this->assertSame(['in_app'], $this->provider->channelsFor(NotificationEvent::taskApproved())->toArray());
    }

    public function testEventSwitchedOffCompletelyResolvesToNoChannels(): void
    {
        $this->repository->save(NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::taskCompleted(),
            NotificationChannels::none()
        ));

        $this->assertTrue($this->provider->channelsFor(NotificationEvent::taskCompleted())->isEmpty());
    }

    public function testAccountActivationCannotBeConfigured(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::accountActivation(),
            NotificationChannels::none()
        );
    }

    public function testMatrixCoversEveryEventInTheCatalog(): void
    {
        $matrix = $this->provider->matrix();

        $this->assertSame(
            ['task_completed', 'task_approved', 'user_welcome', 'account_activation', 'payout_offered', 'streak_at_risk'],
            array_keys($matrix)
        );
    }
}
