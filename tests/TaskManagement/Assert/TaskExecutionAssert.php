<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Assert;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Event\TaskExecutionCreated;
use App\TaskManagement\Domain\ValueObject\ExecutionStatus;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use PHPUnit\Framework\Assert;

final class TaskExecutionAssert
{
    public static function assertTaskExecutionHasId(Uuid $expectedId, TaskExecution $execution): void
    {
        Assert::assertTrue(
            $expectedId->equals($execution->id()),
            sprintf('Expected task execution ID to be %s, but got %s', $expectedId->value(), $execution->id()->value())
        );
    }

    public static function assertTaskExecutionHasRoutineTaskId(Uuid $expectedRoutineTaskId, TaskExecution $execution): void
    {
        Assert::assertNotNull(
            $execution->templateTaskId(),
            'Expected task execution to have a routine task ID'
        );
        Assert::assertTrue(
            $expectedRoutineTaskId->equals($execution->templateTaskId()),
            sprintf('Expected task execution routine task ID to be %s, but got %s', 
                $expectedRoutineTaskId->value(), 
                $execution->templateTaskId()->value()
            )
        );
    }

    public static function assertTaskExecutionHasTemplateTaskId(Uuid $expectedTemplateTaskId, TaskExecution $execution): void
    {
        Assert::assertNotNull(
            $execution->templateTaskId(),
            'Expected task execution to have a template task ID'
        );
        Assert::assertTrue(
            $expectedTemplateTaskId->equals($execution->templateTaskId()),
            sprintf('Expected task execution template task ID to be %s, but got %s', 
                $expectedTemplateTaskId->value(), 
                $execution->templateTaskId()->value()
            )
        );
    }

    public static function assertTaskExecutionHasNoRoutineTask(TaskExecution $execution): void
    {
        Assert::assertNull(
            $execution->templateTaskId(),
            'Expected task execution to not have a routine task ID'
        );
    }

    public static function assertTaskExecutionHasNoTemplateTask(TaskExecution $execution): void
    {
        Assert::assertNull(
            $execution->templateTaskId(),
            'Expected task execution to not have a template task ID'
        );
    }

    public static function assertTaskExecutionHasName(TaskName $expectedName, TaskExecution $execution): void
    {
        Assert::assertTrue(
            $expectedName->equals($execution->name()),
            sprintf('Expected task execution name to be "%s", but got "%s"', $expectedName->value(), $execution->name()->value())
        );
    }

    public static function assertTaskExecutionHasDescription(string $expectedDescription, TaskExecution $execution): void
    {
        Assert::assertSame(
            $expectedDescription,
            $execution->description(),
            sprintf('Expected task execution description to be "%s", but got "%s"', $expectedDescription, $execution->description())
        );
    }

    public static function assertTaskExecutionHasPoints(Points $expectedPoints, TaskExecution $execution): void
    {
        Assert::assertSame(
            $expectedPoints->value(),
            $execution->points()->value(),
            sprintf('Expected task execution points to be %d, but got %d', $expectedPoints->value(), $execution->points()->value())
        );
    }

    public static function assertTaskExecutionHasScheduledFor(\DateTimeImmutable $expectedDate, TaskExecution $execution): void
    {
        Assert::assertEquals(
            $expectedDate,
            $execution->scheduledFor(),
            'Expected task execution to have matching scheduled date'
        );
    }

    public static function assertTaskExecutionIsPending(TaskExecution $execution): void
    {
        Assert::assertSame(
            ExecutionStatus::PENDING,
            $execution->status(),
            'Expected task execution status to be PENDING'
        );
    }

    public static function assertTaskExecutionIsCompleted(TaskExecution $execution): void
    {
        Assert::assertSame(
            ExecutionStatus::COMPLETED,
            $execution->status(),
            'Expected task execution status to be COMPLETED'
        );
    }

    public static function assertTaskExecutionIsApproved(TaskExecution $execution): void
    {
        Assert::assertSame(
            ExecutionStatus::APPROVED,
            $execution->status(),
            'Expected task execution status to be APPROVED'
        );
    }

