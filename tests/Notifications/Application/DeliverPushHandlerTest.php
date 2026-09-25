<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Command\DeliverPushCommand;
use App\Notifications\Application\Handler\DeliverPushHandler;
use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;
use App\Notifications\Infrastructure\Persistence\InMemoryInAppNotificationRepository;
use App\Notifications\Infrastructure\Persistence\InMemoryNativePushDeviceRepository;
use App\Notifications\Infrastructure\Persistence\InMemoryPushSubscriptionRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DeliverPushHandlerTest extends TestCase
{
    private InMemoryPushSubscriptionRepository $subscriptions;

    private RecordingPushSender $sender;

    private InMemoryInAppNotificationRepository $inApp;

    private InMemoryNativePushDeviceRepository $phones;

    private RecordingNativePushSender $phoneSender;

    private DeliverPushHandler $handler;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->subscriptions = new InMemoryPushSubscriptionRepository();
        $this->sender = new RecordingPushSender();
        $this->now = new DateTimeImmutable('2026-09-15 18:00:00');
        $clock = new FixedClock($this->now);
        $this->inApp = new InMemoryInAppNotificationRepository($clock);
        $this->phones = new InMemoryNativePushDeviceRepository();
        $this->phoneSender = new RecordingNativePushSender();
        $this->handler = new DeliverPushHandler(
            $this->subscriptions,
            $this->sender,
            $clock,
            null,
            $this->inApp,
            $this->phones,
            $this->phoneSender
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

    public function testAPushServiceKeepsItHoursNotWeeks(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', null, ['tag' => 'task-1']));

        $this->assertSame(PushOptions::DEFAULT_TTL, $this->sender->lastOptions?->ttl);
        $this->assertSame(32, strlen((string) $this->sender->lastOptions?->topic()));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', (string) $this->sender->lastOptions?->topic());
    }

    public function testAWarningThatRunsOutSoonIsKeptOnlyUntilThen(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Seria', null, [
            'ttl' => 21600,
            'expires_at' => $this->now->modify('+2 hours')->format(DATE_ATOM),
        ]));

        $this->assertSame(7200, $this->sender->lastOptions?->ttl);
    }

    public function testNothingGoesOutOnceTheNotificationWasHandled(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $notification = $this->givenInApp($userId, 'task-1');
        $notification->resolve($this->now);

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', null, [
            'notification_id' => $notification->id()->value(),
        ]));

        $this->assertSame([], $this->sender->endpoints);
    }

    public function testNothingGoesOutOnceTheRecipientReadItInTheApp(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $notification = $this->givenInApp($userId, 'task-1');
        $notification->markAsRead($this->now);

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', null, [
            'notification_id' => $notification->id()->value(),
        ]));

        $this->assertSame([], $this->sender->endpoints);
    }

    public function testAnOpenNotificationStillGoesOut(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');
        $notification = $this->givenInApp($userId, 'task-1');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', null, [
            'notification_id' => $notification->id()->value(),
        ]));

        $this->assertSame(['https://push.example.com/phone'], $this->sender->endpoints);
    }

    public function testAPushThatWaitedInTheQueueTooLongIsDropped(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', null, [
            'ttl' => 3600,
            'queued_at' => $this->now->modify('-2 hours')->format(DATE_ATOM),
        ]));

        $this->assertSame([], $this->sender->endpoints);
    }

    public function testAnExpiredWarningIsDropped(): void
    {
        $userId = Uuid::generate();
        $this->givenDevice($userId, 'phone');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Seria', null, [
            'expires_at' => $this->now->modify('-1 minute')->format(DATE_ATOM),
        ]));

        $this->assertSame([], $this->sender->endpoints);
    }

    public function testPhonesWithTheAppGetItThroughFirebase(): void
    {
        $userId = Uuid::generate();
        $this->givenPhone($userId, 'phone-token');
        $this->givenPhone(Uuid::generate(), 'somebody-elses');

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka', 'Family Plan', ['tag' => 'task-1']));

        $this->assertSame(['phone-token'], $this->phoneSender->tokens);
        $this->assertSame('task-1', $this->phoneSender->lastOptions?->tag);
        $this->assertEquals($this->now, $this->phones->findByToken('phone-token')?->lastUsedAt());
    }

    public function testAPhoneFirebaseForgotStopsBeingKept(): void
    {
        $userId = Uuid::generate();
        $this->givenPhone($userId, 'phone-token');
        $this->phoneSender->answer = PushDelivery::Gone;

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka'));

        $this->assertNull($this->phones->findByToken('phone-token'));
    }

    public function testPhonesWaitUntilFirebaseIsConfigured(): void
    {
        $userId = Uuid::generate();
        $this->givenPhone($userId, 'phone-token');
        $this->phoneSender->configured = false;

        ($this->handler)(new DeliverPushCommand($userId->value(), 'Zadanie czeka'));

        $this->assertSame([], $this->phoneSender->tokens);
        $this->assertNotNull($this->phones->findByToken('phone-token'));
    }

    private function givenInApp(Uuid $userId, string $tag): InAppNotification
    {
        $notification = InAppNotification::raise(Uuid::generate(), $userId, 'Zadanie czeka', null, ['tag' => $tag], $this->now);
        $this->inApp->save($notification);

        return $notification;
    }

    private function givenPhone(Uuid $userId, string $token): void
    {
        $this->phones->save(NativePushDevice::register(Uuid::generate(), $userId, NativePushDevice::ANDROID, $token, null, $this->now));
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

    public ?PushOptions $lastOptions = null;

    public function send(PushSubscription $subscription, NotificationMessage $message, ?PushOptions $options = null): PushDelivery
    {
        $this->endpoints[] = $subscription->endpoint();
        $this->lastMessage = $message;
        $this->lastOptions = $options;

        return $this->answer;
    }
}

final class RecordingNativePushSender implements NativePushSenderInterface
{
    public PushDelivery $answer = PushDelivery::Delivered;

    public bool $configured = true;

    public ?PushOptions $lastOptions = null;

    /**
     * @var list<string>
     */
    public array $tokens = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(NativePushDevice $device, NotificationMessage $message, PushOptions $options): PushDelivery
    {
        $this->tokens[] = $device->token();
        $this->lastOptions = $options;

        return $this->answer;
    }
}
