<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Service;

use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\ValueObject\ExecutionStatus;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use DateTimeImmutable;

final readonly class TaskTypePool
{
    public function __construct(
        private TaskExecutionRepositoryInterface $executionRepository
    ) {
    }

    public function remaining(TaskTemplate $template): ?int
    {
        return $template->executionLimit()->remaining($this->takenInCurrentWindow($template));
    }

    public function hasRoomForAnother(TaskTemplate $template): bool
    {
        return !$this->hasUnfinishedAssignment($template)
            && $template->executionLimit()->allowsAnother($this->takenInCurrentWindow($template));
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

    public function takenInCurrentWindow(TaskTemplate $template): int
    {
        $executions = $this->executionRepository->findByRoutineTask($template->id());
        $windowStart = $template->executionLimit()->windowStart(new DateTimeImmutable());

        if ($windowStart === null) {
            return count($executions);
        }

        return count(array_filter(
            $executions,
            static fn ($execution) => $execution->createdAt() >= $windowStart
        ));
    }
}
