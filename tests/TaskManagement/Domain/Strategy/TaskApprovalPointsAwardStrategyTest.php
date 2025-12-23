<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Strategy;

use App\TaskManagement\Domain\Strategy\TaskApprovalPointsAwardStrategy;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskMother;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Test for TaskApprovalPointsAwardStrategy
 */
class TaskApprovalPointsAwardStrategyTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private TaskApprovalPointsAwardStrategy $strategy;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->strategy = new TaskApprovalPointsAwardStrategy($this->userRepository);
    }

    public function testPointsAreAwardedToUser(): void
    {
        // Given
        $user = UserMother::regularUser();
        $this->userRepository->save($user);
        
        $taskPoints = PointsMother::fromInt(50);
        $task = TaskMother::aTask()
            ->withPoints($taskPoints)
            ->build();
        
        $initialPoints = $user->points();

        // When
        $this->strategy->awardPoints($task, $user->id());

        // Then
        $updatedUser = $this->userRepository->findById($user->id());
        $this->assertNotNull($updatedUser);
        $this->assertEquals($initialPoints + 50, $updatedUser->points());
    }

    public function testPointsAccumulateWithMultipleAwards(): void
    {
        // Given
        $user = UserMother::regularUser();
        $this->userRepository->save($user);
        
        $task1 = TaskMother::aTask()
            ->withPoints(PointsMother::fromInt(30))
            ->build();
        
        $task2 = TaskMother::aTask()
            ->withPoints(PointsMother::fromInt(20))
            ->build();

        // When
        $this->strategy->awardPoints($task1, $user->id());
        $this->strategy->awardPoints($task2, $user->id());

        // Then
        $updatedUser = $this->userRepository->findById($user->id());
        $this->assertNotNull($updatedUser);
        $this->assertEquals(50, $updatedUser->points());
    }

    public function testExceptionWhenUserNotFound(): void
    {
        // Given
        $nonExistentUserId = UuidMother::random();
        $task = TaskMother::aTask()->build();

        // Then
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/User with ID .* not found/');

        // When
        $this->strategy->awardPoints($task, $nonExistentUserId);
    }
}
