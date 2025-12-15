<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Assert\TaskExecutionAssert;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\ScheduleConfigMother;
use App\Tests\TaskManagement\Mother\TaskExecutionMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school unit test for Routine Task Management functionality (merged into Task)
 * Tests real object interactions using Builder pattern and custom Asserts
 */
class TaskTemplateManagementTest extends TestCase
{
    public function testTaskExecutionCanBeCreatedFromTemplateTask(): void
    {
        // Given
        $templateTaskId = UuidMother::random();
        $executionId = UuidMother::random();
        $scheduledFor = new \DateTimeImmutable('2025-01-15 10:00:00');

        // When
        $execution = TaskExecutionMother::aTaskExecution()
            ->withId($executionId)
            ->withTemplateTaskId($templateTaskId)
            ->withScheduledFor($scheduledFor)
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionHasId($executionId, $execution);
        TaskExecutionAssert::assertTaskExecutionHasTemplateTaskId($templateTaskId, $execution);
        TaskExecutionAssert::assertTaskExecutionHasScheduledFor($scheduledFor, $execution);
        TaskExecutionAssert::assertTaskExecutionIsPending($execution);
        TaskExecutionAssert::assertTaskExecutionIsNotCompleted($execution);
    }

    public function testOneTimeTaskExecutionCanBeCreatedWithoutTaskTemplate(): void
    {
        // Given
        $executionId = UuidMother::random();
        $name = TaskNameMother::create('One-time task');
        $description = 'A task that happens only once';
        $points = PointsMother::medium();
        $scheduledFor = new \DateTimeImmutable('2025-01-20 14:00:00');

        // When
        $execution = TaskExecutionMother::aTaskExecution()
            ->withId($executionId)
            ->withName($name)
            ->withDescription($description)
            ->withPoints($points)
            ->withScheduledFor($scheduledFor)
            ->withoutTaskTemplate()
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionHasId($executionId, $execution);
        TaskExecutionAssert::assertTaskExecutionHasName($name, $execution);
        TaskExecutionAssert::assertTaskExecutionHasDescription($description, $execution);
        TaskExecutionAssert::assertTaskExecutionHasPoints($points, $execution);
        TaskExecutionAssert::assertTaskExecutionHasNoTemplateTask($execution);
        TaskExecutionAssert::assertTaskExecutionIsPending($execution);
    }

    public function testTaskExecutionCanBeCompleted(): void
    {
        // Given
        $execution = TaskExecutionMother::pending();
        $userId = UuidMother::random();
        $executionId = $execution->id();
        $execution->pullDomainEvents(); // Clear creation events

        // When
        $execution->complete($userId);

        // Then
        TaskExecutionAssert::assertTaskExecutionIsCompleted($execution);
        TaskExecutionAssert::assertTaskExecutionWasCompletedAt($execution);
        TaskExecutionAssert::assertTaskExecutionRecordedTaskExecutionCompletedEvent($execution, $executionId, $userId);
    }

    public function testTaskExecutionCanBeApproved(): void
    {
        // Given
        $userId = UuidMother::random();
        $adminId = UuidMother::random();
        $execution = TaskExecutionMother::aTaskExecution()
            ->completed($userId)
            ->withoutEvents()
            ->build();
        $executionId = $execution->id();

        // When
        $execution->approve($adminId);

        // Then
        TaskExecutionAssert::assertTaskExecutionIsApproved($execution);
        TaskExecutionAssert::assertTaskExecutionWasApprovedAt($execution);
        TaskExecutionAssert::assertTaskExecutionRecordedTaskExecutionApprovedEvent($execution, $executionId, $adminId);
    }

    public function testTaskExecutionCanBeRejected(): void
    {
        // Given
        $userId = UuidMother::random();
        $execution = TaskExecutionMother::aTaskExecution()
            ->completed($userId)
            ->withoutEvents()
            ->build();

        // When
        $execution->reject();

        // Then
        TaskExecutionAssert::assertTaskExecutionIsRejected($execution);
    }

    public function testPendingTaskExecutionCannotBeApproved(): void
    {
        // Given
        $execution = TaskExecutionMother::pending();
        $adminId = UuidMother::random();

        // Then
        TaskExecutionAssert::assertTaskExecutionCannotPerformAction(
            fn() => $execution->approve($adminId),
            'Only completed task executions can be approved'
        );
    }

    public function testApprovedTaskExecutionCannotBeCompleted(): void
    {
        // Given
        $userId = UuidMother::random();
        $adminId = UuidMother::random();
        $execution = TaskExecutionMother::aTaskExecution()
            ->approved($userId, $adminId)
            ->withoutEvents()
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionCannotPerformAction(
            fn() => $execution->complete(UuidMother::random()),
            'Cannot complete an already approved task execution'
        );
    }

    public function testTaskExecutionCanBeAssignedToUser(): void
    {
        // Given
        $userId = UuidMother::random();

        // When
        $execution = TaskExecutionMother::aTaskExecution()
            ->assignedTo($userId)
            ->withoutEvents()
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionIsAssignedTo($userId, $execution);
    }

    public function testTaskExecutionCreationRecordsDomainEvent(): void
    {
        // Given
        $id = UuidMother::random();
        $templateTaskId = UuidMother::random();
        $scheduledFor = new \DateTimeImmutable('2025-01-15');

        // When
        $execution = TaskExecutionMother::aTaskExecution()
            ->withId($id)
            ->withTemplateTaskId($templateTaskId)
            ->withScheduledFor($scheduledFor)
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionRecordedTaskExecutionCreatedEvent($execution, $id, $scheduledFor);
    }
}
