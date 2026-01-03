<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Query\GetAllStatusChangeRulesQuery;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetAllStatusChangeRulesQueryHandler
{
    public function __construct(
        private StatusChangeRuleRepositoryInterface $repository
    ) {
    }

    /**
     * @return StatusChangeRule[]
     */
    public function __invoke(GetAllStatusChangeRulesQuery $query): array
    {
        if ($query->taskTemplateId !== null) {
            return $this->repository->findActiveByTaskTemplateId(
                Uuid::fromString($query->taskTemplateId)
            );
        }

        return $this->repository->findAll($query->activeOnly);
    }
}
