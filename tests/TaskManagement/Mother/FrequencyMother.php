<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\TaskManagement\Domain\ValueObject\Frequency;

final class FrequencyMother
{
    public static function once(): Frequency
    {
        return Frequency::ONCE;
    }

    public static function daily(): Frequency
    {
        return Frequency::DAILY;
    }

    public static function weekly(): Frequency
    {
        return Frequency::WEEKLY;
    }

    public static function monthly(): Frequency
    {
        return Frequency::MONTHLY;
    }

    public static function random(): Frequency
    {
        $frequencies = [Frequency::ONCE, Frequency::DAILY, Frequency::WEEKLY, Frequency::MONTHLY];
        return $frequencies[array_rand($frequencies)];
    }
}
