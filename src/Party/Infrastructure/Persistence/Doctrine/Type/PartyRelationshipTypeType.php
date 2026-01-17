<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine\Type;

use App\Party\Domain\ValueObject\PartyRelationshipType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine Type for PartyRelationshipType Value Object
 */
class PartyRelationshipTypeType extends Type
{
    public const NAME = 'party_relationship_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PartyRelationshipType
    {
        if ($value === null) {
            return null;
        }

        return PartyRelationshipType::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof PartyRelationshipType) {
            throw new \InvalidArgumentException('Expected PartyRelationshipType instance');
        }

        return $value->value();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
