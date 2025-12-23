<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;

/**
 * Strategy interface for awarding points when task executions are approved.
 * Following the Open/Closed Principle - new point awarding strategies can be added
 * without modifying existing code.
 */
interface ExecutionPointsAwardStrategyInterface
{
    /**
     * Awards points to a user for completing a task execution.
     *
     * @param TaskExecution $execution The task execution that was approved
     * @param Uuid $userId The ID of the user who should receive points
     * @return void
     */
    public function awardPoints(TaskExecution $execution, Uuid $userId): void;
}
