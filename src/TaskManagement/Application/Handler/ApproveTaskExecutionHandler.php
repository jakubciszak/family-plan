<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskExecutionCommand;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Policy\TaskApprovalPolicyInterface;
use App\TaskManagement\Domain\Strategy\ExecutionPointsAwardStrategyInterface;

final readonly class ApproveTaskExecutionHandler
{
    public function __construct(
        private TaskExecutionRepositoryInterface $taskExecutionRepository,
        private TaskApprovalPolicyInterface $approvalPolicy,
        private ExecutionPointsAwardStrategyInterface $pointsAwardStrategy
    ) {
    }

    public function __invoke(ApproveTaskExecutionCommand $command): void
    {
        $adminId = Uuid::fromString($command->adminId);
        
        // Check if user has permission to approve task executions
        if (!$this->approvalPolicy->canApprove($adminId)) {
            throw new \DomainException('Only administrators can approve task executions');
        }
        
        $execution = $this->taskExecutionRepository->findById(
            Uuid::fromString($command->executionId)
        );

        if ($execution === null) {
            throw new \DomainException(
                sprintf('Task execution with ID %s not found', $command->executionId)
            );
        }

        // Get the user who completed the execution before approving
        $completedByUserId = $execution->assignedUserId();
        if ($completedByUserId === null) {
            throw new \DomainException('Cannot approve task execution that was not assigned to anyone');
        }

        $execution->approve($adminId);
        $this->taskExecutionRepository->save($execution);
        
        // Award points to the user who completed the execution
        $this->pointsAwardStrategy->awardPoints($execution, $completedByUserId);
    }
}
