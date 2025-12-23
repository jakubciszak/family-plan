<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;

/**
 * Strategy that awards execution points to the user who completed the task execution.
 * Implements the Open/Closed Principle - new strategies can be added without modification.
 */
final readonly class ExecutionApprovalPointsAwardStrategy implements ExecutionPointsAwardStrategyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function awardPoints(TaskExecution $execution, Uuid $userId): void
    {
        $user = $this->userRepository->findById($userId);
        
        if ($user === null) {
            throw new \DomainException(
                sprintf('User with ID %s not found', $userId->value())
            );
        }
        
        $points = $execution->points();
        if ($points === null) {
            throw new \DomainException('Task execution has no points assigned');
        }
        
        $user->addPoints($points->value());
        $this->userRepository->save($user);
    }
}
