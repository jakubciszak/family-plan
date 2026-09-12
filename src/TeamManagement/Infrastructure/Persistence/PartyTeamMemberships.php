<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;

final readonly class PartyTeamMemberships implements TeamMembershipRepositoryInterface
{
    public function __construct(
        private PartyRelationshipRepositoryInterface $relationshipRepository,
        private PartyAdapter $partyAdapter,
        private PartyResponsibilities $responsibilities,
        private TeamRepositoryInterface $teamRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function join(Uuid $teamId, Uuid $userId, TeamRole $role): ?TeamMembership
    {
        $user = $this->userRepository->findById($userId);
        $team = $this->teamRepository->findById($teamId);

        if ($user === null || $team === null) {
            return null;
        }

        $existing = $this->find($teamId, $userId);

        if ($existing !== null) {
            return $existing;
        }

        $type = $role->isAdmin() ? PartyRelationshipType::adminOf() : PartyRelationshipType::memberOf();

        $memberRole = $this->partyAdapter->getOrStartRole(
            $this->partyAdapter->getOrCreatePerson($user),
            $type->fromRoleType()
        );
        $teamRole = $this->partyAdapter->getOrStartRole(
            $this->partyAdapter->getOrCreateOrganization($team),
            PartyRoleType::team()
        );

        $this->responsibilities->allocateDefaults($memberRole);
        $this->responsibilities->allocateDefaults($teamRole);

        $relationship = PartyRelationship::create(Uuid::generate(), $memberRole, $teamRole, $type);
        $this->relationshipRepository->save($relationship);

        return $this->asMembership($relationship);
    }

    public function leave(Uuid $teamId, Uuid $userId): void
    {
        foreach ($this->activeRelationshipsOfUser($userId) as $relationship) {
            if ($relationship->to()->partyId()->equals($teamId)) {
                $relationship->end(new DateTimeImmutable());
                $this->relationshipRepository->save($relationship);
            }
        }
    }

    public function find(Uuid $teamId, Uuid $userId): ?TeamMembership
    {
        foreach ($this->activeRelationshipsOfUser($userId) as $relationship) {
            if ($relationship->to()->partyId()->equals($teamId)) {
                return $this->asMembership($relationship);
            }
        }

        return null;
    }

    public function ofTeam(Uuid $teamId): array
    {
        $memberships = array_map(
            fn (PartyRelationship $relationship) => $this->asMembership($relationship),
            array_filter(
                $this->relationshipRepository->findByToParty($teamId),
                fn (PartyRelationship $relationship) => $relationship->isActive()
            )
        );

        usort(
            $memberships,
            fn (TeamMembership $first, TeamMembership $second) => $first->joinedAt() <=> $second->joinedAt()
        );

        return $memberships;
    }

    public function ofUser(Uuid $userId): array
    {
        return array_values(array_map(
            fn (PartyRelationship $relationship) => $this->asMembership($relationship),
            $this->activeRelationshipsOfUser($userId)
        ));
    }

    public function isMember(Uuid $userId, Uuid $teamId): bool
    {
        return $this->find($teamId, $userId) !== null;
    }

    public function isAdmin(Uuid $userId, Uuid $teamId): bool
    {
        return $this->find($teamId, $userId)?->isAdmin() === true;
    }

    /**
     * @return PartyRelationship[]
     */
    private function activeRelationshipsOfUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->relationshipRepository->findByFromParty($userId),
            fn (PartyRelationship $relationship) => $relationship->isActive()
        ));
    }

    private function asMembership(PartyRelationship $relationship): TeamMembership
    {
        return new TeamMembership(
            $relationship->id(),
            $relationship->to()->partyId(),
            $relationship->from()->partyId(),
            $this->teamRoleOf($relationship->from()),
            $relationship->createdAt()
        );
    }

    private function teamRoleOf(PartyRole $role): TeamRole
    {
        return $role->type()->equals(PartyRoleType::teamAdmin())
            ? TeamRole::admin()
            : TeamRole::member();
    }
}
