<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\ExecutionStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTaskExecutionRepository implements TaskExecutionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(TaskExecution $execution): void
    {
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?TaskExecution
    {
        return $this->entityManager->find(TaskExecution::class, $id->value());
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(TaskExecution::class)->findAll();
    }

    public function findByRoutineTask(Uuid $routineTaskId): array
    {
        return $this->entityManager->getRepository(TaskExecution::class)
            ->createQueryBuilder('te')
            ->where('te.routineTaskId = :routineTaskId')
            ->setParameter('routineTaskId', $routineTaskId->value())
            ->orderBy('te.scheduledFor', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByAssignedUser(Uuid $userId): array
    {
        return $this->entityManager->getRepository(TaskExecution::class)
            ->createQueryBuilder('te')
            ->where('te.assignedUserId = :userId')
            ->setParameter('userId', $userId->value())
            ->orderBy('te.scheduledFor', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPending(): array
    {
        return $this->entityManager->getRepository(TaskExecution::class)
            ->createQueryBuilder('te')
            ->where('te.status = :status')
            ->setParameter('status', ExecutionStatus::PENDING->value)
            ->orderBy('te.scheduledFor', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCompleted(): array
    {
        return $this->entityManager->getRepository(TaskExecution::class)
            ->createQueryBuilder('te')
            ->where('te.status = :status')
            ->setParameter('status', ExecutionStatus::COMPLETED->value)
            ->orderBy('te.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findScheduledForDate(DateTimeImmutable $date): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->entityManager->getRepository(TaskExecution::class)
            ->createQueryBuilder('te')
            ->where('te.scheduledFor >= :start')
            ->andWhere('te.scheduledFor <= :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('te.scheduledFor', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function delete(TaskExecution $execution): void
    {
        $this->entityManager->remove($execution);
        $this->entityManager->flush();
    }
}
