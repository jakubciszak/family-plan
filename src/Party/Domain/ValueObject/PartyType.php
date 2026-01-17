<?php

declare(strict_types=1);

namespace App\Party\Domain\ValueObject;

/**
 * Party Type Value Object
 *
 * Represents the type of party in the Party archetype pattern.
 * Based on: https://www.softwarearchetypes.com/archetypes/party/
 *
 * Types:
 * - PERSON: Individual human being
 * - ORGANIZATION: Group of people
 */
final readonly class PartyType
{
    private const PERSON = 'PERSON';
    private const ORGANIZATION = 'ORGANIZATION';

    private function __construct(
        private string $value
    ) {
    }

    public static function person(): self
    {
        return new self(self::PERSON);
    }

    public static function organization(): self
    {
        return new self(self::ORGANIZATION);
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper($value);

        if (!in_array($normalized, [self::PERSON, self::ORGANIZATION], true)) {
            throw new \InvalidArgumentException("Invalid party type: {$value}");
        }

        return new self($normalized);
    }

    public function isPerson(): bool
    {
        return $this->value === self::PERSON;
    }

    public function isOrganization(): bool
    {
        return $this->value === self::ORGANIZATION;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
