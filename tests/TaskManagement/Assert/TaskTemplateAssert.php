<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Assert;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Event\TaskTemplateCreated;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use PHPUnit\Framework\Assert;

final class TaskTemplateAssert
{
    public static function assertTaskTemplateHasId(Uuid $expectedId, TaskTemplate $taskTemplate): void
    {
        Assert::assertTrue(
            $expectedId->equals($taskTemplate->id()),
            sprintf('Expected task template ID to be %s, but got %s', $expectedId->value(), $taskTemplate->id()->value())
        );
    }

    public static function assertTaskTemplateHasName(TaskName $expectedName, TaskTemplate $taskTemplate): void
    {
        Assert::assertTrue(
            $expectedName->equals($taskTemplate->name()),
            sprintf('Expected task template name to be "%s", but got "%s"', $expectedName->value(), $taskTemplate->name()->value())
        );
    }

    public static function assertTaskTemplateHasDescription(string $expectedDescription, TaskTemplate $taskTemplate): void
    {
        Assert::assertSame(
            $expectedDescription,
            $taskTemplate->description(),
            sprintf('Expected task template description to be "%s", but got "%s"', $expectedDescription, $taskTemplate->description())
        );
    }

    public static function assertTaskTemplateHasPoints(Points $expectedPoints, TaskTemplate $taskTemplate): void
    {
        Assert::assertSame(
            $expectedPoints->value(),
            $taskTemplate->points()->value(),
            sprintf('Expected task template points to be %d, but got %d', $expectedPoints->value(), $taskTemplate->points()->value())
        );
    }

    public static function assertTaskTemplateHasFrequency(Frequency $expectedFrequency, TaskTemplate $taskTemplate): void
    {
        Assert::assertSame(
            $expectedFrequency,
            $taskTemplate->frequency(),
            sprintf('Expected task template frequency to be %s, but got %s', $expectedFrequency->value, $taskTemplate->frequency()->value)
        );
    }

    public static function assertTaskTemplateHasScheduleConfig(ScheduleConfig $expectedConfig, TaskTemplate $taskTemplate): void
    {
        Assert::assertTrue(
            $expectedConfig->equals($taskTemplate->scheduleConfig()),
            sprintf('Expected task template schedule config to match')
        );
    }

    public static function assertTaskTemplateIsActive(TaskTemplate $taskTemplate): void
    {
        Assert::assertTrue(
            $taskTemplate->isActive(),
            'Expected task template to be active'
        );
    }

    public static function assertTaskTemplateIsInactive(TaskTemplate $taskTemplate): void
    {
        Assert::assertFalse(
            $taskTemplate->isActive(),
            'Expected task template to be inactive'
        );
    }

    public static function assertTaskTemplateHasCreatedAt(TaskTemplate $taskTemplate): void
    {
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $taskTemplate->createdAt(),
            'Expected task template to have a createdAt timestamp'
        );
    }

    public static function assertTaskTemplateRecordedTaskTemplateCreatedEvent(
        TaskTemplate $taskTemplate,
        Uuid $id,
        TaskName $name
    ): void {
        $events = $taskTemplate->pullDomainEvents();
        Assert::assertCount(1, $events, 'Expected exactly one domain event');
        
        $event = $events[0];
        Assert::assertInstanceOf(TaskTemplateCreated::class, $event, 'Expected event to be TaskTemplateCreated');
        
        Assert::assertTrue(
            $id->equals($event->taskTemplateId()),
            'Expected TaskTemplateCreated event to have matching task template ID'
        );
        Assert::assertTrue(
            $name->equals($event->name()),
            'Expected TaskTemplateCreated event to have matching task name'
        );
    }
}
