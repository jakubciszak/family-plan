<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Allowance\Domain\Event\PayoutOffered;
use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\Service\ChannelResolver;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\EventSubscriber\PayoutOfferedEventSubscriber;
use App\Notifications\Communication\Infrastructure\Persistence\InMemoryNotificationPolicyRepository;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use DateTimeImmutable;

class PayoutNotificationTest extends IntegrationTestCase
{
    private InMemoryNotificationAdapter $sent;

    private InMemoryNotificationPolicyRepository $policies;

    private PayoutOfferedEventSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = new InMemoryNotificationAdapter();
        $this->policies = new InMemoryNotificationPolicyRepository();
        $this->subscriber = new PayoutOfferedEventSubscriber(new NotificationOrchestrator(
            new NotificationFacade([$this->sent]),
            $this->service(UserRepositoryInterface::class),
            $this->service(UserSettingsRepositoryInterface::class),
            new NotificationPolicyProvider($this->policies),
            new ChannelResolver()
        ));
    }

    public function testTheOwnerIsToldThereIsMoneyToCollect(): void
    {
        $child = $this->user('Dziecko');
        $this->policies->save(NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::payoutOffered(),
            NotificationChannels::fromArray([NotificationChannels::IN_APP])
        ));

        $this->subscriber->onPayoutOffered(new PayoutOffered(
            Uuid::generate(),
            $child->id(),
            2550,
            Uuid::generate(),
            new DateTimeImmutable()
        ));

        $sent = $this->sent->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertSame($child->id()->value(), $sent[0]['recipient']);
        $this->assertStringContainsString('25,50 zł', $sent[0]['message']);
    }

    public function testNobodyElseHearsAboutIt(): void
    {
        $child = $this->user('Dziecko');
        $this->user('Rodzic');
        $this->policies->save(NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::payoutOffered(),
            NotificationChannels::fromArray([NotificationChannels::IN_APP])
        ));

        $this->subscriber->onPayoutOffered(new PayoutOffered(
            Uuid::generate(),
            $child->id(),
            1000,
            Uuid::generate(),
            new DateTimeImmutable()
        ));

        $this->assertCount(1, $this->sent->getSentNotifications());
    }

    public function testTurningTheEventOffStopsIt(): void
    {
        $child = $this->user('Dziecko');
        $this->policies->save(NotificationPolicy::create(
            Uuid::generate(),
            NotificationEvent::payoutOffered(),
            NotificationChannels::none()
        ));

        $this->subscriber->onPayoutOffered(new PayoutOffered(
            Uuid::generate(),
            $child->id(),
            1000,
            Uuid::generate(),
            new DateTimeImmutable()
        ));

        $this->assertSame([], $this->sent->getSentNotifications());
    }
}
