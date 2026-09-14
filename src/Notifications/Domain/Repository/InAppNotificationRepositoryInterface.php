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
     * @return InAppNotification[]
     */
    public function unreadFor(Uuid $userId, int $limit): array;

    /**
     * @return InAppNotification[]
     */
    public function recentFor(Uuid $userId, int $limit): array;

    public function countUnreadFor(Uuid $userId): int;

    public function markAllAsRead(Uuid $userId, DateTimeImmutable $readAt): int;
}
