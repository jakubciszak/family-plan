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
 * Test for NEW status functionality
 */
class TaskNewStatusTest extends TestCase
{
    public function testTaskIsCreatedWithNewStatus(): void
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
        $this->assertEquals(TaskStatus::NEW, $task->status());
        $this->assertTrue($task->status()->isNew());
    }

    public function testNewTaskCanBeCompleted(): void
    {
        // Given
        $task = TaskMother::aTask()->build();
        $userId = UuidMother::random();
        $task->pullDomainEvents(); // Clear creation events

        // When
        $task->markAsCompleted($userId);

        // Then
        TaskAssert::assertTaskIsCompleted($task);
        TaskAssert::assertTaskWasCompletedAt($task);
    }

    public function testNewTaskCannotBeApproved(): void
    {
        // Given
        $task = TaskMother::aTask()->build();
        $adminId = UuidMother::random();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->approve($adminId),
            'Only completed tasks can be approved'
        );
    }

    public function testNewTaskCannotBeRejected(): void
    {
        // Given
        $task = TaskMother::aTask()->build();

        // Then
        TaskAssert::assertTaskCannotPerformAction(
            fn() => $task->reject(),
            'Only completed tasks can be rejected'
        );
    }
}
