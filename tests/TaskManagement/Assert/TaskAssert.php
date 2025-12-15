<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Assert;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Event\TaskApproved;
use App\TaskManagement\Domain\Event\TaskCompleted;
use App\TaskManagement\Domain\Event\TaskCreated;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\TaskStatus;
use PHPUnit\Framework\Assert;

final class TaskAssert
{
    public static function assertTaskHasId(Uuid $expectedId, Task $task): void
    {
        Assert::assertTrue(
            $expectedId->equals($task->id()),
            sprintf('Expected task ID to be %s, but got %s', $expectedId->value(), $task->id()->value())
        );
    }

    public static function assertTaskHasName(TaskName $expectedName, Task $task): void
    {
        Assert::assertTrue(
            $expectedName->equals($task->name()),
            sprintf('Expected task name to be "%s", but got "%s"', $expectedName->value(), $task->name()->value())
        );
    }

    public static function assertTaskHasDescription(string $expectedDescription, Task $task): void
    {
        Assert::assertSame(
            $expectedDescription,
            $task->description(),
            sprintf('Expected task description to be "%s", but got "%s"', $expectedDescription, $task->description())
        );
    }

    public static function assertTaskHasPoints(Points $expectedPoints, Task $task): void
    {
        Assert::assertSame(
            $expectedPoints->value(),
            $task->points()->value(),
            sprintf('Expected task points to be %d, but got %d', $expectedPoints->value(), $task->points()->value())
        );
    }

    public static function assertTaskHasFrequency(Frequency $expectedFrequency, Task $task): void
    {
        Assert::assertSame(
            $expectedFrequency,
            $task->frequency(),
            sprintf('Expected task frequency to be %s, but got %s', $expectedFrequency->value, $task->frequency()->value)
        );
    }

    public static function assertTaskHasStatus(TaskStatus $expectedStatus, Task $task): void
    {
        Assert::assertSame(
            $expectedStatus,
            $task->status(),
            sprintf('Expected task status to be %s, but got %s', $expectedStatus->value, $task->status()->value)
        );
    }

    public static function assertTaskIsPending(Task $task): void
    {
        self::assertTaskHasStatus(TaskStatus::PENDING, $task);
    }

    public static function assertTaskIsCompleted(Task $task): void
    {
        self::assertTaskHasStatus(TaskStatus::COMPLETED, $task);
    }

    public static function assertTaskIsApproved(Task $task): void
    {
        self::assertTaskHasStatus(TaskStatus::APPROVED, $task);
    }

    public static function assertTaskIsRejected(Task $task): void
    {
        self::assertTaskHasStatus(TaskStatus::REJECTED, $task);
    }

    public static function assertTaskIsAssignedTo(Uuid $userId, Task $task): void
    {
        Assert::assertNotNull(
            $task->assignedUserId(),
            'Expected task to be assigned to a user'
        );
        Assert::assertTrue(
            $userId->equals($task->assignedUserId()),
            sprintf('Expected task to be assigned to user %s, but was assigned to %s', 
                $userId->value(), 
                $task->assignedUserId()->value()
            )
        );
    }

    public static function assertTaskIsNotAssigned(Task $task): void
    {
        Assert::assertNull(
            $task->assignedUserId(),
            'Expected task to not be assigned to any user'
        );
    }

