<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TaskManagement\Domain\ValueObject\ExecutionLimit;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ExecutionLimitType extends Type
{
    private const TYPE_NAME = 'execution_limit';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 100]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ExecutionLimit
    {
        if ($value === null || $value instanceof ExecutionLimit) {
            return $value;
        }

        return ExecutionLimit::fromArray(json_decode($value, true) ?? []);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ExecutionLimit) {
            return json_encode($value->toArray());
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
