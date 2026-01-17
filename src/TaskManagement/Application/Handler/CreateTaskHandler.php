<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private \App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface $teamMemberRepository
    ) {
    }

    public function __invoke(CreateTaskCommand $command): void
    {
        // Validate that teamId is provided
        if ($command->teamId === null) {
            throw new \InvalidArgumentException('Task must belong to a team');
        }

        // Validate that the user is a team admin
        $userId = Uuid::fromString($command->createdBy);
        $teamId = Uuid::fromString($command->teamId);

        if (!$this->teamMemberRepository->isUserAdminOfTeam($userId, $teamId)) {
            throw \App\TaskManagement\Domain\Exception\TaskCreationNotAllowedException::notTeamAdmin();
        }

        $task = Task::create(
            Uuid::fromString($command->id),
            TaskName::fromString($command->name),
            $command->description,
            Points::fromInt($command->points),
            Frequency::fromString($command->frequency),
            $command->assignedUserId ? Uuid::fromString($command->assignedUserId) : null
        );

        // Assign task to team
        $task->assignToTeam($teamId);

        $this->taskRepository->save($task);
    }
}
