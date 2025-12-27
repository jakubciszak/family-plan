<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TaskManagement\Domain\ValueObject\RuleType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class RuleTypeType extends Type
{
    private const NAME = 'rule_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RuleType
    {
        if ($value === null) {
            return null;
        }

        return RuleType::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RuleType) {
            return $value->value;
        }

        return $value;
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
