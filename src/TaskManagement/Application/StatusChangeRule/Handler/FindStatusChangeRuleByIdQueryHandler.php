<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Query\FindStatusChangeRuleByIdQuery;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class FindStatusChangeRuleByIdQueryHandler
{
    public function __construct(
        private StatusChangeRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(FindStatusChangeRuleByIdQuery $query): ?StatusChangeRule
    {
        return $this->repository->findById(Uuid::fromString($query->id));
    }
}
