<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;

/**
 * Party-Based Team Member Repository
 *
 * Adapter implementation that uses PartyRelationship under the hood
 * while maintaining TeamMemberRepositoryInterface compatibility.
 *
 * This enables gradual migration to the Party archetype pattern.
 */
class PartyBasedTeamMemberRepository implements TeamMemberRepositoryInterface
{
    /**
     * Internal cache for TeamMember objects
     * @var array<string, TeamMember>
     */
    private array $membersCache = [];

    public function __construct(
        private PartyRelationshipRepositoryInterface $relationshipRepository,
        private PartyAdapter $partyAdapter
    ) {
    }

    public function save(TeamMember $member): void
    {
        // Cache the TeamMember
        $this->membersCache[$member->id()->value()] = $member;

        // Check if PartyRelationship already exists
        $existing = $this->relationshipRepository->findById($member->id());

        if ($existing === null) {
            // Create new PartyRelationship
            $relationship = PartyRelationship::create(
                $member->id(),
                // Note: In real implementation, we'd get Person and Organization from PartyAdapter
                // For now, we create minimal Party entities
                \App\Party\Domain\Entity\Person::create(
                    $member->userId(),
                    'User ' . $member->userId()->value(),
                    \App\UserManagement\Domain\ValueObject\Email::fromString('user@temp.com')
                ),
                \App\Party\Domain\Entity\Organization::create(
                    $member->teamId(),
                    'Team ' . $member->teamId()->value(),
                    null
                ),
                $this->teamRoleToPartyRelationshipType($member->role())
            );
            $this->relationshipRepository->save($relationship);
        }
    }

    public function findById(Uuid $id): ?TeamMember
    {
        return $this->membersCache[$id->value()] ?? null;
    }

    public function findByTeamIdAndUserId(Uuid $teamId, Uuid $userId): ?TeamMember
    {
        foreach ($this->membersCache as $member) {
            if ($member->teamId()->value() === $teamId->value()
                && $member->userId()->value() === $userId->value()) {
                return $member;
            }
        }
        return null;
    }

    public function findByTeamId(Uuid $teamId): array
    {
        return array_values(array_filter(
            $this->membersCache,
            fn(TeamMember $member) => $member->teamId()->value() === $teamId->value()
        ));
    }

    public function findByUserId(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->membersCache,
            fn(TeamMember $member) => $member->userId()->value() === $userId->value()
        ));
    }

    public function remove(TeamMember $member): void
    {
        unset($this->membersCache[$member->id()->value()]);

        // End the PartyRelationship
        $relationship = $this->relationshipRepository->findById($member->id());
        if ($relationship !== null) {
            $relationship->end(new \DateTimeImmutable());
            $this->relationshipRepository->save($relationship);
        }
    }

    public function isUserMemberOfTeam(Uuid $userId, Uuid $teamId): bool
    {
        return $this->findByTeamIdAndUserId($teamId, $userId) !== null;
    }

    public function isUserAdminOfTeam(Uuid $userId, Uuid $teamId): bool
    {
        $member = $this->findByTeamIdAndUserId($teamId, $userId);
        return $member !== null && $member->isAdmin();
    }

    /**
     * Convert TeamRole to PartyRelationshipType
     */
    private function teamRoleToPartyRelationshipType(TeamRole $role): PartyRelationshipType
    {
        return $role->isAdmin()
            ? PartyRelationshipType::adminOf()
            : PartyRelationshipType::memberOf();
    }

    /**
     * Clear cache (for testing)
     */
    public function clear(): void
    {
        $this->membersCache = [];
    }
}
