<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Handler;

use App\TaskManagement\Application\BonusRule\Query\GetAllBonusPointsRulesQuery;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAllBonusPointsRulesQueryHandler
{
    public function __construct(
        private BonusPointsRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(GetAllBonusPointsRulesQuery $query): array
    {
        if ($query->activeOnly) {
            return $this->repository->findActive();
        }

        return $this->repository->findAll();
    }
}
