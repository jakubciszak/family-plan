<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Command\CreateBonusPointsRuleCommand;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\ValueObject\RuleType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateBonusPointsRuleHandler
{
    public function __construct(
        private BonusPointsRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(CreateBonusPointsRuleCommand $command): void
    {
        $config = RuleConfig::fromInput(RuleType::from($command->ruleType), $command->ruleConfig);

        $rule = BonusPointsRule::create(
            Uuid::fromString($command->id),
            Uuid::fromString($command->teamId),
            $command->name,
            $command->description,
            Points::fromInt($command->bonusPoints),
            $config
        );

        $this->repository->save($rule);
    }
}
