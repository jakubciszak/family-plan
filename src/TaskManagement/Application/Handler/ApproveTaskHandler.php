<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Exception\UnauthorizedTaskActionException;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\Policy\TaskApprovalPolicyInterface;
use App\TaskManagement\Domain\Service\BonusSettlementInterface;
use App\TaskManagement\Domain\Strategy\PointsAwardStrategyInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApproveTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TaskApprovalPolicyInterface $approvalPolicy,
        private PointsAwardStrategyInterface $pointsAwardStrategy,
        private BonusSettlementInterface $bonusSettlement
    ) {
    }

    public function __invoke(ApproveTaskCommand $command): void
    {
        $adminId = Uuid::fromString($command->adminId);

        $task = $this->taskRepository->findById(Uuid::fromString($command->taskId));

        if (!$task) {
            throw new \RuntimeException('Task not found');
        }

        if (!$this->approvalPolicy->canApprove($adminId, $task->teamId())) {
            throw new UnauthorizedTaskActionException('Only team admins can approve tasks');
        }

        // Get the user who completed the task before approving
        $completedByUserId = $task->assignedUserId();
        if ($completedByUserId === null) {
            throw new \DomainException('Cannot approve task that was not assigned to anyone');
        }

        $task->approve($adminId);
        $this->taskRepository->save($task);
        
        // Award points to the user who completed the task
        $this->pointsAwardStrategy->awardPoints($task, $completedByUserId);
        $this->bonusSettlement->settleFor($completedByUserId);
    }
}
