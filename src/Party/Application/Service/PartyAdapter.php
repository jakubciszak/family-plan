<?php

declare(strict_types=1);

namespace App\Party\Application\Service;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\Party;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Repository\PartyRepositoryInterface;
use App\Party\Domain\Repository\PartyRoleRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\UserManagement\Domain\Entity\User;

/**
 * Party Adapter Service
 *
 * Adapts between legacy User/Team entities and new Party model.
 * This allows gradual migration to the Party archetype pattern.
 */
class PartyAdapter
{
    public function __construct(
        private PartyRepositoryInterface $partyRepository,
        private PartyRoleRepositoryInterface $roleRepository
    ) {
    }

    /**
     * Convert User to Person
     */
    public function userToPerson(User $user): Person
    {
        return Person::create(
            $user->id(),
            $user->name(),
            $user->email()
        );
    }

    /**
     * Convert Team to Organization
     */
    public function teamToOrganization(Team $team): Organization
    {
        return Organization::create(
            $team->id(),
            $team->name()->value(),
            $team->description()
        );
    }

    /**
     * Get or create Person from User
     *
     * Checks if Person already exists in repository, creates if not
     */
    public function getOrCreatePerson(User $user): Person
    {
        $existing = $this->partyRepository->findById($user->id());

        if ($existing !== null && $existing instanceof Person) {
            return $existing;
        }

        $person = $this->userToPerson($user);
        $this->partyRepository->save($person);

        return $person;
    }

    /**
     * Get or create Organization from Team
     *
     * Checks if Organization already exists in repository, creates if not
     */
    public function getOrCreateOrganization(Team $team): Organization
    {
        $existing = $this->partyRepository->findById($team->id());

        if ($existing !== null && $existing instanceof Organization) {
            return $existing;
        }

        $organization = $this->teamToOrganization($team);
        $this->partyRepository->save($organization);

        return $organization;
    }

    /**
     * Get or start the role a party plays
     */
    public function getOrStartRole(Party $party, PartyRoleType $type): PartyRole
    {
        $existing = $this->roleRepository->findByPartyAndType($party->id(), $type);

        if ($existing !== null) {
            return $existing;
        }

        $role = PartyRole::start(Uuid::generate(), $party, $type);
        $this->roleRepository->save($role);

        return $role;
    }

    /**
     * Sync User updates to Person
     *
     * Updates the Person entity with current User data
     */
    public function syncUserToPerson(User $user): void
    {
        $person = $this->partyRepository->findById($user->id());

        if ($person === null || !($person instanceof Person)) {
            $person = $this->userToPerson($user);
        } else {
            // Update existing person
            $person->updateName($user->name());
            $person->updateEmail($user->email());
        }

        $this->partyRepository->save($person);
    }

    /**
     * Sync Team updates to Organization
     *
     * Updates the Organization entity with current Team data
     */
    public function syncTeamToOrganization(Team $team): void
    {
        $organization = $this->partyRepository->findById($team->id());

        if ($organization === null || !($organization instanceof Organization)) {
            $organization = $this->teamToOrganization($team);
        } else {
            // Update existing organization
            $organization->updateName($team->name()->value());
            $organization->updateDescription($team->description());
        }

        $this->partyRepository->save($organization);
    }
}
