<?php

declare(strict_types=1);

namespace App\ActionPlanning\Application\Handler;

use App\ActionPlanning\Application\Command\DeleteActionPlanCommand;
use App\ActionPlanning\Application\Service\ActionPlanAccess;
use App\ActionPlanning\Domain\Repository\ActionPlanRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteActionPlanHandler
{
    public function __construct(private ActionPlanRepositoryInterface $plans, private ActionPlanAccess $access)
    {
    }

    public function __invoke(DeleteActionPlanCommand $command): void
    {
        $plan = $this->access->forManagement($command->id, $command->userId);
        if ($this->plans->isLinked($plan->id())) {
            throw new \DomainException('actionPlans.linkedPlan');
        }
        $this->plans->remove($plan);
    }
}
