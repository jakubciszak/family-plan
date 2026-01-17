<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Event\PartyRelationshipCreated;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * TDD: PartyRelationship Entity Tests
 *
 * PartyRelationship represents a relationship between two parties.
 * Examples:
 * - Person is MEMBER_OF Organization
 * - Person is ADMIN_OF Organization
 */
class PartyRelationshipTest extends TestCase
{
    public function testCanCreatePartyRelationship(): void
    {
        // Given
        $id = Uuid::generate();
        $person = Person::create(Uuid::generate(), 'Alice', Email::fromString('alice@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Smith Family', 'The Smith family');
        $type = PartyRelationshipType::memberOf();

        // When
        $relationship = PartyRelationship::create($id, $person, $organization, $type);

        // Then
        $this->assertEquals($id, $relationship->id());
        $this->assertEquals($person, $relationship->from());
        $this->assertEquals($organization, $relationship->to());
        $this->assertEquals($type, $relationship->type());
        $this->assertInstanceOf(DateTimeImmutable::class, $relationship->createdAt());
        $this->assertNull($relationship->endedAt());
        $this->assertTrue($relationship->isActive());
    }

    public function testPartyRelationshipCreationRecordsDomainEvent(): void
    {
        // Given
        $id = Uuid::generate();
        $person = Person::create(Uuid::generate(), 'Bob', Email::fromString('bob@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Jones Family', null);
        $type = PartyRelationshipType::adminOf();

        // When
        $relationship = PartyRelationship::create($id, $person, $organization, $type);
        $events = $relationship->pullDomainEvents();

        // Then
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PartyRelationshipCreated::class, $events[0]);

        /** @var PartyRelationshipCreated $event */
        $event = $events[0];
        $this->assertEquals($id, $event->relationshipId);
        $this->assertEquals($person->id(), $event->fromPartyId);
        $this->assertEquals($organization->id(), $event->toPartyId);
        $this->assertEquals($type, $event->type);
    }

    public function testDomainEventsAreClearedAfterPulling(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Test', Email::fromString('test@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Test Org', null);
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::memberOf()
        );

        // When
        $events1 = $relationship->pullDomainEvents();
        $events2 = $relationship->pullDomainEvents();

        // Then
        $this->assertCount(1, $events1);
        $this->assertCount(0, $events2);
    }

    public function testCanEndRelationship(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Charlie', Email::fromString('charlie@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Brown Family', null);
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::memberOf()
        );

        // When
        $endDate = new DateTimeImmutable();
        $relationship->end($endDate);

        // Then
        $this->assertEquals($endDate, $relationship->endedAt());
        $this->assertFalse($relationship->isActive());
    }

    public function testActiveRelationshipHasNoEndDate(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Dave', Email::fromString('dave@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Davis Family', null);

        // When
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::adminOf()
        );

        // Then
        $this->assertNull($relationship->endedAt());
        $this->assertTrue($relationship->isActive());
    }

    public function testCanCheckIfPartyIsFrom(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Eve', Email::fromString('eve@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Evans Family', null);
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::memberOf()
        );

        // Then
        $this->assertTrue($relationship->isFrom($person->id()));
        $this->assertFalse($relationship->isFrom($organization->id()));
    }

    public function testCanCheckIfPartyIsTo(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Frank', Email::fromString('frank@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Frank Family', null);
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::adminOf()
        );

        // Then
        $this->assertTrue($relationship->isTo($organization->id()));
        $this->assertFalse($relationship->isTo($person->id()));
    }

    public function testCanCheckRelationshipType(): void
    {
        // Given
        $person = Person::create(Uuid::generate(), 'Grace', Email::fromString('grace@example.com'));
        $organization = Organization::create(Uuid::generate(), 'Grace Family', null);
        $memberRelationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::memberOf()
        );
        $adminRelationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            PartyRelationshipType::adminOf()
        );

        // Then
        $this->assertTrue($memberRelationship->type()->isMemberOf());
        $this->assertFalse($memberRelationship->type()->isAdminOf());
        $this->assertTrue($adminRelationship->type()->isAdminOf());
        $this->assertFalse($adminRelationship->type()->isMemberOf());
    }
}
