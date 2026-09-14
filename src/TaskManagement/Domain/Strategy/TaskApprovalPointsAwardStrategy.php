<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;

/**
 * Books the points of an approved task on the user's task account.
 */
final readonly class TaskApprovalPointsAwardStrategy implements PointsAwardStrategyInterface
{
    public function __construct(private PointsLedger $ledger)
    {
    }

    public function awardPoints(Task $task, Uuid $userId): void
    {
        $this->ledger->post(
            $userId,
            AccountKind::TASKS,
            $task->points()->value(),
            EntrySource::TASK_EXECUTION,
            sprintf('Task approved: %s', $task->name()->value()),
            $task->id(),
            'task'
        );
    }
}
