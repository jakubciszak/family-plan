<?php

declare(strict_types=1);

namespace App\ActionPlanning\Infrastructure\Persistence;

use App\ActionPlanning\Domain\Entity\ActionPlan;
use App\ActionPlanning\Domain\Repository\ActionPlanRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineActionPlanRepository implements ActionPlanRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function visibleTo(Uuid $userId, array $teamIds): array
    {
        $query = $this->entityManager->createQueryBuilder()->select('plan')->from(ActionPlan::class, 'plan')
            ->where('(plan.userId = :user AND plan.teamId IS NULL)')->setParameter('user', $userId->value());
        if ($teamIds !== []) {
            $query->orWhere('plan.teamId IN (:teams)')->setParameter('teams', $teamIds);
        }

        return $query->orderBy('plan.name', 'ASC')->addOrderBy('plan.id', 'ASC')->getQuery()->getResult();
    }

    public function find(Uuid $id): ?ActionPlan
    {
        return $this->entityManager->find(ActionPlan::class, $id);
    }

    public function isLinked(Uuid $id): bool
    {
        return (bool) $this->entityManager->getConnection()->fetchOne(
            'SELECT 1 FROM task_templates WHERE action_plan_id = ? LIMIT 1', [$id->value()]
        );
    }

    public function save(ActionPlan $plan): void
    {
        $this->entityManager->persist($plan);
        $this->entityManager->flush();
    }

    public function remove(ActionPlan $plan): void
    {
        $this->entityManager->remove($plan);
        $this->entityManager->flush();
    }
}
