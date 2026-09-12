<?php

declare(strict_types=1);

namespace App\Party\Domain\ValueObject;

use InvalidArgumentException;

final readonly class PartyRoleType
{
    private const TEAM_MEMBER = 'TEAM_MEMBER';
    private const TEAM_ADMIN = 'TEAM_ADMIN';
    private const TEAM = 'TEAM';

    private const PLAYED_BY = [
        self::TEAM_MEMBER => 'PERSON',
        self::TEAM_ADMIN => 'PERSON',
        self::TEAM => 'ORGANIZATION',
    ];

    private function __construct(
        private string $value
    ) {
        if (!array_key_exists($value, self::PLAYED_BY)) {
            throw new InvalidArgumentException(sprintf('Invalid party role type: %s', $value));
        }
    }

    public static function teamMember(): self
    {
        return new self(self::TEAM_MEMBER);
    }

    public static function teamAdmin(): self
    {
        return new self(self::TEAM_ADMIN);
    }

    public static function team(): self
    {
        return new self(self::TEAM);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isPlayedBy(PartyType $partyType): bool
    {
        return self::PLAYED_BY[$this->value] === $partyType->value();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
