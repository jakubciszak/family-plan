<?php

declare(strict_types=1);

namespace App\Party\Domain\ValueObject;

/**
 * Party Relationship Type Value Object
 *
 * Represents the type of relationship between two parties.
 * Based on Party archetype pattern.
 *
 * Common types:
 * - MEMBER_OF: A party is a member of another party (e.g., Person is member of Team)
 * - ADMIN_OF: A party administers another party (e.g., Person is admin of Team)
 *
 * @see https://www.softwarearchetypes.com/archetypes/party/
 */
final readonly class PartyRelationshipType
{
    private const MEMBER_OF = 'MEMBER_OF';
    private const ADMIN_OF = 'ADMIN_OF';

    private const VALID_TYPES = [
        self::MEMBER_OF,
        self::ADMIN_OF,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid party relationship type: {$value}");
        }
    }

    /**
     * Create MEMBER_OF relationship type
     */
    public static function memberOf(): self
    {
        return new self(self::MEMBER_OF);
    }

    /**
     * Create ADMIN_OF relationship type
     */
    public static function adminOf(): self
    {
        return new self(self::ADMIN_OF);
    }

    /**
     * Create from string value
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Check if this is MEMBER_OF type
     */
    public function isMemberOf(): bool
    {
        return $this->value === self::MEMBER_OF;
    }

    /**
     * Check if this is ADMIN_OF type
     */
    public function isAdminOf(): bool
    {
        return $this->value === self::ADMIN_OF;
    }

    /**
     * Get the string value
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another PartyRelationshipType
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Convert to string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
