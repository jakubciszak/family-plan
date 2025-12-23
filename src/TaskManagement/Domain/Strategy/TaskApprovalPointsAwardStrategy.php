<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\Repository\UserWalletRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;

/**
 * Strategy that awards task points to the user's wallet when task is approved.
 * Implements the Open/Closed Principle - new strategies can be added without modification.
 */
final readonly class TaskApprovalPointsAwardStrategy implements PointsAwardStrategyInterface
{
    public function __construct(
        private UserWalletRepositoryInterface $walletRepository
    ) {
    }

    public function awardPoints(Task $task, Uuid $userId): void
    {
        $wallet = $this->walletRepository->findByUserId($userId);
        
        if ($wallet === null) {
            // Create wallet if it doesn't exist
            $wallet = UserWallet::create(Uuid::generate(), $userId);
        }
        
        $wallet->awardPoints(
            $task->points()->value(),
            sprintf('Task approved: %s', $task->name()->value())
        );
        
        $this->walletRepository->save($wallet);
    }
}
