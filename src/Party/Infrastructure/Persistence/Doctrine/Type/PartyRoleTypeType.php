<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine\Type;

use App\Party\Domain\ValueObject\PartyRoleType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

class PartyRoleTypeType extends Type
{
    public const NAME = 'party_role_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PartyRoleType
    {
        if ($value === null || $value instanceof PartyRoleType) {
            return $value;
        }

        return PartyRoleType::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof PartyRoleType) {
            throw new InvalidArgumentException('Expected PartyRoleType instance');
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
