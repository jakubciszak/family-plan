<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TaskManagement\Domain\ValueObject\Points;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PointsType extends Type
{
    private const TYPE_NAME = 'points';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Points
    {
        if ($value === null || $value instanceof Points) {
            return $value;
        }

        return Points::fromInt((int) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Points) {
            return $value->value();
        }

        return (int) $value;
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
