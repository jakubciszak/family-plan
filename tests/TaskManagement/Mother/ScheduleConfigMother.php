<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\TaskManagement\Domain\ValueObject\ScheduleConfig;

final class ScheduleConfigMother
{
    public static function daily(): ScheduleConfig
    {
        return ScheduleConfig::daily();
    }

    public static function weeklyOnMonday(): ScheduleConfig
    {
        return ScheduleConfig::weeklyOnDay(1); // Monday
    }

    public static function weeklyOnFriday(): ScheduleConfig
    {
        return ScheduleConfig::weeklyOnDay(5); // Friday
    }

    public static function monthlyOnDay(int $dayOfMonth): ScheduleConfig
    {
        return ScheduleConfig::monthlyOnDay($dayOfMonth);
    }

    public static function timesPerWeek(int $times): ScheduleConfig
    {
        return ScheduleConfig::timesPerWeek($times);
    }

    public static function once(): ScheduleConfig
    {
        return ScheduleConfig::once();
    }

    public static function random(): ScheduleConfig
    {
        $type = rand(1, 5);
        return match($type) {
            1 => self::daily(),
            2 => self::weeklyOnMonday(),
            3 => self::monthlyOnDay(rand(1, 28)),
            4 => self::timesPerWeek(rand(2, 6)),
            5 => self::once(),
        };
    }
}
