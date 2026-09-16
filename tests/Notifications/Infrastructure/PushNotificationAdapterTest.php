<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Application\Command\DeliverPushCommand;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Notifications\Infrastructure\Adapter\PushNotificationAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PushNotificationAdapterTest extends TestCase
{
    private CollectingMessageBus $bus;

    private PushNotificationAdapter $adapter;

    protected function setUp(): void
    {
        $this->bus = new CollectingMessageBus();
        $this->adapter = new PushNotificationAdapter($this->bus);
    }

    public function testItSupportsOnlyThePushChannel(): void
    {
        $this->assertTrue($this->adapter->supports(NotificationChannel::push()));
        $this->assertFalse($this->adapter->supports(NotificationChannel::inApp()));
        $this->assertFalse($this->adapter->supports(NotificationChannel::email()));
    }

    public function testItHandsTheDeliveryToTheBusInsteadOfWaitingForIt(): void
    {
        $userId = Uuid::generate();

        $this->adapter->send(
            Recipient::userId($userId->value()),
            NotificationMessage::create('Zadanie czeka', 'Family Plan', ['url' => '/tasks']),
            NotificationChannel::push()
        );

        $this->assertCount(1, $this->bus->dispatched);

        $command = $this->bus->dispatched[0];
        $this->assertInstanceOf(DeliverPushCommand::class, $command);
        $this->assertSame($userId->value(), $command->userId);
        $this->assertSame('Zadanie czeka', $command->message);
        $this->assertSame('Family Plan', $command->subject);
        $this->assertSame(['url' => '/tasks'], $command->additionalParameters);
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
}

final class CollectingMessageBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message, $stamps);
    }
}
