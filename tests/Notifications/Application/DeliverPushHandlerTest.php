<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Command\DeliverPushCommand;
use App\Notifications\Application\Handler\DeliverPushHandler;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Infrastructure\Persistence\InMemoryPushSubscriptionRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DeliverPushHandlerTest extends TestCase
{
    private InMemoryPushSubscriptionRepository $subscriptions;

    private RecordingPushSender $sender;

    private DeliverPushHandler $handler;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->subscriptions = new InMemoryPushSubscriptionRepository();
        $this->sender = new RecordingPushSender();
        $this->now = new DateTimeImmutable('2026-09-15 18:00:00');
        $this->handler = new DeliverPushHandler(
            $this->subscriptions,
            $this->sender,
            new FixedClock($this->now)
        );
    }

    public function testItReachesEveryDeviceTheUserRegistered(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $this->givenDevice($userId, 'tablet');
        $this->givenDevice(Uuid::generate(), 'somebody-elses');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', 'Family Plan'));

        $this->assertSame(
            ['https://push.example.com/phone', 'https://push.example.com/tablet'],
            $this->sender->endpoints
        );
    }

    public function testDeliveryIsRemembered(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka'));

        $this->assertEquals($this->now, $this->subscriptions->findForUser($userId)[0]->lastUsedAt());
    }

    public function testDeviceThatIsGoneStopsBeingKept(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $this->sender->answer = PushDelivery::Gone;

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka'));

        $this->assertSame(0, $this->subscriptions->countForUser($userId));
    }

    public function testDeviceSurvivesAFailureThatIsNotItsFault(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $this->sender->answer = PushDelivery::Failed;

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka'));

        $this->assertSame(1, $this->subscriptions->countForUser($userId));
        $this->assertNull($this->subscriptions->findForUser($userId)[0]->lastUsedAt());
    }

    public function testNothingHappensForSomebodyWithoutDevices(): void
    {
        ($this->handler)(new DeliverPushCommand(Uuid::generate()->value(), 'Zadanie czeka'));

        $this->assertSame([], $this->sender->endpoints);
    }

    public function testTheSubjectAndParametersReachTheSender(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        ($this->handler)(new DeliverPushCommand(
            $userId->value(),
            'Zadanie czeka',
            'Family Plan',
            ['url' => '/tasks']
        ));

        $this->assertSame('Family Plan', $this->sender->lastMessage?->subject());
        $this->assertSame(['url' => '/tasks'], $this->sender->lastMessage?->additionalParameters());
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

    public ?NotificationMessage $lastMessage = null;

    /**
     * @var list<string>
     */
    public array $endpoints = [];

    public function send(PushSubscription $subscription, NotificationMessage $message): PushDelivery
    {
        $this->endpoints[] = $subscription->endpoint();
        $this->lastMessage = $message;

        return $this->answer;
    }
}
