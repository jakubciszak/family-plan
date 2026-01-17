<?php

declare(strict_types=1);

namespace App\Tests\Party\Application;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\Person;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use PHPUnit\Framework\TestCase;

/**
 * TDD: PartyAdapter Tests
 *
 * Tests the adapter that converts between User/Team and Person/Organization
 */
class PartyAdapterTest extends TestCase
{
    private PartyAdapter $adapter;
    private InMemoryPartyRepository $partyRepository;
    private InMemoryPartyRelationshipRepository $relationshipRepository;

    protected function setUp(): void
    {
        $this->partyRepository = new InMemoryPartyRepository();
        $this->relationshipRepository = new InMemoryPartyRelationshipRepository();
        $this->adapter = new PartyAdapter(
            $this->partyRepository,
            $this->relationshipRepository
        );
    }

    public function testCanConvertUserToPerson(): void
    {
        // Given
        $user = User::create(
            Uuid::generate(),
            'Alice Smith',
            Email::fromString('alice@example.com'),
            'hashed_password',
            Role::USER
        );

        // When
        $person = $this->adapter->userToPerson($user);

        // Then
        $this->assertInstanceOf(Person::class, $person);
        $this->assertEquals($user->id(), $person->id());
        $this->assertEquals($user->name(), $person->name());
        $this->assertEquals($user->email(), $person->email());
        $this->assertTrue($person->isPerson());
    }

    public function testCanConvertTeamToOrganization(): void
    {
        // Given
        $team = Team::create(
            Uuid::generate(),
            TeamName::fromString('Smith Family'),
            'The Smith family household',
            Uuid::generate()
        );

        // When
        $organization = $this->adapter->teamToOrganization($team);

        // Then
        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertEquals($team->id(), $organization->id());
        $this->assertEquals($team->name()->value(), $organization->name());
        $this->assertEquals($team->description(), $organization->description());
        $this->assertTrue($organization->isOrganization());
    }

    public function testCanGetOrCreatePersonFromUser(): void
    {
        // Given
        $user = User::create(
            Uuid::generate(),
            'Bob Jones',
            Email::fromString('bob@example.com'),
            'hashed_password',
            Role::USER
        );

        // When
        $person1 = $this->adapter->getOrCreatePerson($user);
        $person2 = $this->adapter->getOrCreatePerson($user);

        // Then
        $this->assertEquals($person1->id(), $person2->id());
        $this->assertCount(1, $this->partyRepository->findAll());
    }

    public function testCanGetOrCreateOrganizationFromTeam(): void
    {
        // Given
        $team = Team::create(
            Uuid::generate(),
            TeamName::fromString('Jones Family'),
            null,
            Uuid::generate()
        );

        // When
        $org1 = $this->adapter->getOrCreateOrganization($team);
        $org2 = $this->adapter->getOrCreateOrganization($team);

        // Then
        $this->assertEquals($org1->id(), $org2->id());
        $this->assertCount(1, $this->partyRepository->findAll());
    }

    public function testCanSyncUserUpdatesToPerson(): void
    {
        // Given
        $user = User::create(
            Uuid::generate(),
            'Charlie Brown',
            Email::fromString('charlie@example.com'),
            'hashed_password',
            Role::USER
        );

        $person = $this->adapter->getOrCreatePerson($user);

        // When
        $user->updateName('Charles Brown');
        $user->updateEmail(Email::fromString('charles@example.com'));
        $this->adapter->syncUserToPerson($user);

        // Then
        $updated = $this->partyRepository->findById($person->id());
        $this->assertEquals('Charles Brown', $updated->getName());
        $this->assertEquals('charles@example.com', $updated->email()->value());
    }

    public function testCanSyncTeamUpdatesToOrganization(): void
    {
        // Given
        $team = Team::create(
            Uuid::generate(),
            TeamName::fromString('Davis Family'),
            'Original description',
            Uuid::generate()
        );

        $organization = $this->adapter->getOrCreateOrganization($team);

        // When
        $team->updateName(TeamName::fromString('The Davis Family'));
        $team->updateDescription('Updated description');
        $this->adapter->syncTeamToOrganization($team);

        // Then
        $updated = $this->partyRepository->findById($organization->id());
        $this->assertEquals('The Davis Family', $updated->getName());
        $this->assertEquals('Updated description', $updated->description());
    }
}
