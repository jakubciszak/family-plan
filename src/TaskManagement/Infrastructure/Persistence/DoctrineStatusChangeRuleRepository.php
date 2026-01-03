<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineStatusChangeRuleRepository implements StatusChangeRuleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(StatusChangeRule $rule): void
    {
        $this->entityManager->persist($rule);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?StatusChangeRule
    {
        return $this->entityManager->find(StatusChangeRule::class, $id);
    }

    public function findAll(bool $activeOnly = false): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
            ->from(StatusChangeRule::class, 'r');

        if ($activeOnly) {
            $qb->where('r.isActive = :active')
                ->setParameter('active', true);
        }

        $qb->orderBy('r.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findActiveByTaskTemplateId(Uuid $taskTemplateId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
            ->from(StatusChangeRule::class, 'r')
            ->where('r.taskTemplateId = :taskTemplateId')
            ->andWhere('r.isActive = :active')
            ->setParameter('taskTemplateId', $taskTemplateId)
            ->setParameter('active', true)
            ->orderBy('r.createdAt', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
