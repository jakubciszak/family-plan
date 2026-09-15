<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\SavingsGoal;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSavingsGoalRepository implements SavingsGoalRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?SavingsGoal
    {
        return $this->entityManager->getRepository(SavingsGoal::class)->find($id->value());
    }

    public function ofUser(Uuid $userId, bool $openOnly = false): array
    {
        $criteria = ['userId' => $userId];

        if ($openOnly) {
            $criteria['closedAt'] = null;
        }

        return $this->entityManager->getRepository(SavingsGoal::class)->findBy($criteria, ['createdAt' => 'ASC']);
    }

    public function save(SavingsGoal $goal): void
    {
        $this->entityManager->persist($goal);
        $this->entityManager->flush();
    }
}
