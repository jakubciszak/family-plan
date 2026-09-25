<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineInAppNotificationRepository implements InAppNotificationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock
    ) {
    }

    public function save(InAppNotification $notification): void
    {
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?InAppNotification
    {
        return $this->entityManager->getRepository(InAppNotification::class)->find($id);
    }

    public function unreadFor(Uuid $userId, int $limit): array
    {
        // Newest first: after a long break the latest news matters, not the oldest backlog.
        return $this->unread($userId)
            ->select('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function recentFor(Uuid $userId, int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(InAppNotification::class, 'n')
            ->where('n.userId = :userId')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadFor(Uuid $userId): int
    {
        return (int) $this->unread($userId)
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllAsRead(Uuid $userId, DateTimeImmutable $readAt): int
    {
        return $this->entityManager->createQueryBuilder()
            ->update(InAppNotification::class, 'n')
            ->set('n.readAt', ':readAt')
            ->where('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('readAt', $readAt, 'datetime_immutable')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    public function resolveTopic(string $topic, DateTimeImmutable $resolvedAt, ?string $event = null, ?Uuid $userId = null): array
    {
        $open = $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(InAppNotification::class, 'n')
            ->where('n.topic = :topic')
            ->andWhere('n.resolvedAt IS NULL')
            ->setParameter('topic', $topic);

        if ($event !== null) {
            $open->andWhere('n.event = :event')->setParameter('event', $event);
        }

        if ($userId !== null) {
            $open->andWhere('n.userId = :userId')->setParameter('userId', $userId);
        }

        $recipients = [];

        foreach ($open->getQuery()->getResult() as $notification) {
            /** @var InAppNotification $notification */
            $notification->resolve($resolvedAt);
            $recipients[$notification->userId()->value()] = $notification->userId();
        }

        if ($recipients !== []) {
            $this->entityManager->flush();
        }

        return array_values($recipients);
    }

    private function unread(Uuid $userId): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from(InAppNotification::class, 'n')
            ->where('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->andWhere('n.resolvedAt IS NULL')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', $this->clock->now(), 'datetime_immutable');
    }
}
