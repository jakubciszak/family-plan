<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;

/**
 * Strategy that awards task points to the user who completed the task.
 * Implements the Open/Closed Principle - new strategies can be added without modification.
 */
final readonly class TaskApprovalPointsAwardStrategy implements PointsAwardStrategyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function awardPoints(Task $task, Uuid $userId): void
    {
        $user = $this->userRepository->findById($userId);
        
        if ($user === null) {
            throw new \DomainException(
                sprintf('User with ID %s not found', $userId->value())
            );
        }
        
        $user->addPoints($task->points()->value());
        $this->userRepository->save($user);
    }
}
