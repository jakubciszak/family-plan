<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine\Type;

use App\Party\Domain\ValueObject\ResponsibilityType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

class ResponsibilityTypeType extends Type
{
    public const NAME = 'responsibility_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ResponsibilityType
    {
        if ($value === null || $value instanceof ResponsibilityType) {
            return $value;
        }

        return ResponsibilityType::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof ResponsibilityType) {
            throw new InvalidArgumentException('Expected ResponsibilityType instance');
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
