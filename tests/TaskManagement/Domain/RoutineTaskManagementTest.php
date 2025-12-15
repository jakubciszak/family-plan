<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Assert\RoutineTaskAssert;
use App\Tests\TaskManagement\Assert\TaskExecutionAssert;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\RoutineTaskMother;
use App\Tests\TaskManagement\Mother\ScheduleConfigMother;
use App\Tests\TaskManagement\Mother\TaskExecutionMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school unit test for Routine Task Management functionality
 * Tests real object interactions using Builder pattern and custom Asserts
 */
class RoutineTaskManagementTest extends TestCase
{
    public function testRoutineTaskCanBeCreatedWithValidData(): void
    {
        // Given
        $id = UuidMother::random();
        $name = TaskNameMother::create('Clean the kitchen');
        $description = 'Wash dishes and wipe counters';
        $points = PointsMother::medium();
        $frequency = FrequencyMother::daily();
        $scheduleConfig = ScheduleConfigMother::daily();

        // When
        $routineTask = RoutineTaskMother::aRoutineTask()
            ->withId($id)
            ->withName($name)
            ->withDescription($description)
            ->withPoints($points)
            ->withFrequency($frequency)
            ->withScheduleConfig($scheduleConfig)
            ->build();

        // Then
        RoutineTaskAssert::assertRoutineTaskHasId($id, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasName($name, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasDescription($description, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasPoints($points, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasFrequency($frequency, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasScheduleConfig($scheduleConfig, $routineTask);
        RoutineTaskAssert::assertRoutineTaskIsActive($routineTask);
        RoutineTaskAssert::assertRoutineTaskHasCreatedAt($routineTask);
    }

    public function testRoutineTaskCreationRecordsDomainEvent(): void
    {
        // Given
        $id = UuidMother::random();
        $name = TaskNameMother::create('Weekly cleaning');

        // When
        $routineTask = RoutineTaskMother::aRoutineTask()
            ->withId($id)
            ->withName($name)
            ->build();

        // Then
        RoutineTaskAssert::assertRoutineTaskRecordedRoutineTaskCreatedEvent($routineTask, $id, $name);
    }

    public function testWeeklyRoutineTaskCanBeConfiguredForSpecificDay(): void
    {
        // Given
        $scheduleConfig = ScheduleConfigMother::weeklyOnMonday();

        // When
        $routineTask = RoutineTaskMother::aRoutineTask()
            ->withFrequency(FrequencyMother::weekly())
            ->withScheduleConfig($scheduleConfig)
            ->build();

        // Then
        RoutineTaskAssert::assertRoutineTaskHasScheduleConfig($scheduleConfig, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasFrequency(FrequencyMother::weekly(), $routineTask);
    }

    public function testMonthlyRoutineTaskCanBeConfiguredForSpecificDayOfMonth(): void
    {
        // Given
        $scheduleConfig = ScheduleConfigMother::monthlyOnDay(15);

        // When
        $routineTask = RoutineTaskMother::aRoutineTask()
            ->withFrequency(FrequencyMother::monthly())
            ->withScheduleConfig($scheduleConfig)
            ->build();

        // Then
        RoutineTaskAssert::assertRoutineTaskHasScheduleConfig($scheduleConfig, $routineTask);
        RoutineTaskAssert::assertRoutineTaskHasFrequency(FrequencyMother::monthly(), $routineTask);
    }

    public function testRoutineTaskCanBeDeactivated(): void
    {
        // Given
        $routineTask = RoutineTaskMother::active();

        // When
        $routineTask->deactivate();

        // Then
        RoutineTaskAssert::assertRoutineTaskIsInactive($routineTask);
    }

    public function testRoutineTaskCanBeReactivated(): void
    {
        // Given
        $routineTask = RoutineTaskMother::inactive();

        // When
        $routineTask->activate();

        // Then
        RoutineTaskAssert::assertRoutineTaskIsActive($routineTask);
    }

    public function testTaskExecutionCanBeCreatedFromRoutineTask(): void
    {
        // Given
        $routineTaskId = UuidMother::random();
        $executionId = UuidMother::random();
        $scheduledFor = new \DateTimeImmutable('2025-01-15 10:00:00');

        // When
        $execution = TaskExecutionMother::aTaskExecution()
            ->withId($executionId)
            ->withRoutineTaskId($routineTaskId)
            ->withScheduledFor($scheduledFor)
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionHasId($executionId, $execution);
        TaskExecutionAssert::assertTaskExecutionHasRoutineTaskId($routineTaskId, $execution);
        TaskExecutionAssert::assertTaskExecutionHasScheduledFor($scheduledFor, $execution);
        TaskExecutionAssert::assertTaskExecutionIsPending($execution);
        TaskExecutionAssert::assertTaskExecutionIsNotCompleted($execution);
    }

    public function testOneTimeTaskExecutionCanBeCreatedWithoutRoutineTask(): void
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
            ->withoutRoutineTask()
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionHasId($executionId, $execution);
        TaskExecutionAssert::assertTaskExecutionHasName($name, $execution);
        TaskExecutionAssert::assertTaskExecutionHasDescription($description, $execution);
        TaskExecutionAssert::assertTaskExecutionHasPoints($points, $execution);
        TaskExecutionAssert::assertTaskExecutionHasNoRoutineTask($execution);
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
        $routineTaskId = UuidMother::random();
        $scheduledFor = new \DateTimeImmutable('2025-01-15');

        // When
        $execution = TaskExecutionMother::aTaskExecution()
            ->withId($id)
            ->withRoutineTaskId($routineTaskId)
            ->withScheduledFor($scheduledFor)
            ->build();

        // Then
        TaskExecutionAssert::assertTaskExecutionRecordedTaskExecutionCreatedEvent($execution, $id, $scheduledFor);
    }
}
