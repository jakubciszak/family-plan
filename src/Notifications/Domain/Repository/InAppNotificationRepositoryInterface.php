<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Repository;

use App\Notifications\Domain\Entity\InAppNotification;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface InAppNotificationRepositoryInterface
{
    public function save(InAppNotification $notification): void;

    public function findById(Uuid $id): ?InAppNotification;

    /**
     * Unread notifications that still say something true, newest first: resolved and expired ones are left out.
     *
     * @return InAppNotification[]
     */
    public function unreadFor(Uuid $userId, int $limit): array;

    /**
     * @return InAppNotification[]
     */
    public function recentFor(Uuid $userId, int $limit): array;

    public function countUnreadFor(Uuid $userId): int;

    public function markAllAsRead(Uuid $userId, DateTimeImmutable $readAt): int;

    /**
     * Resolves every open notification on the topic, optionally only of one event and one recipient.
     *
     * @return int how many were resolved
     */
    public function resolveTopic(string $topic, DateTimeImmutable $resolvedAt, ?string $event = null, ?Uuid $userId = null): int;
}
