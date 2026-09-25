<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Domain\Port\PushRetractionInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Notifications\Infrastructure\Adapter\InAppNotificationAdapter;
use App\Notifications\Infrastructure\Persistence\InMemoryInAppNotificationRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class InAppNotificationAdapterTest extends TestCase
{
    private InMemoryInAppNotificationRepository $notifications;

    private InAppNotificationAdapter $adapter;

    private TrayRetractions $retractions;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryInAppNotificationRepository();
        $this->now = new DateTimeImmutable('2026-09-14 19:00:00');
        $this->retractions = new TrayRetractions();
        $this->adapter = new InAppNotificationAdapter($this->notifications, new FixedClock($this->now), $this->retractions);
    }

    public function testItSupportsOnlyTheInAppChannel(): void
    {
        $this->assertTrue($this->adapter->supports(NotificationChannel::inApp()));
        $this->assertFalse($this->adapter->supports(NotificationChannel::email()));
        $this->assertFalse($this->adapter->supports(NotificationChannel::sms()));
    }

    public function testItStoresTheNotificationForTheUser(): void
    {
        $userId = Uuid::generate();

        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create('You earned 10 points', 'Task approved', ['points' => 10]),
            NotificationChannel::inApp()
        );

        $stored = $this->notifications->unreadFor($userId, 10);

        $this->assertCount(1, $stored);
        $this->assertSame('You earned 10 points', $stored[0]->message());
        $this->assertSame('Task approved', $stored[0]->subject());
        $this->assertSame(['points' => 10], $stored[0]->parameters());
        $this->assertFalse($stored[0]->isRead());
        $this->assertSame($this->now->format(DATE_ATOM), $stored[0]->createdAt()->format(DATE_ATOM));
    }

    public function testItStoresNothingForAnotherUser(): void
    {
        $this->adapter->send(
            Recipient::userId(Uuid::generate()->value()),
            NotificationMessage::create('Private business'),
            NotificationChannel::inApp()
        );

        $this->assertCount(0, $this->notifications->unreadFor(Uuid::generate(), 10));
    }

    public function testItRejectsAnotherChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InAppNotificationAdapter only supports the in_app channel');

        $this->adapter->send(
            Recipient::userId(Uuid::generate()->value()),
            NotificationMessage::create('Message'),
            NotificationChannel::email()
        );
    }

    public function testItRejectsARecipientThatIsNotAUserId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient must be a user id for the in_app channel');

        $this->adapter->send(
            Recipient::email('someone@example.com'),
            NotificationMessage::create('Message'),
            NotificationChannel::inApp()
        );
    }

    public function testTheNewestWordOnATopicReplacesTheOlderOnes(): void
    {
        $userId = Uuid::generate();
        $other = Uuid::generate();
        $this->send($userId, 'Przypisano zadanie', ['tag' => 'task-1']);
        $this->send($other, 'Przypisano zadanie', ['tag' => 'task-1']);
        $this->send($userId, 'Inne zadanie', ['tag' => 'task-2']);

        $this->send($userId, 'Zadanie zatwierdzone', ['tag' => 'task-1']);

        $messages = array_map(static fn ($n) => $n->message(), $this->notifications->unreadFor($userId, 10));
        sort($messages);
        $this->assertSame(['Inne zadanie', 'Zadanie zatwierdzone'], $messages);
        $this->assertCount(1, $this->notifications->unreadFor($other, 10));
    }

    public function testAReplacedWordThatNoPushFollowsLeavesThePhoneTray(): void
    {
        $userId = Uuid::generate();
        $this->send($userId, 'Przypisano zadanie', ['tag' => 'task-1', 'delivery_channels' => ['in_app', 'push']]);

        $this->send($userId, 'Zadanie zatwierdzone', ['tag' => 'task-1', 'delivery_channels' => ['in_app']]);

        $this->assertSame([[[$userId->value()], ['task-1']]], $this->retractions->calls);
    }

    public function testAPushWithTheSameTagTakesTheOldPlaceByItself(): void
    {
        $userId = Uuid::generate();
        $this->send($userId, 'Przypisano zadanie', ['tag' => 'task-1', 'delivery_channels' => ['in_app', 'push']]);
        $this->send($userId, 'Nowy temat', ['tag' => 'task-2', 'delivery_channels' => ['in_app']]);

        $this->send($userId, 'Zadanie zatwierdzone', ['tag' => 'task-1', 'delivery_channels' => ['in_app', 'push']]);

        $this->assertSame([], $this->retractions->calls);
    }

    public function testItKeepsTheIdThePushWasToldAbout(): void
    {
        $userId = Uuid::generate();
        $id = Uuid::generate();

        $this->send($userId, 'Zadanie czeka', ['notification_id' => $id->value(), 'ttl' => 3600, 'event' => 'task_completed']);

        $stored = $this->notifications->findById($id);
        $this->assertNotNull($stored);
        $this->assertSame('task_completed', $stored->event());
        // Delivery bookkeeping is not something the recipient reads.
        $this->assertSame(['event' => 'task_completed'], $stored->parameters());
    }

    private function send(Uuid $userId, string $message, array $parameters): void
    {
        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create($message, null, $parameters),
            NotificationChannel::inApp()
        );
    }
}

final class TrayRetractions implements PushRetractionInterface
{
    /**
     * @var list<array{0: list<string>, 1: list<string>}>
     */
    public array $calls = [];

    public function retract(array $userIds, array $tags): void
    {
        $this->calls[] = [array_map(static fn (Uuid $userId): string => $userId->value(), $userIds), $tags];
    }
}
