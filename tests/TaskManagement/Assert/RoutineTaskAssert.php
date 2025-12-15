<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Assert;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\RoutineTask;
use App\TaskManagement\Domain\Event\RoutineTaskCreated;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use PHPUnit\Framework\Assert;

final class RoutineTaskAssert
{
    public static function assertRoutineTaskHasId(Uuid $expectedId, RoutineTask $routineTask): void
    {
        Assert::assertTrue(
            $expectedId->equals($routineTask->id()),
            sprintf('Expected routine task ID to be %s, but got %s', $expectedId->value(), $routineTask->id()->value())
        );
    }

    public static function assertRoutineTaskHasName(TaskName $expectedName, RoutineTask $routineTask): void
    {
        Assert::assertTrue(
            $expectedName->equals($routineTask->name()),
            sprintf('Expected routine task name to be "%s", but got "%s"', $expectedName->value(), $routineTask->name()->value())
        );
    }

    public static function assertRoutineTaskHasDescription(string $expectedDescription, RoutineTask $routineTask): void
    {
        Assert::assertSame(
            $expectedDescription,
            $routineTask->description(),
            sprintf('Expected routine task description to be "%s", but got "%s"', $expectedDescription, $routineTask->description())
        );
    }

    public static function assertRoutineTaskHasPoints(Points $expectedPoints, RoutineTask $routineTask): void
    {
        Assert::assertSame(
            $expectedPoints->value(),
            $routineTask->points()->value(),
            sprintf('Expected routine task points to be %d, but got %d', $expectedPoints->value(), $routineTask->points()->value())
        );
    }

    public static function assertRoutineTaskHasFrequency(Frequency $expectedFrequency, RoutineTask $routineTask): void
    {
        Assert::assertSame(
            $expectedFrequency,
            $routineTask->frequency(),
            sprintf('Expected routine task frequency to be %s, but got %s', $expectedFrequency->value, $routineTask->frequency()->value)
        );
    }

    public static function assertRoutineTaskHasScheduleConfig(ScheduleConfig $expectedConfig, RoutineTask $routineTask): void
    {
        Assert::assertTrue(
            $expectedConfig->equals($routineTask->scheduleConfig()),
            sprintf('Expected routine task schedule config to match')
        );
    }

    public static function assertRoutineTaskIsActive(RoutineTask $routineTask): void
    {
        Assert::assertTrue(
            $routineTask->isActive(),
            'Expected routine task to be active'
        );
    }

    public static function assertRoutineTaskIsInactive(RoutineTask $routineTask): void
    {
        Assert::assertFalse(
            $routineTask->isActive(),
            'Expected routine task to be inactive'
        );
    }

    public static function assertRoutineTaskHasCreatedAt(RoutineTask $routineTask): void
    {
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $routineTask->createdAt(),
            'Expected routine task to have a createdAt timestamp'
        );
    }

    public static function assertRoutineTaskRecordedRoutineTaskCreatedEvent(
        RoutineTask $routineTask,
        Uuid $id,
        TaskName $name
    ): void {
        $events = $routineTask->pullDomainEvents();
        Assert::assertCount(1, $events, 'Expected exactly one domain event');
        
        $event = $events[0];
        Assert::assertInstanceOf(RoutineTaskCreated::class, $event, 'Expected event to be RoutineTaskCreated');
        
        Assert::assertTrue(
            $id->equals($event->routineTaskId()),
            'Expected RoutineTaskCreated event to have matching routine task ID'
        );
        Assert::assertTrue(
            $name->equals($event->taskName()),
            'Expected RoutineTaskCreated event to have matching task name'
        );
    }
}
