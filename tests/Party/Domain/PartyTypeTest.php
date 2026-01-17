<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\ValueObject\PartyType;
use PHPUnit\Framework\TestCase;

/**
 * TDD: Party Type Value Object Tests
 *
 * Party Type distinguishes between different types of parties:
 * - PERSON: Individual human being (User)
 * - ORGANIZATION: Group of people (Team)
 */
class PartyTypeTest extends TestCase
{
    public function testCanCreatePersonType(): void
    {
        // Given & When
        $type = PartyType::person();

        // Then
        $this->assertTrue($type->isPerson());
        $this->assertFalse($type->isOrganization());
        $this->assertEquals('PERSON', $type->value());
    }

    public function testCanCreateOrganizationType(): void
    {
        // Given & When
        $type = PartyType::organization();

        // Then
        $this->assertTrue($type->isOrganization());
        $this->assertFalse($type->isPerson());
        $this->assertEquals('ORGANIZATION', $type->value());
    }

    public function testCanCreateFromString(): void
    {
        // Given & When
        $personType = PartyType::fromString('PERSON');
        $orgType = PartyType::fromString('ORGANIZATION');

        // Then
        $this->assertTrue($personType->isPerson());
        $this->assertTrue($orgType->isOrganization());
    }

    public function testThrowsExceptionForInvalidType(): void
    {
        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid party type: INVALID');

        // When
        PartyType::fromString('INVALID');
    }

    public function testPartyTypesAreEqual(): void
    {
        // Given
        $type1 = PartyType::person();
        $type2 = PartyType::person();

        // Then
        $this->assertTrue($type1->equals($type2));
    }

    public function testPartyTypesAreNotEqual(): void
    {
        // Given
        $personType = PartyType::person();
        $orgType = PartyType::organization();

        // Then
        $this->assertFalse($personType->equals($orgType));
    }

    public function testCanConvertToString(): void
    {
        // Given
        $type = PartyType::person();

        // Then
        $this->assertEquals('PERSON', (string) $type);
    }
}
