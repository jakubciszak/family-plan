<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Shared\Domain\ValueObject\Uuid;

final class UuidMother
{
    public static function create(?string $value = null): Uuid
    {
        if ($value === null) {
            return Uuid::generate();
        }
        
        return Uuid::fromString($value);
    }

    public static function random(): Uuid
    {
        return Uuid::generate();
    }

    public static function fixed(): Uuid
    {
        return Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
    }
}
