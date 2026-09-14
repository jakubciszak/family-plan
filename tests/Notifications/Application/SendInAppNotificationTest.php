<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Infrastructure\Adapter\InAppNotificationAdapter;
use App\Notifications\Infrastructure\Persistence\InMemoryInAppNotificationRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use PHPUnit\Framework\TestCase;

class SendInAppNotificationTest extends TestCase
{
    private InMemoryInAppNotificationRepository $notifications;

    private NotificationFacade $facade;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryInAppNotificationRepository();
        $adapter = new InAppNotificationAdapter($this->notifications, new FixedClock());
        $this->facade = new NotificationFacade([$adapter]);
    }

    public function testTheFacadeDeliversToTheUsersMailbox(): void
    {
        $userId = Uuid::generate();

        $this->facade->sendInApp($userId->value(), 'Your task has been approved', 'Task approved', ['points' => 5]);

        $stored = $this->notifications->unreadFor($userId, 10);

        $this->assertCount(1, $stored);
        $this->assertSame('Your task has been approved', $stored[0]->message());
        $this->assertSame('Task approved', $stored[0]->subject());
        $this->assertSame(['points' => 5], $stored[0]->parameters());
    }

    public function testTheChannelNameRoutesThroughTheGenericSend(): void
    {
        $userId = Uuid::generate();

        $this->facade->send('in_app', $userId->value(), 'Sent by channel name');

        $this->assertCount(1, $this->notifications->unreadFor($userId, 10));
    }

    public function testItRefusesARecipientThatIsNotAUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->facade->sendInApp('not-a-uuid', 'Message');
    }
}
