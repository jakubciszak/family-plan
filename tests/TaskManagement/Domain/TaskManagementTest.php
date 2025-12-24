<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\TaskManagement\Domain\ValueObject\TaskStatus;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Assert\TaskAssert;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school unit test for Task Management functionality
 * Tests real object interactions using Builder pattern and custom Asserts
 */
class TaskManagementTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidData(): void
    {
        // Given
        $id = UuidMother::random();
        $name = TaskNameMother::create('Clean the kitchen');
        $description = 'Wash dishes and wipe counters';
        $points = PointsMother::medium();
        $frequency = FrequencyMother::daily();

        // When
        $task = TaskMother::aTask()
            ->withId($id)
            ->withName($name)
            ->withDescription($description)
            ->withPoints($points)
            ->withFrequency($frequency)
            ->build();

        // Then
        TaskAssert::assertTaskHasId($id, $task);
        TaskAssert::assertTaskHasName($name, $task);
        TaskAssert::assertTaskHasDescription($description, $task);
        TaskAssert::assertTaskHasPoints($points, $task);
        TaskAssert::assertTaskHasFrequency($frequency, $task);
        TaskAssert::assertTaskIsNew($task);
        TaskAssert::assertTaskIsNotAssigned($task);
        TaskAssert::assertTaskHasCreatedAt($task);
    }

    public function testTaskCreationRecordsDomainEvent(): void
    {
        // Given
        $id = UuidMother::random();
        $name = TaskNameMother::create();
        $points = PointsMother::medium();
        $frequency = FrequencyMother::daily();

        // When
        $task = TaskMother::aTask()
            ->withId($id)
            ->withName($name)
            ->withPoints($points)
            ->withFrequency($frequency)
            ->build();

        // Then
        TaskAssert::assertTaskRecordedTaskCreatedEvent($task, $id, $name, $points, $frequency);
    }

    public function testTaskCanBeAssignedToUser(): void
    {
        // Given
        $userId = UuidMother::random();

        // When
        $task = TaskMother::aTask()
            ->assignedTo($userId)
            ->withoutEvents()
            ->build();

        // Then
        TaskAssert::assertTaskIsAssignedTo($userId, $task);
    }

    public function testPendingTaskCanBeCompleted(): void
    {
        // Given
        $task = TaskMother::pending();
        $userId = UuidMother::random();
        $taskId = $task->id();
        $task->pullDomainEvents(); // Clear creation events

        // When
        $task->markAsCompleted($userId);

        // Then
        TaskAssert::assertTaskIsCompleted($task);
        TaskAssert::assertTaskWasCompletedAt($task);
        TaskAssert::assertTaskRecordedTaskCompletedEvent($task, $taskId, $userId);
    }

    public function testCompletedTaskCanBeApproved(): void
    {
        // Given
        $userId = UuidMother::random();
        $adminId = UuidMother::random();
        $task = TaskMother::aTask()
            ->completed($userId)
            ->withoutEvents()
            ->build();
        $taskId = $task->id();

        // When
        $task->approve($adminId);

        // Then
        TaskAssert::assertTaskIsApproved($task);
        TaskAssert::assertTaskWasApprovedAt($task);
        TaskAssert::assertTaskRecordedTaskApprovedEvent($task, $taskId, $adminId);
    }

    public function testCompletedTaskCanBeRejected(): void
    {
        // Given
        $userId = UuidMother::random();
        $task = TaskMother::aTask()
            ->completed($userId)
            ->withoutEvents()
            ->build();

        // When
        $task->reject();

        // Then
        TaskAssert::assertTaskIsRejected($task);
        TaskAssert::assertTaskHasUpdatedAt($task);
    }

    public function testPendingTaskCannotBeApproved(): void
    {
        // Given
        $task = TaskMother::pending();
        $adminId = UuidMother::random();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->approve($adminId),
            'Only completed tasks can be approved'
        );
    }

    public function testPendingTaskCannotBeRejected(): void
    {
        // Given
        $task = TaskMother::pending();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->reject(),
            'Only completed tasks can be rejected'
        );
    }

    public function testApprovedTaskCannotBeCompleted(): void
    {
        // Given
        $userId = UuidMother::random();
        $adminId = UuidMother::random();
        $task = TaskMother::aTask()
            ->approved($userId, $adminId)
            ->withoutEvents()
            ->build();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->markAsCompleted(UuidMother::random()),
            'Cannot complete an already approved task'
        );
    }

    public function testApprovedTaskCannotBeRejected(): void
    {
        // Given
        $userId = UuidMother::random();
        $adminId = UuidMother::random();
        $task = TaskMother::aTask()
            ->approved($userId, $adminId)
            ->withoutEvents()
            ->build();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->reject(),
            'Cannot reject an approved task'
        );
    }

    public function testRejectedTaskCannotBeCompleted(): void
    {
        // Given
        $userId = UuidMother::random();
        $task = TaskMother::aTask()
            ->rejected($userId)
            ->withoutEvents()
            ->build();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->markAsCompleted(UuidMother::random()),
            'Cannot complete a rejected task'
        );
    }

    public function testCompletedTaskCannotBeCompletedAgain(): void
    {
        // Given
        $userId = UuidMother::random();
        $task = TaskMother::aTask()
            ->completed($userId)
            ->withoutEvents()
            ->build();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->markAsCompleted(UuidMother::random()),
            'Task is already completed'
        );
    }

    public function testDailyTaskHasCorrectAttributes(): void
    {
        // When
        $task = TaskMother::dailyTask();

        // Then
        TaskAssert::assertTaskHasFrequency(FrequencyMother::daily(), $task);
        TaskAssert::assertTaskHasPoints(PointsMother::low(), $task);
    }

    public function testWeeklyTaskHasCorrectAttributes(): void
    {
        // When
        $task = TaskMother::weeklyTask();

        // Then
        TaskAssert::assertTaskHasFrequency(FrequencyMother::weekly(), $task);
        TaskAssert::assertTaskHasPoints(PointsMother::medium(), $task);
    }

    public function testMonthlyTaskHasCorrectAttributes(): void
    {
        // When
        $task = TaskMother::monthlyTask();

        // Then
        TaskAssert::assertTaskHasFrequency(FrequencyMother::monthly(), $task);
        TaskAssert::assertTaskHasPoints(PointsMother::high(), $task);
    }

    public function testHighValueTaskHasCorrectAttributes(): void
    {
        // When
        $task = TaskMother::highValueTask();

        // Then
        TaskAssert::assertTaskHasPoints(PointsMother::high(), $task);
    }

    public function testTaskStateTransitionWorkflow(): void
    {
        // Given - Create a new task
        $task = TaskMother::pending();
        $userId = UuidMother::random();
        $adminId = UuidMother::random();
        
        TaskAssert::assertTaskIsNew($task);
        
        // When - Complete the task
        $task->markAsCompleted($userId);
        
        // Then - Task is completed
        TaskAssert::assertTaskIsCompleted($task);
        TaskAssert::assertTaskWasCompletedAt($task);
        
        // When - Approve the task
        $task->approve($adminId);
        
        // Then - Task is approved (terminal state)
        TaskAssert::assertTaskIsApproved($task);
        TaskAssert::assertTaskWasApprovedAt($task);
    }

    public function testTaskCanBeUpdated(): void
    {
        // Given
        $task = TaskMother::pending();
        $newName = TaskNameMother::create('Updated task name');
        $newDescription = 'Updated description';
        $newPoints = PointsMother::high();
        $newFrequency = FrequencyMother::weekly();

        // When
        $task->update($newName, $newDescription, $newPoints, $newFrequency);

        // Then
        TaskAssert::assertTaskHasName($newName, $task);
        TaskAssert::assertTaskHasDescription($newDescription, $task);
        TaskAssert::assertTaskHasPoints($newPoints, $task);
        TaskAssert::assertTaskHasFrequency($newFrequency, $task);
        TaskAssert::assertTaskHasUpdatedAt($task);
    }

    public function testTaskCanBeAssignedAndReassigned(): void
    {
        // Given
        $task = TaskMother::pending();
        $firstUserId = UuidMother::random();
        $secondUserId = UuidMother::random();

        // When - Assign to first user
        $task->assignTo($firstUserId);
        
        // Then
        TaskAssert::assertTaskIsAssignedTo($firstUserId, $task);

        // When - Reassign to second user
        $task->assignTo($secondUserId);
        
        // Then
        TaskAssert::assertTaskIsAssignedTo($secondUserId, $task);
    }

    public function testBuilderPatternAllowsFluentTaskCreation(): void
    {
        // When
        $task = TaskMother::aTask()
            ->withName(TaskNameMother::create('Complex task'))
            ->withDescription('A complex task with multiple attributes')
            ->withPoints(PointsMother::high())
            ->withFrequency(FrequencyMother::weekly())
            ->assignedTo(UuidMother::random())
            ->build();

        // Then
        TaskAssert::assertTaskHasName(TaskNameMother::create('Complex task'), $task);
        TaskAssert::assertTaskHasPoints(PointsMother::high(), $task);
        TaskAssert::assertTaskHasFrequency(FrequencyMother::weekly(), $task);
        $this->assertNotNull($task->assignedUserId());
    }
}
