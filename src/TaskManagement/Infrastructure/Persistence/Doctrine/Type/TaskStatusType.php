<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TaskManagement\Domain\ValueObject\TaskStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TaskStatusType extends Type
{
    private const TYPE_NAME = 'task_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaskStatus
    {
        if ($value === null || $value instanceof TaskStatus) {
            return $value;
        }

        return TaskStatus::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TaskStatus) {
            return $value->value;
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
