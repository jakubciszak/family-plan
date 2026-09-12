<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Service;

use App\TaskManagement\Domain\Entity\TaskTemplate;
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
        return $template->executionLimit()->allowsAnother($this->takenInCurrentWindow($template));
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
