<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\AllowanceRule;
use App\Allowance\Domain\Repository\AllowanceRuleRepositoryInterface;
use App\PointsManagement\Domain\ValueObject\AccountKind as PointsAccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAllowanceRuleRepository implements AllowanceRuleRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $teamId, PointsAccountKind $pointsAccount): ?AllowanceRule
    {
        return $this->entityManager->getRepository(AllowanceRule::class)->findOneBy([
            'teamId' => $teamId,
            'pointsAccount' => $pointsAccount,
        ]);
    }

    public function ofTeam(Uuid $teamId): array
    {
        return $this->entityManager->getRepository(AllowanceRule::class)->findBy(['teamId' => $teamId]);
    }

    public function save(AllowanceRule $rule): void
    {
        $this->entityManager->persist($rule);
        $this->entityManager->flush();
    }

    public function remove(AllowanceRule $rule): void
    {
        $this->entityManager->remove($rule);
        $this->entityManager->flush();
    }
}
