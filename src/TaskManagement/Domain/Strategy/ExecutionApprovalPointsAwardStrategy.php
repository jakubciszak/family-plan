<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\Repository\UserWalletRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;

/**
 * Strategy that awards execution points to the user's wallet when execution is approved.
 * Implements the Open/Closed Principle - new strategies can be added without modification.
 */
final readonly class ExecutionApprovalPointsAwardStrategy implements ExecutionPointsAwardStrategyInterface
{
    public function __construct(
        private UserWalletRepositoryInterface $walletRepository
    ) {
    }

    public function awardPoints(TaskExecution $execution, Uuid $userId): void
    {
        $points = $execution->points();
        if ($points === null) {
            throw new \DomainException('Task execution has no points assigned');
        }
        
        $wallet = $this->walletRepository->findByUserId($userId);
        
        if ($wallet === null) {
            // Create wallet if it doesn't exist
            $wallet = UserWallet::create(Uuid::generate(), $userId);
        }
        
        $executionName = $execution->name() ? $execution->name()->value() : 'Task execution';
        $wallet->awardPoints(
            $points->value(),
            sprintf('Task execution approved: %s', $executionName)
        );
        
        $this->walletRepository->save($wallet);
    }
}
