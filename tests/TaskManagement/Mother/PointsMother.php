<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\TaskManagement\Domain\ValueObject\Points;

final class PointsMother
{
    public static function fromInt(int $value): Points
    {
        return Points::fromInt($value);
    }

    public static function create(int $value = 10): Points
    {
        return Points::fromInt($value);
    }

    public static function random(): Points
    {
        return Points::fromInt(rand(5, 100));
    }

    public static function low(): Points
    {
        return Points::fromInt(5);
    }

    public static function medium(): Points
    {
        return Points::fromInt(25);
    }

    public static function high(): Points
    {
        return Points::fromInt(50);
    }

    public static function maximum(): Points
    {
        return Points::fromInt(1000);
    }

    public static function minimum(): Points
    {
        return Points::fromInt(0);
    }
}
