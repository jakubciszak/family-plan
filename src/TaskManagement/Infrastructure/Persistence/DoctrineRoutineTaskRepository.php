<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\RoutineTask;
use App\TaskManagement\Domain\Repository\RoutineTaskRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRoutineTaskRepository implements RoutineTaskRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(RoutineTask $routineTask): void
    {
        $this->entityManager->persist($routineTask);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?RoutineTask
    {
        return $this->entityManager->find(RoutineTask::class, $id->value());
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(RoutineTask::class)->findAll();
    }

    public function findActive(): array
    {
        return $this->entityManager->getRepository(RoutineTask::class)
            ->createQueryBuilder('rt')
            ->where('rt.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findByAssignedUser(Uuid $userId): array
    {
        return $this->entityManager->getRepository(RoutineTask::class)
            ->createQueryBuilder('rt')
            ->where('rt.assignedUserId = :userId')
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->getResult();
    }

    public function delete(RoutineTask $routineTask): void
    {
        $this->entityManager->remove($routineTask);
        $this->entityManager->flush();
    }
}
