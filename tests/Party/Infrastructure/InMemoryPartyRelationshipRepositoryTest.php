<?php

declare(strict_types=1);

namespace App\Tests\Party\Infrastructure;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * TDD: InMemoryPartyRelationshipRepository Tests
 */
class InMemoryPartyRelationshipRepositoryTest extends TestCase
{
    private InMemoryPartyRelationshipRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPartyRelationshipRepository();
    }

    public function testCanSaveAndFindRelationship(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Alice', Email::fromString('alice@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Smith Family', null);
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::memberOf()
        );

        // When
        $this->repository->save($relationship);
        $found = $this->repository->findById($relationship->id());

        // Then
        $this->assertNotNull($found);
        $this->assertEquals($relationship->id(), $found->id());
    }

    public function testCanFindRelationshipsByFromParty(): void
    {
        // Given
        $person1 = Person::create(Uuid::generate(), 'Bob', Email::fromString('bob@example.com'));
        $person2 = Person::create(Uuid::generate(), 'Charlie', Email::fromString('charlie@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Jones Family', null);

        $rel1 = PartyRelationship::create(Uuid::generate(), $person1, $organization, PartyRelationshipType::memberOf());
        $rel2 = PartyRelationship::create(Uuid::generate(), $person1, $organization, PartyRelationshipType::adminOf());
        $rel3 = PartyRelationship::create(Uuid::generate(), $person2, $organization, PartyRelationshipType::memberOf());

        // When
        $this->repository->save($rel1);
        $this->repository->save($rel2);
        $this->repository->save($rel3);
        $relationships = $this->repository->findByFromParty($person1->id());

        // Then
        $this->assertCount(2, $relationships);
    }

    public function testCanFindRelationshipsByToParty(): void
    {
        // Given
        $person1 = Person::create(Uuid::generate(), 'Dave', Email::fromString('dave@example.com'));
        $person2 = Person::create(Uuid::generate(), 'Eve', Email::fromString('eve@example.com'));
        $organization1 = Organization::create(Uuid::generate(), 'Brown Family', null);
        $organization2 = Organization::create(Uuid::generate(), 'Davis Family', null);

        $rel1 = PartyRelationship::create(Uuid::generate(), $person1, $organization1, PartyRelationshipType::memberOf());
        $rel2 = PartyRelationship::create(Uuid::generate(), $person2, $organization1, PartyRelationshipType::memberOf());
        $rel3 = PartyRelationship::create(Uuid::generate(), $person1, $organization2, PartyRelationshipType::adminOf());

        // When
        $this->repository->save($rel1);
        $this->repository->save($rel2);
        $this->repository->save($rel3);
        $relationships = $this->repository->findByToParty($organization1->id());

        // Then
        $this->assertCount(2, $relationships);
    }

    public function testCanFindActiveRelationships(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Frank', Email::fromString('frank@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Evans Family', null);

        $activeRel = PartyRelationship::create(Uuid::generate(), $person, $organization, PartyRelationshipType::memberOf());
        $endedRel = PartyRelationship::create(Uuid::generate(), $person, $organization, PartyRelationshipType::adminOf());
        $endedRel->end(new \DateTimeImmutable());

        // When
        $this->repository->save($activeRel);
        $this->repository->save($endedRel);
        $active = $this->repository->findActiveRelationships($person->id(), $organization->id());

        // Then
        $this->assertCount(1, $active);
        $this->assertTrue($active[0]->isActive());
    }

    public function testCanFindByType(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Grace', Email::fromString('grace@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Grace Family', null);

        $memberRel = PartyRelationship::create(Uuid::generate(), $person, $organization, PartyRelationshipType::memberOf());
        $adminRel = PartyRelationship::create(Uuid::generate(), $person, $organization, PartyRelationshipType::adminOf());

        // When
        $this->repository->save($memberRel);
        $this->repository->save($adminRel);
        $adminRels = $this->repository->findByFromPartyAndType($person->id(), PartyRelationshipType::adminOf());

        // Then
        $this->assertCount(1, $adminRels);
        $this->assertTrue($adminRels[0]->type()->isAdminOf());
    }

    public function testIsPartyAdminOfOrganization(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Henry', Email::fromString('henry@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Henry Family', null);

        $adminRel = PartyRelationship::create(Uuid::generate(), $person, $organization, PartyRelationshipType::adminOf());

        // When
        $this->repository->save($adminRel);
        $isAdmin = $this->repository->isPartyAdminOf($person->id(), $organization->id());

        // Then
        $this->assertTrue($isAdmin);
    }

    public function testIsNotPartyAdminOfOrganization(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Iris', Email::fromString('iris@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Iris Family', null);

        $memberRel = PartyRelationship::create(Uuid::generate(), $person, $organization, PartyRelationshipType::memberOf());

        // When
        $this->repository->save($memberRel);
        $isAdmin = $this->repository->isPartyAdminOf($person->id(), $organization->id());

        // Then
        $this->assertFalse($isAdmin);
    }
}