    public static function assertTaskExecutionIsRejected(TaskExecution $execution): void
    {
        Assert::assertSame(
            ExecutionStatus::REJECTED,
            $execution->status(),
            'Expected task execution status to be REJECTED'
        );
    }

    public static function assertTaskExecutionIsNotCompleted(TaskExecution $execution): void
    {
        Assert::assertNull(
            $execution->completedAt(),
            'Expected task execution to not have a completion timestamp'
        );
    }

    public static function assertTaskExecutionWasCompletedAt(TaskExecution $execution): void
    {
        Assert::assertNotNull(
            $execution->completedAt(),
            'Expected task execution to have a completion timestamp'
        );
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $execution->completedAt(),
            'Expected completedAt to be a DateTimeImmutable'
        );
    }

    public static function assertTaskExecutionWasApprovedAt(TaskExecution $execution): void
    {
        Assert::assertNotNull(
            $execution->approvedAt(),
            'Expected task execution to have an approval timestamp'
        );
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $execution->approvedAt(),
            'Expected approvedAt to be a DateTimeImmutable'
        );
    }

    public static function assertTaskExecutionIsAssignedTo(Uuid $userId, TaskExecution $execution): void
    {
        Assert::assertNotNull(
            $execution->assignedUserId(),
            'Expected task execution to be assigned to a user'
        );
        Assert::assertTrue(
            $userId->equals($execution->assignedUserId()),
            sprintf('Expected task execution to be assigned to user %s, but was assigned to %s', 
                $userId->value(), 
                $execution->assignedUserId()->value()
            )
        );
    }

    public static function assertTaskExecutionRecordedTaskExecutionCreatedEvent(
        TaskExecution $execution,
        Uuid $executionId,
        \DateTimeImmutable $scheduledFor
    ): void {
        $events = $execution->pullDomainEvents();
        Assert::assertCount(1, $events, 'Expected exactly one domain event');
        
        $event = $events[0];
        Assert::assertInstanceOf(TaskExecutionCreated::class, $event, 'Expected event to be TaskExecutionCreated');
        
        Assert::assertTrue(
            $executionId->equals($event->executionId()),
            'Expected TaskExecutionCreated event to have matching execution ID'
        );
        Assert::assertEquals(
            $scheduledFor,
            $event->scheduledFor(),
            'Expected TaskExecutionCreated event to have matching scheduled date'
        );
    }

    public static function assertTaskExecutionRecordedTaskExecutionCompletedEvent(
        TaskExecution $execution,
        Uuid $executionId,
        Uuid $userId
    ): void {
        $events = $execution->pullDomainEvents();
        $found = false;
        
        foreach ($events as $event) {
            if ($event instanceof TaskExecutionCompleted) {
                $found = true;
                Assert::assertTrue(
                    $executionId->equals($event->executionId()),
                    'Expected TaskExecutionCompleted event to have matching execution ID'
                );
                Assert::assertTrue(
                    $userId->equals($event->userId()),
                    'Expected TaskExecutionCompleted event to have matching user ID'
                );
                break;
            }
        }
        
        Assert::assertTrue($found, 'Expected to find TaskExecutionCompleted event');
    }

    public static function assertTaskExecutionRecordedTaskExecutionApprovedEvent(
        TaskExecution $execution,
        Uuid $executionId,
        Uuid $adminId
    ): void {
        $events = $execution->pullDomainEvents();
        $found = false;
        
        foreach ($events as $event) {
            if ($event instanceof TaskExecutionApproved) {
                $found = true;
                Assert::assertTrue(
                    $executionId->equals($event->executionId()),
                    'Expected TaskExecutionApproved event to have matching execution ID'
                );
                Assert::assertTrue(
                    $adminId->equals($event->adminId()),
                    'Expected TaskExecutionApproved event to have matching admin ID'
                );
                break;
            }
        }
        
        Assert::assertTrue($found, 'Expected to find TaskExecutionApproved event');
    }

    public static function assertTaskExecutionCannotPerformAction(callable $action, string $expectedExceptionMessage): void
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
