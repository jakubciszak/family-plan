<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\ValueObject;

enum AccountKind: string
{
    case TASKS = 'tasks';
    case BONUSES = 'bonuses';

    /**
     * @return AccountKind[]
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @param string[] $values
     * @return AccountKind[]
     */
    public static function fromValues(array $values): array
    {
        return array_map(static fn (string $value) => self::from($value), $values);
    }
}
