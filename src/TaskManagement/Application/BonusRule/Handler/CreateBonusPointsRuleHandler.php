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
        $ruleType = RuleType::from($command->ruleType);
        
        $config = match ($ruleType) {
            RuleType::CONSECUTIVE_DAYS => RuleConfig::consecutiveDays(
                Uuid::fromString($command->ruleConfig['taskTemplateId']),
                $command->ruleConfig['requiredDays']
            ),
            RuleType::MONTHLY_TASK_COUNT => RuleConfig::monthlyTaskCount(
                $command->ruleConfig['requiredCount']
            ),
        };

        $rule = BonusPointsRule::create(
            Uuid::fromString($command->id),
            $command->name,
            $command->description,
            Points::fromInt($command->bonusPoints),
            $config
        );

        $this->repository->save($rule);
    }
}
