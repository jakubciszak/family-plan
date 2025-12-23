<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;

/**
 * Strategy interface for awarding points when tasks are approved.
 * Following the Open/Closed Principle - new point awarding strategies can be added
 * without modifying existing code.
 */
interface PointsAwardStrategyInterface
{
    /**
     * Awards points to a user for completing a task.
     *
     * @param Task $task The task that was approved
     * @param Uuid $userId The ID of the user who should receive points
     * @return void
     */
    public function awardPoints(Task $task, Uuid $userId): void;
}
