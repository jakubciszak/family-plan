<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineInAppNotificationRepository implements InAppNotificationRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
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
        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(InAppNotification::class, 'n')
            ->where('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->orderBy('n.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('userId', $userId)
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
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(InAppNotification::class, 'n')
            ->where('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllAsRead(Uuid $userId, DateTimeImmutable $readAt): int
    {
        $marked = 0;

        foreach ($this->unreadFor($userId, PHP_INT_MAX) as $notification) {
            $notification->markAsRead($readAt);
            $this->entityManager->persist($notification);
            $marked++;
        }

        $this->entityManager->flush();

        return $marked;
    }
}