    public static function assertTaskWasCompletedAt(Task $task): void
    {
        Assert::assertNotNull(
            $task->completedAt(),
            'Expected task to have a completion timestamp'
        );
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $task->completedAt(),
            'Expected completedAt to be a DateTimeImmutable'
        );
    }

    public static function assertTaskWasNotCompleted(Task $task): void
    {
        Assert::assertNull(
            $task->completedAt(),
            'Expected task to not have a completion timestamp'
        );
    }

    public static function assertTaskWasApprovedAt(Task $task): void
    {
        Assert::assertNotNull(
            $task->approvedAt(),
            'Expected task to have an approval timestamp'
        );
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $task->approvedAt(),
            'Expected approvedAt to be a DateTimeImmutable'
        );
    }

    public static function assertTaskWasNotApproved(Task $task): void
    {
        Assert::assertNull(
            $task->approvedAt(),
            'Expected task to not have an approval timestamp'
        );
    }

    public static function assertTaskHasCreatedAt(Task $task): void
    {
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $task->createdAt(),
            'Expected task to have a createdAt timestamp'
        );
    }

    public static function assertTaskHasUpdatedAt(Task $task): void
    {
        Assert::assertNotNull(
            $task->updatedAt(),
            'Expected task to have an updatedAt timestamp'
        );
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $task->updatedAt(),
            'Expected updatedAt to be a DateTimeImmutable'
        );
    }

    public static function assertTaskRecordedTaskCreatedEvent(
        Task $task,
        Uuid $taskId,
        TaskName $taskName,
        Points $points,
        Frequency $frequency
    ): void {
        $events = $task->pullDomainEvents();
        Assert::assertCount(1, $events, 'Expected exactly one domain event');
        
        $event = $events[0];
        Assert::assertInstanceOf(TaskCreated::class, $event, 'Expected event to be TaskCreated');
        
        Assert::assertTrue(
            $taskId->equals($event->taskId()),
            'Expected TaskCreated event to have matching task ID'
        );
        Assert::assertTrue(
            $taskName->equals($event->taskName()),
            'Expected TaskCreated event to have matching task name'
        );
        Assert::assertSame(
            $points->value(),
            $event->points()->value(),
            'Expected TaskCreated event to have matching points'
        );
        Assert::assertSame(
            $frequency,
            $event->frequency(),
            'Expected TaskCreated event to have matching frequency'
        );
    }

    public static function assertTaskRecordedTaskCompletedEvent(Task $task, Uuid $taskId, Uuid $userId): void
    {
        $events = $task->pullDomainEvents();
        $found = false;
        
        foreach ($events as $event) {
            if ($event instanceof TaskCompleted) {
                $found = true;
                Assert::assertTrue(
                    $taskId->equals($event->taskId()),
                    'Expected TaskCompleted event to have matching task ID'
                );
                Assert::assertTrue(
                    $userId->equals($event->userId()),
                    'Expected TaskCompleted event to have matching user ID'
                );
                break;
            }
        }
        
        Assert::assertTrue($found, 'Expected to find TaskCompleted event');
    }

    public static function assertTaskRecordedTaskApprovedEvent(Task $task, Uuid $taskId, Uuid $adminId): void
    {
        $events = $task->pullDomainEvents();
        $found = false;
        
        foreach ($events as $event) {
            if ($event instanceof TaskApproved) {
                $found = true;
                Assert::assertTrue(
                    $taskId->equals($event->taskId()),
                    'Expected TaskApproved event to have matching task ID'
                );
                Assert::assertTrue(
                    $adminId->equals($event->adminId()),
                    'Expected TaskApproved event to have matching admin ID'
                );
                break;
            }
        }
        
        Assert::assertTrue($found, 'Expected to find TaskApproved event');
    }

    public static function assertTaskHasNoDomainEvents(Task $task): void
    {
        $events = $task->pullDomainEvents();
        Assert::assertCount(
            0,
            $events,
            sprintf('Expected task to have no domain events, but found %d', count($events))
        );
    }

    public static function assertTaskCannotPerformAction(callable $action, string $expectedExceptionMessage): void
    {
        try {
            $action();
            Assert::fail('Expected DomainException to be thrown');
        } catch (\DomainException $e) {
            Assert::assertStringContainsString(
                $expectedExceptionMessage,
                $e->getMessage(),
                sprintf('Expected exception message to contain "%s", but got "%s"', 
                    $expectedExceptionMessage, 
                    $e->getMessage()
                )
            );
        }
    }
}
