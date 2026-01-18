<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTaskRepository implements TaskRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Task $task): void
    {
        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Task
    {
        return $this->entityManager->find(Task::class, $id->value());
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(Task::class)->findAll();
    }

    public function findByAssignedUser(Uuid $userId): array
    {
        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.assignedUserId = :userId')
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->getResult();
    }

    public function findPending(): array
    {
        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', TaskStatus::PENDING->value)
            ->getQuery()
            ->getResult();
    }

    public function findCompleted(): array
    {
        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', TaskStatus::COMPLETED->value)
            ->getQuery()
            ->getResult();
    }

    public function findTemplates(): array
    {
        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.isTemplate = :isTemplate')
            ->setParameter('isTemplate', true)
            ->getQuery()
            ->getResult();
    }

    public function findActiveTemplates(): array
    {
        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.isTemplate = :isTemplate')
            ->andWhere('t.isActive = :isActive')
            ->setParameter('isTemplate', true)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();
    }

    public function findByTeamId(Uuid $teamId): array
    {
        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.teamId = :teamId')
            ->setParameter('teamId', $teamId->value())
            ->getQuery()
            ->getResult();
    }

    public function findByTeamIds(array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $teamIdValues = array_map(fn(Uuid $uuid) => $uuid->value(), $teamIds);

        return $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.teamId IN (:teamIds)')
            ->setParameter('teamIds', $teamIdValues)
            ->getQuery()
            ->getResult();
    }

    public function delete(Task $task): void
    {
        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }
}
