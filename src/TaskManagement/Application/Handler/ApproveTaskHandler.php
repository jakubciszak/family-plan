<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\Policy\TaskApprovalPolicyInterface;
use App\TaskManagement\Domain\Strategy\PointsAwardStrategyInterface;

final readonly class ApproveTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TaskApprovalPolicyInterface $approvalPolicy,
        private PointsAwardStrategyInterface $pointsAwardStrategy
    ) {
    }

    public function __invoke(ApproveTaskCommand $command): void
    {
        $adminId = Uuid::fromString($command->adminId);
        
        // Check if user has permission to approve tasks
        if (!$this->approvalPolicy->canApprove($adminId)) {
            throw new \DomainException('Only administrators can approve tasks');
        }
        
        $task = $this->taskRepository->findById(Uuid::fromString($command->taskId));

        if (!$task) {
            throw new \RuntimeException('Task not found');
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
    }
}
