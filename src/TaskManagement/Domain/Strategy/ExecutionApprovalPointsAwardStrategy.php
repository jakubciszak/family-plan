<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Strategy;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;

/**
 * Books the points of an approved execution on the user's task account.
 */
final readonly class ExecutionApprovalPointsAwardStrategy implements ExecutionPointsAwardStrategyInterface
{
    public function __construct(private PointsLedger $ledger, private \App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface $templates)
    {
    }

    public function awardPoints(TaskExecution $execution, Uuid $userId): void
    {
        $points = $execution->points();

        if ($points === null) {
            throw new \DomainException('Task execution has no points assigned');
        }

        $template = $execution->taskTemplateId() === null ? null : $this->templates->findById($execution->taskTemplateId());
        if ($template?->teamId() === null) {
            throw new \DomainException('An execution needs a household before points can be booked');
        }

        $name = $execution->name() ? $execution->name()->value() : 'Task execution';

        $this->ledger->post(
            $userId,
            AccountKind::TASKS,
            $points->value(),
            EntrySource::TASK_EXECUTION,
            sprintf('Task execution approved: %s', $name),
            $execution->id(),
            'execution', teamId: $template->teamId()
        );
    }
}
