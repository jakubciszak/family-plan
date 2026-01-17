<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine\Type;

use App\Party\Domain\ValueObject\PartyType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PartyTypeType extends Type
{
    private const TYPE_NAME = 'party_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PartyType
    {
        if ($value === null || $value instanceof PartyType) {
            return $value;
        }

        return PartyType::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PartyType) {
            return $value->value();
        }

        return $value;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
