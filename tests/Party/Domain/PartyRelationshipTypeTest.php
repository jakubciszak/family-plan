<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\ValueObject\PartyRelationshipType;
use PHPUnit\Framework\TestCase;

/**
 * TDD: PartyRelationshipType Value Object Tests
 *
 * PartyRelationshipType represents the type of relationship between two parties.
 * Examples: MEMBER_OF, ADMIN_OF, PARENT_OF, etc.
 */
class PartyRelationshipTypeTest extends TestCase
{
    public function testCanCreateMemberOfType(): void
    {
        // When
        $type = PartyRelationshipType::memberOf();

        // Then
        $this->assertTrue($type->isMemberOf());
        $this->assertFalse($type->isAdminOf());
        $this->assertEquals('MEMBER_OF', $type->value());
    }

    public function testCanCreateAdminOfType(): void
    {
        // When
        $type = PartyRelationshipType::adminOf();

        // Then
        $this->assertTrue($type->isAdminOf());
        $this->assertFalse($type->isMemberOf());
        $this->assertEquals('ADMIN_OF', $type->value());
    }

    public function testCanCreateFromString(): void
    {
        // When
        $memberType = PartyRelationshipType::fromString('MEMBER_OF');
        $adminType = PartyRelationshipType::fromString('ADMIN_OF');

        // Then
        $this->assertTrue($memberType->isMemberOf());
        $this->assertTrue($adminType->isAdminOf());
    }

    public function testThrowsExceptionForInvalidType(): void
    {
        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid party relationship type: INVALID');

        // When
        PartyRelationshipType::fromString('INVALID');
    }

    public function testTypesAreEqualWhenSameValue(): void
    {
        // Given
        $type1 = PartyRelationshipType::memberOf();
        $type2 = PartyRelationshipType::fromString('MEMBER_OF');

        // Then
        $this->assertTrue($type1->equals($type2));
    }

    public function testTypesAreNotEqualWhenDifferentValue(): void
    {
        // Given
        $type1 = PartyRelationshipType::memberOf();
        $type2 = PartyRelationshipType::adminOf();

        // Then
        $this->assertFalse($type1->equals($type2));
    }

    public function testCanConvertToString(): void
    {
        // Given
        $memberType = PartyRelationshipType::memberOf();
        $adminType = PartyRelationshipType::adminOf();

        // Then
        $this->assertEquals('MEMBER_OF', (string) $memberType);
        $this->assertEquals('ADMIN_OF', (string) $adminType);
    }
}
