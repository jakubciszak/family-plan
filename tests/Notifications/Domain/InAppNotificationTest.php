<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Domain;

use App\Notifications\Domain\Entity\InAppNotification;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class InAppNotificationTest extends TestCase
{
    public function testItStartsUnread(): void
    {
        $notification = $this->notificationFor(Uuid::generate());

        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->readAt());
    }

    public function testItKeepsWhatItWasRaisedWith(): void
    {
        $userId = Uuid::generate();
        $notification = InAppNotification::raise(
            Uuid::generate(),
            $userId,
            'Your task has been approved',
            'Task approved',
            ['taskId' => 'abc', 'points' => 10],
            new DateTimeImmutable('2026-09-14 10:00:00')
        );

        $this->assertSame('Your task has been approved', $notification->message());
        $this->assertSame('Task approved', $notification->subject());
        $this->assertSame(['taskId' => 'abc', 'points' => 10], $notification->parameters());
        $this->assertTrue($notification->userId()->equals($userId));
        $this->assertSame('2026-09-14 10:00:00', $notification->createdAt()->format('Y-m-d H:i:s'));
    }

    public function testReadingItStampsTheTime(): void
    {
        $notification = $this->notificationFor(Uuid::generate());
        $readAt = new DateTimeImmutable('2026-09-14 12:00:00');

        $notification->markAsRead($readAt);

        $this->assertTrue($notification->isRead());
        $this->assertSame($readAt->format(DATE_ATOM), $notification->readAt()->format(DATE_ATOM));
    }

    public function testReadingItTwiceKeepsTheFirstTime(): void
    {
        $notification = $this->notificationFor(Uuid::generate());
        $first = new DateTimeImmutable('2026-09-14 12:00:00');

        $notification->markAsRead($first);
        $notification->markAsRead(new DateTimeImmutable('2026-09-14 18:00:00'));

        $this->assertSame($first->format(DATE_ATOM), $notification->readAt()->format(DATE_ATOM));
    }

    public function testItBelongsOnlyToItsOwnUser(): void
    {
        $owner = Uuid::generate();
        $notification = $this->notificationFor($owner);

        $this->assertTrue($notification->belongsTo($owner));
        $this->assertFalse($notification->belongsTo(Uuid::generate()));
    }

    public function testItRefusesAnEmptyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification message cannot be empty');

        InAppNotification::raise(
            Uuid::generate(),
            Uuid::generate(),
            '   ',
            null,
            [],
            new DateTimeImmutable()
        );
    }

    private function notificationFor(Uuid $userId): InAppNotification
    {
        return InAppNotification::raise(
            Uuid::generate(),
            $userId,
            'Something happened',
            null,
            [],
            new DateTimeImmutable('2026-09-14 10:00:00')
        );
    }
}
