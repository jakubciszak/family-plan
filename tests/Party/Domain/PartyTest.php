<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\Party;
use App\Party\Domain\ValueObject\PartyType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * TDD: Abstract Party Entity Tests
 *
 * Party is the base class for all parties (Person, Organization).
 * Based on Party archetype pattern.
 */
class PartyTest extends TestCase
{
    public function testCanCreateConcreteParty(): void
    {
        // Given
        $id = Uuid::generate();
        $type = PartyType::person();
        $createdAt = new DateTimeImmutable();

        // When
        $party = new ConcretePartyForTest($id, $type, $createdAt);

        // Then
        $this->assertEquals($id, $party->id());
        $this->assertEquals($type, $party->type());
        $this->assertEquals($createdAt, $party->createdAt());
        $this->assertEquals('Test Party', $party->getName());
    }

    public function testPartyCanBeIdentifiedByType(): void
    {
        // Given
        $personParty = new ConcretePartyForTest(
            Uuid::generate(),
            PartyType::person(),
            new DateTimeImmutable()
        );

        $orgParty = new ConcretePartyForTest(
            Uuid::generate(),
            PartyType::organization(),
            new DateTimeImmutable()
        );

        // Then
        $this->assertTrue($personParty->isPerson());
        $this->assertFalse($personParty->isOrganization());

        $this->assertTrue($orgParty->isOrganization());
        $this->assertFalse($orgParty->isPerson());
    }

    public function testPartiesWithSameIdAreEqual(): void
    {
        // Given
        $id = Uuid::generate();
        $party1 = new ConcretePartyForTest($id, PartyType::person(), new DateTimeImmutable());
        $party2 = new ConcretePartyForTest($id, PartyType::person(), new DateTimeImmutable());

        // Then
        $this->assertTrue($party1->equals($party2));
    }

    public function testPartiesWithDifferentIdsAreNotEqual(): void
    {
        // Given
        $party1 = new ConcretePartyForTest(Uuid::generate(), PartyType::person(), new DateTimeImmutable());
        $party2 = new ConcretePartyForTest(Uuid::generate(), PartyType::person(), new DateTimeImmutable());

        // Then
        $this->assertFalse($party1->equals($party2));
    }
}

/**
 * Concrete implementation for testing abstract Party class
 */
class ConcretePartyForTest extends Party
{
    public function __construct(Uuid $id, PartyType $type, DateTimeImmutable $createdAt)
    {
        parent::__construct($id, $type, $createdAt);
    }

    public function getName(): string
    {
        return 'Test Party';
    }
}
