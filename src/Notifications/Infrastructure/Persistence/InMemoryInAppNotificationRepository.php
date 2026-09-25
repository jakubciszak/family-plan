<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\SystemClock;
use DateTimeImmutable;

final class InMemoryInAppNotificationRepository implements InAppNotificationRepositoryInterface
{
    /** @var array<string, InAppNotification> */
    private array $notifications = [];

    private ClockInterface $clock;

    public function __construct(?ClockInterface $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    public function save(InAppNotification $notification): void
    {
        $this->notifications[$notification->id()->value()] = $notification;
    }

    public function findById(Uuid $id): ?InAppNotification
    {
        return $this->notifications[$id->value()] ?? null;
    }

    public function unreadFor(Uuid $userId, int $limit): array
    {
        $now = $this->clock->now();
        $unread = array_filter(
            $this->ofUser($userId),
            static fn (InAppNotification $notification) => $notification->isActive($now)
        );

        usort(
            $unread,
            static fn (InAppNotification $a, InAppNotification $b) => $b->createdAt() <=> $a->createdAt()
        );

        return array_slice($unread, 0, $limit);
    }

    public function recentFor(Uuid $userId, int $limit): array
    {
        $all = $this->ofUser($userId);

        usort(
            $all,
            static fn (InAppNotification $a, InAppNotification $b) => $b->createdAt() <=> $a->createdAt()
        );

        return array_slice($all, 0, $limit);
    }

    public function countUnreadFor(Uuid $userId): int
    {
        return count($this->unreadFor($userId, PHP_INT_MAX));
    }

    public function markAllAsRead(Uuid $userId, DateTimeImmutable $readAt): int
    {
        $marked = 0;

        foreach ($this->ofUser($userId) as $notification) {
            if ($notification->isRead()) {
                continue;
            }

            $notification->markAsRead($readAt);
            $marked++;
        }

        return $marked;
    }

    public function resolveTopic(string $topic, DateTimeImmutable $resolvedAt, ?string $event = null, ?Uuid $userId = null): int
    {
        $resolved = 0;

        foreach ($this->notifications as $notification) {
            if ($notification->topic() !== $topic || $notification->isResolved()) {
                continue;
            }

            if (($event !== null && $notification->event() !== $event) || ($userId !== null && !$notification->belongsTo($userId))) {
                continue;
            }

            $notification->resolve($resolvedAt);
            $resolved++;
        }

        return $resolved;
    }

    public function clear(): void
    {
        $this->notifications = [];
    }

    /**
     * @return InAppNotification[]
     */
    private function ofUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->notifications,
            static fn (InAppNotification $notification) => $notification->belongsTo($userId)
        ));
    }
}
