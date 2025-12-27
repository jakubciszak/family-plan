<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Command\UpdateBonusPointsRuleCommand;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Points;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use InvalidArgumentException;

#[AsMessageHandler]
final readonly class UpdateBonusPointsRuleHandler
{
    public function __construct(
        private BonusPointsRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(UpdateBonusPointsRuleCommand $command): void
    {
        $rule = $this->repository->findById(Uuid::fromString($command->id));

        if ($rule === null) {
            throw new InvalidArgumentException("Rule not found: {$command->id}");
        }

        $rule->update(
            $command->name,
            $command->description,
            Points::fromInt($command->bonusPoints)
        );

        $this->repository->save($rule);
    }
}
