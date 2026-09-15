<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Notifications\Infrastructure\Adapter\PushNotificationAdapter;
use App\Notifications\Infrastructure\Persistence\InMemoryPushSubscriptionRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PushNotificationAdapterTest extends TestCase
{
    private InMemoryPushSubscriptionRepository $subscriptions;

    private RecordingPushSender $sender;

    private PushNotificationAdapter $adapter;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->subscriptions = new InMemoryPushSubscriptionRepository();
        $this->sender = new RecordingPushSender();
        $this->now = new DateTimeImmutable('2026-09-15 18:00:00');
        $this->adapter = new PushNotificationAdapter(
            $this->subscriptions,
            $this->sender,
            new FixedClock($this->now)
        );
    }

    public function testItSupportsOnlyThePushChannel(): void
    {
        $this->assertTrue($this->adapter->supports(NotificationChannel::push()));
        $this->assertFalse($this->adapter->supports(NotificationChannel::inApp()));
        $this->assertFalse($this->adapter->supports(NotificationChannel::email()));
    }

    public function testItReachesEveryDeviceTheUserRegistered(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $this->givenDevice($userId, 'tablet');
        $this->givenDevice(Uuid::generate(), 'somebody-elses');

        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create('Zadanie czeka', 'Family Plan'),
            NotificationChannel::push()
        );

        $this->assertSame(
            ['https://push.example.com/phone', 'https://push.example.com/tablet'],
            $this->sender->endpoints
        );
    }

    public function testDeliveryIsRemembered(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create('Zadanie czeka'),
            NotificationChannel::push()
        );

        $this->assertEquals($this->now, $this->subscriptions->findForUser($userId)[0]->lastUsedAt());
    }

    public function testDeviceThatIsGoneStopsBeingKept(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $this->sender->answer = PushDelivery::Gone;

        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create('Zadanie czeka'),
            NotificationChannel::push()
        );

        $this->assertSame(0, $this->subscriptions->countForUser($userId));
    }

    public function testDeviceSurvivesAFailureThatIsNotItsFault(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $this->sender->answer = PushDelivery::Failed;

        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create('Zadanie czeka'),
            NotificationChannel::push()
        );

        $this->assertSame(1, $this->subscriptions->countForUser($userId));
        $this->assertNull($this->subscriptions->findForUser($userId)[0]->lastUsedAt());
    }

    public function testItRefusesChannelsItDoesNotOwn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adapter->send(
            Recipient::userId(Uuid::generate()->value()),
            NotificationMessage::create('Zadanie czeka'),
            NotificationChannel::inApp()
        );
    }

    public function testItNeedsAUserIdRatherThanAnAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adapter->send(
            Recipient::email('parent@example.com'),
            NotificationMessage::create('Zadanie czeka'),
            NotificationChannel::push()
        );
    }

    private function givenDevice(Uuid $userId, string $name): void
    {
        $this->subscriptions->save(PushSubscription::register(
            Uuid::generate(),
            $userId,
            "https://push.example.com/{$name}",
            'a-public-key',
            'an-auth-token',
            null,
            $this->now
        ));
    }
}

final class RecordingPushSender implements PushSenderInterface
{
    public PushDelivery $answer = PushDelivery::Delivered;

    /**
     * @var list<string>
     */
    public array $endpoints = [];

    public function send(PushSubscription $subscription, NotificationMessage $message): PushDelivery
    {
        $this->endpoints[] = $subscription->endpoint();

        return $this->answer;
    }
}
