<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(PushSubscription $subscription): void
    {
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    public function delete(PushSubscription $subscription): void
    {
        $this->entityManager->remove($subscription);
        $this->entityManager->flush();
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->entityManager->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $endpoint]);
    }

    public function findForUser(Uuid $userId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(PushSubscription::class, 's')
            ->where('s.userId = :userId')
            ->orderBy('s.createdAt', 'ASC')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function countForUser(Uuid $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(PushSubscription::class, 's')
            ->where('s.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
