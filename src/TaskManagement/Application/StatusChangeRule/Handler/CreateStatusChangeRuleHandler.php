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
        $config = StatusChangeConditionConfig::fromInput(
            StatusChangeConditionType::from($command->conditionType),
            $command->conditionConfig
        );

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
