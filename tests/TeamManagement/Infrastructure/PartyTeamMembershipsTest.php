<?php

declare(strict_types=1);

namespace App\Tests\TeamManagement\Infrastructure;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRoleRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryResponsibilityRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemorySignatureRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository;
use App\TeamManagement\Infrastructure\Persistence\PartyTeamMemberships;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

class PartyTeamMembershipsTest extends TestCase
{
    private InMemoryPartyRelationshipRepository $relationships;

    private PartyResponsibilities $responsibilities;

    private InMemoryTeamRepository $teams;

    private InMemoryUserRepository $users;

    private PartyTeamMemberships $memberships;

    protected function setUp(): void
    {
        $parties = new InMemoryPartyRepository();
        $roles = new InMemoryPartyRoleRepository();
        $this->relationships = new InMemoryPartyRelationshipRepository();
        $this->responsibilities = new PartyResponsibilities(
            $roles,
            $this->relationships,
            new InMemoryResponsibilityRepository(),
            new InMemorySignatureRepository()
        );
        $this->teams = new InMemoryTeamRepository();
        $this->users = new InMemoryUserRepository();

        $this->memberships = new PartyTeamMemberships(
            $this->relationships,
            new PartyAdapter($parties, $roles),
            $this->responsibilities,
            $this->teams,
            $this->users
        );
    }

    public function testJoiningATeamIsARelationshipBetweenRoles(): void
    {
        $team = $this->team();
        $user = $this->user('Dziecko');

        $membership = $this->memberships->join($team->id(), $user->id(), TeamRole::member());

        $this->assertNotNull($membership);
        $this->assertTrue($membership->teamId()->equals($team->id()));
        $this->assertTrue($membership->userId()->equals($user->id()));
        $this->assertFalse($membership->isAdmin());

        $relationship = $this->relationships->findById($membership->id());
        $this->assertNotNull($relationship);
        $this->assertTrue($relationship->from()->type()->equals(PartyRoleType::teamMember()));
        $this->assertTrue($relationship->to()->type()->equals(PartyRoleType::team()));
    }

    public function testJoiningAllocatesTheResponsibilitiesOfThatRole(): void
    {
        $team = $this->team();
        $admin = $this->user('Rodzic');

        $this->memberships->join($team->id(), $admin->id(), TeamRole::admin());

        $this->assertTrue(
            $this->responsibilities->partyMay($admin->id(), ResponsibilityType::approveTask(), $team->id())
        );
    }

    public function testJoiningTwiceKeepsOneMembership(): void
    {
        $team = $this->team();
        $user = $this->user('Dziecko');

        $first = $this->memberships->join($team->id(), $user->id(), TeamRole::member());
        $second = $this->memberships->join($team->id(), $user->id(), TeamRole::member());

        $this->assertTrue($first->id()->equals($second->id()));
        $this->assertCount(1, $this->memberships->ofTeam($team->id()));
    }

    public function testLeavingEndsTheRelationship(): void
    {
        $team = $this->team();
        $user = $this->user('Dziecko');
        $this->memberships->join($team->id(), $user->id(), TeamRole::member());

        $this->memberships->leave($team->id(), $user->id());

        $this->assertNull($this->memberships->find($team->id(), $user->id()));
        $this->assertFalse($this->memberships->isMember($user->id(), $team->id()));
        $this->assertCount(0, $this->memberships->ofTeam($team->id()));
    }

    public function testLeavingTakesTheResponsibilitiesAway(): void
    {
        $team = $this->team();
        $admin = $this->user('Rodzic');
        $this->memberships->join($team->id(), $admin->id(), TeamRole::admin());

        $this->memberships->leave($team->id(), $admin->id());

        $this->assertFalse(
            $this->responsibilities->partyMay($admin->id(), ResponsibilityType::approveTask(), $team->id())
        );
    }

    public function testATeamListsItsPeopleWithTheirRoles(): void
    {
        $team = $this->team();
        $admin = $this->user('Rodzic');
        $child = $this->user('Dziecko');

        $this->memberships->join($team->id(), $admin->id(), TeamRole::admin());
        $this->memberships->join($team->id(), $child->id(), TeamRole::member());

        $this->assertCount(2, $this->memberships->ofTeam($team->id()));
        $this->assertTrue($this->memberships->isAdmin($admin->id(), $team->id()));
        $this->assertFalse($this->memberships->isAdmin($child->id(), $team->id()));
    }

    public function testMembershipOfOneTeamSaysNothingAboutAnother(): void
    {
        $ownTeam = $this->team();
        $otherTeam = $this->team('Obca rodzina');
        $user = $this->user('Dziecko');

        $this->memberships->join($ownTeam->id(), $user->id(), TeamRole::member());

        $this->assertTrue($this->memberships->isMember($user->id(), $ownTeam->id()));
        $this->assertFalse($this->memberships->isMember($user->id(), $otherTeam->id()));
        $this->assertCount(1, $this->memberships->ofUser($user->id()));
    }

    public function testNobodyJoinsATeamThatDoesNotExist(): void
    {
        $user = $this->user('Dziecko');

        $this->assertNull($this->memberships->join(Uuid::generate(), $user->id(), TeamRole::member()));
    }

    private function team(string $name = 'Rodzina'): Team
    {
        $team = Team::create(Uuid::generate(), TeamName::fromString($name), null, Uuid::generate());
        $this->teams->save($team);

        return $team;
    }

    private function user(string $name): User
    {
        $user = User::create(
            Uuid::generate(),
            $name,
            Email::fromString(sprintf('%s-%s@example.com', strtolower($name), uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );
        $this->users->save($user);

        return $user;
    }
}
