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

/**
 * Party-Based Create Task Handler
 *
 * Uses the Party archetype model (Person, Organization, PartyRelationship)
 * instead of the legacy User/Team/TeamMember model.
 *
 * This demonstrates the new architectural approach where:
 * - Person represents a User
 * - Organization represents a Team
 * - PartyRelationship with ADMIN_OF type represents team admin role
 */
final readonly class PartyBasedCreateTaskHandler
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
