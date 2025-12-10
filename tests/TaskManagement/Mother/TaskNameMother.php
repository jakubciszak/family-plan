<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\TaskManagement\Domain\ValueObject\TaskName;

final class TaskNameMother
{
    public static function create(string $value = 'Clean the kitchen'): TaskName
    {
        return TaskName::fromString($value);
    }

    public static function random(): TaskName
    {
        $names = [
            'Clean the kitchen',
            'Take out trash',
            'Do the laundry',
            'Vacuum the living room',
            'Wash the dishes',
            'Mow the lawn',
            'Water the plants',
            'Clean the bathroom'
        ];

        return TaskName::fromString($names[array_rand($names)]);
    }

    public static function short(): TaskName
    {
        return TaskName::fromString('Task');
    }

    public static function long(): TaskName
    {
        return TaskName::fromString('This is a very long task name that describes the task in great detail');
    }
}
