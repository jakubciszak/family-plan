<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Service;

use App\Shared\Domain\Clock\ClockInterface;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\ValueObject\ExecutionStatus;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use DateTimeImmutable;

final readonly class TaskTypePool
{
    public function __construct(
        private TaskExecutionRepositoryInterface $executionRepository,
        private ClockInterface $clock
    ) {
    }

    public function remaining(TaskTemplate $template): ?int
    {
        return $template->executionLimit()->remaining($this->takenInWindowOf($template, $this->clock->now()));
    }

    public function hasRoomForAnother(TaskTemplate $template, ?DateTimeImmutable $day = null): bool
    {
        return !$this->hasUnfinishedAssignment($template)
            && $template->executionLimit()->allowsAnother(
                $this->takenInWindowOf($template, $day ?? $this->clock->now())
            );
    }

    private function hasUnfinishedAssignment(TaskTemplate $template): bool
    {
        foreach ($this->executionRepository->findByRoutineTask($template->id()) as $execution) {
            if ($execution->assignedUserId() !== null && in_array($execution->status(), [
                ExecutionStatus::NEW,
                ExecutionStatus::PENDING,
                ExecutionStatus::REJECTED,
            ], true)) {
                return true;
            }
        }

        return false;
    }

    public function takenInWindowOf(TaskTemplate $template, DateTimeImmutable $day): int
    {
        $executions = $this->executionRepository->findByRoutineTask($template->id());
        $limit = $template->executionLimit();
        $window = $limit->windowStart($day);

        if ($window === null) {
            return count($executions);
        }

        return count(array_filter(
            $executions,
            static fn (TaskExecution $execution) => $limit->windowStart($execution->earnedOn()) == $window
        ));
    }
}
