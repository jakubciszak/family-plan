<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Application\Service\PushAnnouncements;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Notifications\Infrastructure\Persistence\InMemoryPushSubscriptionRepository;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

class PushAnnouncementsTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;
    private InMemoryPushSubscriptionRepository $subscriptions;
    private PushAnnouncements $announcements;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
        $this->subscriptions = new InMemoryPushSubscriptionRepository();
        $this->announcements = new PushAnnouncements(
            $this->subscriptions,
            new NotificationFacade([$this->adapter])
        );
    }

    public function testEverybodyWithADeviceIsReachedOnce(): void
    {
        $mum = Uuid::generate();
        $child = Uuid::generate();

        $this->giveDevice($mum, 'phone');
        $this->giveDevice($mum, 'laptop');
        $this->giveDevice($child, 'tablet');

        $reached = $this->announcements->toEveryone('Obiad za dziesięć minut');

        $this->assertSame(2, $reached);
        $this->assertSame(
            [$mum->value(), $child->value()],
            array_column($this->adapter->getSentNotifications(), 'recipient')
        );
    }

    public function testNobodyIsReachedWhenNoDeviceIsRegistered(): void
    {
        $this->assertSame(0, $this->announcements->toEveryone('Halo'));
        $this->assertSame([], $this->adapter->getSentNotifications());
    }

    public function testOnePersonIsReached(): void
    {
        $child = Uuid::generate();
        $this->giveDevice($child, 'tablet');

        $this->assertTrue($this->announcements->toOne($child, 'Twoja kolej na śmieci', 'Przypomnienie'));

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertSame($child->value(), $sent[0]['recipient']);
        $this->assertSame('Przypomnienie', $sent[0]['subject']);
        $this->assertSame('push', $sent[0]['channel']);
    }

    public function testPersonWithoutADeviceIsNotReached(): void
    {
        $this->assertFalse($this->announcements->toOne(Uuid::generate(), 'Halo'));
        $this->assertSame([], $this->adapter->getSentNotifications());
    }

    public function testMessageWithoutATitleFallsBackToTheApplicationName(): void
    {
        $child = Uuid::generate();
        $this->giveDevice($child, 'tablet');

        $this->announcements->toOne($child, 'Halo', '   ');

        $this->assertSame('Family Plan', $this->adapter->getSentNotifications()[0]['subject']);
    }

    private function giveDevice(Uuid $userId, string $name): void
    {
        $this->subscriptions->save(PushSubscription::register(
            Uuid::generate(),
            $userId,
            "https://push.example.com/{$name}-{$userId->value()}",
            'a-public-key',
            'an-auth-token',
            null,
            new \DateTimeImmutable('2026-09-16 10:00:00')
        ));
    }
}
