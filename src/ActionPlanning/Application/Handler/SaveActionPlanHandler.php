<?php

declare(strict_types=1);

namespace App\ActionPlanning\Application\Handler;

use App\ActionPlanning\Application\Command\SaveActionPlanCommand;
use App\ActionPlanning\Domain\Entity\ActionPlan;
use App\ActionPlanning\Application\Service\ActionPlanAccess;
use App\ActionPlanning\Domain\Repository\ActionPlanRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SaveActionPlanHandler
{
    public function __construct(private ActionPlanRepositoryInterface $plans, private ActionPlanAccess $access)
    {
    }

    public function __invoke(SaveActionPlanCommand $command): array
    {
        $this->access->assertMember($command->userId, $command->teamId);
        if ($command->id === null) {
            $plan = ActionPlan::create(Uuid::generate(), $command->userId, $command->name, $command->steps, $command->estimatedMinutes, $command->reminderMinutes);
        } else {
            $plan = $this->access->forManagement($command->id, $command->userId);
            if ($plan->teamId()?->value() !== $command->teamId?->value()) {
                if (!$plan->userId()->equals($command->userId)) {
                    throw new \DomainException('actionPlans.onlyAuthorChangesScope');
                }
                if ($this->plans->isLinked($plan->id())) {
                    throw new \DomainException('actionPlans.linkedPlan');
                }
            }
            $plan->revise($command->name, $command->steps, $command->estimatedMinutes, $command->reminderMinutes);
        }
        $plan->shareWith($command->teamId);
        if ($command->reminderSound !== null) {
            $plan->changeReminderSound($command->reminderSound);
        }
        $this->plans->save($plan);

        return [...$plan->describe(), 'canManage' => true, 'canChangeScope' => $plan->userId()->equals($command->userId)];
    }
}
