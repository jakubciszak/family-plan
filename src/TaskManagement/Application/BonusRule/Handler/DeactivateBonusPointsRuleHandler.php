<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Command\DeactivateBonusPointsRuleCommand;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use InvalidArgumentException;

#[AsMessageHandler]
final readonly class DeactivateBonusPointsRuleHandler
{
    public function __construct(
        private BonusPointsRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(DeactivateBonusPointsRuleCommand $command): void
    {
        $rule = $this->repository->findById(Uuid::fromString($command->id));

        if ($rule === null) {
            throw new InvalidArgumentException("Rule not found: {$command->id}");
        }

        $rule->deactivate();
        $this->repository->save($rule);
    }
}
