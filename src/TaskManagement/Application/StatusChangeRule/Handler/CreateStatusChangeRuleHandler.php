<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Command\CreateStatusChangeRuleCommand;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionConfig;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateStatusChangeRuleHandler
{
    public function __construct(
        private StatusChangeRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(CreateStatusChangeRuleCommand $command): void
    {
        $conditionType = StatusChangeConditionType::from($command->conditionType);
        
        $config = match ($conditionType) {
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY => 
                StatusChangeConditionConfig::otherTaskCompletedToday(
                    Uuid::fromString($command->conditionConfig['requiredTaskTemplateId'])
                ),
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN => 
                StatusChangeConditionConfig::lastExecutionCooldown(
                    $command->conditionConfig['cooldownDays']
                ),
        };

        $rule = StatusChangeRule::create(
            Uuid::fromString($command->id),
            Uuid::fromString($command->teamId),
            Uuid::fromString($command->taskTemplateId),
            $command->name,
            $command->description,
            $config
        );

        $this->repository->save($rule);
    }
}
