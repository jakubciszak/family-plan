<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Policy;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Policy interface for task approval authorization.
 * Following the Open/Closed Principle - new approval policies can be added
 * without modifying existing code.
 */
interface TaskApprovalPolicyInterface
{
    /**
     * Determines if a user can approve a task.
     *
     * @param Uuid $userId The ID of the user attempting to approve
     * @return bool True if the user is authorized to approve tasks
     */
    public function canApprove(Uuid $userId): bool;
}
