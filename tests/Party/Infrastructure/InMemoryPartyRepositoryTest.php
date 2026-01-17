<?php

declare(strict_types=1);

namespace App\Tests\Party\Infrastructure;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\Person;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * TDD: InMemoryPartyRepository Tests
 */
class InMemoryPartyRepositoryTest extends TestCase
{
    private InMemoryPartyRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPartyRepository();
    }

    public function testCanSaveAndFindPerson(): void
    {
        // Given
        $person = Person::create(
            Uuid::generate(),
            'Alice Smith',
            Email::fromString('alice@example.com')
        );

        // When
        $this->repository->save($person);
        $found = $this->repository->findById($person->id());

        // Then
        $this->assertNotNull($found);
        $this->assertEquals($person->id(), $found->id());
        $this->assertEquals('Alice Smith', $found->getName());
    }

    public function testCanSaveAndFindOrganization(): void
    {
        // Given
        $organization = Organization::create(
            Uuid::generate(),
            'Smith Family',
            'The Smith family household'
        );

        // When
        $this->repository->save($organization);
        $found = $this->repository->findById($organization->id());

        // Then
        $this->assertNotNull($found);
        $this->assertEquals($organization->id(), $found->id());
        $this->assertEquals('Smith Family', $found->getName());
    }

    public function testReturnsNullForNonExistentParty(): void
    {
        // Given
        $nonExistentId = Uuid::generate();

        // When
        $found = $this->repository->findById($nonExistentId);

        // Then
        $this->assertNull($found);
    }

    public function testCanFindAllParties(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Bob', Email::fromString('bob@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Jones Family', null);

        // When
        $this->repository->save($person);
        $this->repository->save($organization);
        $all = $this->repository->findAll();

        // Then
        $this->assertCount(2, $all);
    }

    public function testCanFindPersonsByType(): void
    {
        // Given
        $person1 = Person::create(Uuid::generate(), 'Charlie', Email::fromString('charlie@example.com'));
        $person2 = Person::create(Uuid::generate(), 'Dave', Email::fromString('dave@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Brown Family', null);

        // When
        $this->repository->save($person1);
        $this->repository->save($person2);
        $this->repository->save($organization);
        $persons = $this->repository->findPersons();

        // Then
        $this->assertCount(2, $persons);
        foreach ($persons as $person) {
            $this->assertTrue($person->isPerson());
        }
    }

    public function testCanFindOrganizationsByType(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Eve', Email::fromString('eve@example.com'));
        $organization1 = Organization::create(Uuid::generate(), 'Evans Family', null);
        $organization2 = Organization::create(Uuid::generate(), 'Davis Family', 'The Davis household');

        // When
        $this->repository->save($person);
        $this->repository->save($organization1);
        $this->repository->save($organization2);
        $organizations = $this->repository->findOrganizations();

        // Then
        $this->assertCount(2, $organizations);
        foreach ($organizations as $organization) {
            $this->assertTrue($organization->isOrganization());
        }
    }

    public function testUpdatingPartySavesChanges(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Frank', Email::fromString('frank@example.com'));
        $this->repository->save($person);

        // When
        $person->updateName('Franklin');
        $this->repository->save($person);
        $found = $this->repository->findById($person->id());

        // Then
        $this->assertEquals('Franklin', $found->getName());
    }
}
