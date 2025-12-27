<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBonusPointsRuleRepository implements BonusPointsRuleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(BonusPointsRule $rule): void
    {
        $this->entityManager->persist($rule);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?BonusPointsRule
    {
        return $this->entityManager->find(BonusPointsRule::class, $id->value());
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(BonusPointsRule::class)
            ->findAll();
    }

    public function findActive(): array
    {
        return $this->entityManager->getRepository(BonusPointsRule::class)
            ->createQueryBuilder('br')
            ->where('br.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('br.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function delete(BonusPointsRule $rule): void
    {
        $this->entityManager->remove($rule);
        $this->entityManager->flush();
    }
}
