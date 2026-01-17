<?php

declare(strict_types=1);

namespace App\Tests\TeamManagement\Infrastructure;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\TeamManagement\Infrastructure\Persistence\PartyBasedTeamMemberRepository;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use PHPUnit\Framework\TestCase;

/**
 * TDD: PartyBasedTeamMemberRepository Tests
 *
 * Tests the new implementation that uses PartyRelationship under the hood
 */
class PartyBasedTeamMemberRepositoryTest extends TestCase
{
    private PartyBasedTeamMemberRepository $repository;
    private PartyAdapter $partyAdapter;
    private InMemoryPartyRepository $partyRepository;
    private InMemoryPartyRelationshipRepository $relationshipRepository;

    protected function setUp(): void
    {
        $this->partyRepository = new InMemoryPartyRepository();
        $this->relationshipRepository = new InMemoryPartyRelationshipRepository();
        $this->partyAdapter = new PartyAdapter(
            $this->partyRepository,
            $this->relationshipRepository
        );
        $this->repository = new PartyBasedTeamMemberRepository(
            $this->relationshipRepository,
            $this->partyAdapter
        );
    }

    public function testCanSaveAndFindTeamMember(): void
    {
        // Given
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $member = TeamMember::create(
            Uuid::generate(),
            $teamId,
            $userId,
            TeamRole::member()
        );

        // When
        $this->repository->save($member);
        $found = $this->repository->findById($member->id());

        // Then
        $this->assertNotNull($found);
        $this->assertEquals($member->id(), $found->id());
    }

    public function testCanFindByTeamIdAndUserId(): void
    {
        // Given
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $member = TeamMember::create(
            Uuid::generate(),
            $teamId,
            $userId,
            TeamRole::admin()
        );

        // When
        $this->repository->save($member);
        $found = $this->repository->findByTeamIdAndUserId($teamId, $userId);

        // Then
        $this->assertNotNull($found);
        $this->assertEquals($member->id(), $found->id());
        $this->assertTrue($found->isAdmin());
    }

    public function testCanFindByTeamId(): void
    {
        // Given
        $teamId = Uuid::generate();
        $user1Id = Uuid::generate();
        $user2Id = Uuid::generate();

        $member1 = TeamMember::create(Uuid::generate(), $teamId, $user1Id, TeamRole::admin());
        $member2 = TeamMember::create(Uuid::generate(), $teamId, $user2Id, TeamRole::member());

        // When
        $this->repository->save($member1);
        $this->repository->save($member2);
        $members = $this->repository->findByTeamId($teamId);

        // Then
        $this->assertCount(2, $members);
    }

    public function testCanFindByUserId(): void
    {
        // Given
        $userId = Uuid::generate();
        $team1Id = Uuid::generate();
        $team2Id = Uuid::generate();

        $member1 = TeamMember::create(Uuid::generate(), $team1Id, $userId, TeamRole::admin());
        $member2 = TeamMember::create(Uuid::generate(), $team2Id, $userId, TeamRole::member());

        // When
        $this->repository->save($member1);
        $this->repository->save($member2);
        $members = $this->repository->findByUserId($userId);

        // Then
        $this->assertCount(2, $members);
    }

    public function testCanCheckIfUserIsMemberOfTeam(): void
    {
        // Given
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $member = TeamMember::create(Uuid::generate(), $teamId, $userId, TeamRole::member());

        // When
        $this->repository->save($member);
        $isMember = $this->repository->isUserMemberOfTeam($userId, $teamId);

        // Then
        $this->assertTrue($isMember);
    }

    public function testCanCheckIfUserIsAdminOfTeam(): void
    {
        // Given
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $member = TeamMember::create(Uuid::generate(), $teamId, $userId, TeamRole::admin());

        // When
        $this->repository->save($member);
        $isAdmin = $this->repository->isUserAdminOfTeam($userId, $teamId);

        // Then
        $this->assertTrue($isAdmin);
    }

    public function testRegularMemberIsNotAdmin(): void
    {
        // Given
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $member = TeamMember::create(Uuid::generate(), $teamId, $userId, TeamRole::member());

        // When
        $this->repository->save($member);
        $isAdmin = $this->repository->isUserAdminOfTeam($userId, $teamId);

        // Then
        $this->assertFalse($isAdmin);
    }

    public function testCanRemoveTeamMember(): void
    {
        // Given
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $member = TeamMember::create(Uuid::generate(), $teamId, $userId, TeamRole::member());

        // When
        $this->repository->save($member);
        $this->repository->remove($member);
        $found = $this->repository->findById($member->id());

        // Then
        $this->assertNull($found);
    }
}
