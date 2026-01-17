<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Exception\TaskCreationNotAllowedException;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Create Task Handler
 *
 * MIGRATED TO PARTY ARCHETYPE:
 * Now uses PartyRelationshipRepository instead of TeamMemberRepository.
 * Validates that Person has ADMIN_OF relationship with Organization (Team).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private PartyRelationshipRepositoryInterface $partyRelationshipRepository
    ) {
    }

    public function __invoke(CreateTaskCommand $command): void
    {
        // Validate that teamId (organizationId) is provided
        if ($command->teamId === null) {
            throw new \InvalidArgumentException('Task must belong to a team');
        }

        // Validate that the person is an admin of the organization
        $personId = Uuid::fromString($command->createdBy);
        $organizationId = Uuid::fromString($command->teamId);

        if (!$this->partyRelationshipRepository->isPartyAdminOf($personId, $organizationId)) {
            throw TaskCreationNotAllowedException::notTeamAdmin();
        }

        $task = Task::create(
            Uuid::fromString($command->id),
            TaskName::fromString($command->name),
            $command->description,
            Points::fromInt($command->points),
            Frequency::fromString($command->frequency),
            $command->assignedUserId ? Uuid::fromString($command->assignedUserId) : null
        );

        // Assign task to organization (team)
        $task->assignToTeam($organizationId);

        $this->taskRepository->save($task);
    }
}
