<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\Person;
use App\Party\Domain\Event\PersonCreated;
use App\Party\Domain\ValueObject\PartyType;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * TDD: Person Entity Tests
 *
 * Person represents an individual human being in the Party archetype.
 * It's a concrete implementation of Party.
 */
class PersonTest extends TestCase
{
    public function testCanCreatePerson(): void
    {
        // Given
        $id = Uuid::generate();
        $name = 'John Doe';
        $email = Email::fromString('john@example.com');

        // When
        $person = Person::create($id, $name, $email);

        // Then
        $this->assertEquals($id, $person->id());
        $this->assertEquals($name, $person->name());
        $this->assertEquals($name, $person->getName()); // Party interface
        $this->assertEquals($email, $person->email());
        $this->assertTrue($person->isPerson());
        $this->assertFalse($person->isOrganization());
        $this->assertEquals(PartyType::person(), $person->type());
    }

    public function testPersonCreationRecordsDomainEvent(): void
    {
        // Given
        $id = Uuid::generate();
        $name = 'Jane Doe';
        $email = Email::fromString('jane@example.com');

        // When
        $person = Person::create($id, $name, $email);
        $events = $person->pullDomainEvents();

        // Then
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PersonCreated::class, $events[0]);

        /** @var PersonCreated $event */
        $event = $events[0];
        $this->assertEquals($id, $event->personId);
        $this->assertEquals($name, $event->name);
        $this->assertEquals($email, $event->email);
    }

    public function testDomainEventsAreClearedAfterPulling(): void
    {
        // Given
        $person = Person::create(
            Uuid::generate(),
            'Test Person',
            Email::fromString('test@example.com')
        );

        // When
        $events1 = $person->pullDomainEvents();
        $events2 = $person->pullDomainEvents();

        // Then
        $this->assertCount(1, $events1);
        $this->assertCount(0, $events2);
    }

    public function testCanUpdatePersonName(): void
    {
        // Given
        $person = Person::create(
            Uuid::generate(),
            'Original Name',
            Email::fromString('test@example.com')
        );

        // When
        $person->updateName('Updated Name');

        // Then
        $this->assertEquals('Updated Name', $person->name());
        $this->assertEquals('Updated Name', $person->getName());
    }

    public function testCanUpdatePersonEmail(): void
    {
        // Given
        $person = Person::create(
            Uuid::generate(),
            'Test Person',
            Email::fromString('old@example.com')
        );

        $newEmail = Email::fromString('new@example.com');

        // When
        $person->updateEmail($newEmail);

        // Then
        $this->assertEquals($newEmail, $person->email());
    }

    public function testPersonsWithSameIdAreEqual(): void
    {
        // Given
        $id = Uuid::generate();
        $person1 = Person::create($id, 'Person 1', Email::fromString('p1@example.com'));
        $person2 = Person::create($id, 'Person 2', Email::fromString('p2@example.com'));

        // Then
        $this->assertTrue($person1->equals($person2));
    }

    public function testPersonsWithDifferentIdsAreNotEqual(): void
    {
        // Given
        $person1 = Person::create(Uuid::generate(), 'Person 1', Email::fromString('p1@example.com'));
        $person2 = Person::create(Uuid::generate(), 'Person 2', Email::fromString('p2@example.com'));

        // Then
        $this->assertFalse($person1->equals($person2));
    }
}
