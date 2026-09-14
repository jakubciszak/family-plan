<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Infrastructure\Persistence;

use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\Repository\NotificationPolicyRepositoryInterface;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNotificationPolicyRepository implements NotificationPolicyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(NotificationPolicy $policy): void
    {
        $this->entityManager->persist($policy);
        $this->entityManager->flush();
    }

    public function findByEvent(NotificationEvent $event): ?NotificationPolicy
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(NotificationPolicy::class, 'p')
            ->where('p.event = :event')
            ->setParameter('event', $event->value())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(NotificationPolicy::class, 'p')
            ->getQuery()
            ->getResult();
    }
}
