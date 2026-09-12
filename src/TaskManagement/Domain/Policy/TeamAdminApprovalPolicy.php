<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Policy;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;

final readonly class TeamAdminApprovalPolicy implements TaskApprovalPolicyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TeamMembershipRepositoryInterface $memberships
    ) {
    }

    public function canApprove(Uuid $userId, ?Uuid $teamId = null): bool
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($teamId === null) {
            return false;
        }

        return $this->memberships->isAdmin($userId, $teamId);
    }
}
