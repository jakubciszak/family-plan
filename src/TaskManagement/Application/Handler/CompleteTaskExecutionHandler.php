<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CompleteTaskExecutionCommand;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;

final readonly class CompleteTaskExecutionHandler
{
    public function __construct(
        private TaskExecutionRepositoryInterface $taskExecutionRepository
    ) {
    }

    public function __invoke(CompleteTaskExecutionCommand $command): void
    {
        $execution = $this->taskExecutionRepository->findById(
            Uuid::fromString($command->executionId)
        );

        if ($execution === null) {
            throw new \DomainException(
                sprintf('Task execution with ID %s not found', $command->executionId)
            );
        }

        $execution->complete(Uuid::fromString($command->userId));
        $this->taskExecutionRepository->save($execution);
    }
}
