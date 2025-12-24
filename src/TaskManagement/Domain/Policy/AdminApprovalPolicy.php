<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Policy;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;

/**
 * Policy that allows only administrators to approve tasks.
 * Implements the Open/Closed Principle - new policies can be added without modification.
 */
final readonly class AdminApprovalPolicy implements TaskApprovalPolicyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function canApprove(Uuid $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        
        if ($user === null) {
            return false;
        }
        
        return $user->isAdmin();
    }
}
