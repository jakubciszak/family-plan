<?php

declare(strict_types=1);

namespace App\Party\Communication\EventSubscriber;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Event\TeamCreated;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Team Created Event Subscriber
 *
 * Automatically creates an Organization entity when a Team is created,
 * and creates an ADMIN_OF relationship between the creator Person and Organization.
 * This keeps the Party model synchronized with the legacy Team model.
 */
class TeamCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PartyAdapter $partyAdapter,
        private TeamRepositoryInterface $teamRepository,
        private UserRepositoryInterface $userRepository,
        private PartyRelationshipRepositoryInterface $relationshipRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TeamCreated::class => 'onTeamCreated',
        ];
    }

    public function onTeamCreated(TeamCreated $event): void
    {
        // Find the team
        $team = $this->teamRepository->findById($event->teamId);

        if ($team === null) {
            return;
        }

        // Create corresponding Organization in Party model
        $organization = $this->partyAdapter->getOrCreateOrganization($team);

        // Find the creator user
        $creator = $this->userRepository->findById($event->createdBy);

        if ($creator === null) {
            return;
        }

        // Create corresponding Person for the creator
        $person = $this->partyAdapter->getOrCreatePerson($creator);

        // Create ADMIN_OF relationship between creator and organization
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::adminOf()
        );

        $this->relationshipRepository->save($relationship);
    }
}
