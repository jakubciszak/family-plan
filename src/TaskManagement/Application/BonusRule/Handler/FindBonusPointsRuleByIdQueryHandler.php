<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Query\FindBonusPointsRuleByIdQuery;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FindBonusPointsRuleByIdQueryHandler
{
    public function __construct(
        private BonusPointsRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(FindBonusPointsRuleByIdQuery $query): mixed
    {
        return $this->repository->findById(Uuid::fromString($query->id));
    }
}
