<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Strategy;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Domain\Strategy\TaskApprovalPointsAwardStrategy;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskMother;
use PHPUnit\Framework\TestCase;

/**
 * Test for TaskApprovalPointsAwardStrategy with UserWallet aggregate
 */
class TaskApprovalPointsAwardStrategyTest extends TestCase
{
    private InMemoryUserWalletRepository $walletRepository;
    private FixedClock $clock;
    private TaskApprovalPointsAwardStrategy $strategy;

    protected function setUp(): void
    {
        $this->walletRepository = new InMemoryUserWalletRepository();
        $this->clock = new FixedClock();
        $this->strategy = new TaskApprovalPointsAwardStrategy($this->walletRepository, $this->clock);
    }

    public function testPointsAreAwardedToUserWallet(): void
    {
        // Given
        $userId = UuidMother::random();
        $wallet = UserWallet::create(UuidMother::random(), $userId, $this->clock);
        $this->walletRepository->save($wallet);
        
        $taskPoints = PointsMother::fromInt(50);
        $task = TaskMother::aTask()
            ->withPoints($taskPoints)
            ->build();
        
        $initialBalance = $wallet->balance()->value();

        // When
        $this->strategy->awardPoints($task, $userId);

        // Then
        $updatedWallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($updatedWallet);
        $this->assertEquals($initialBalance + 50, $updatedWallet->balance()->value());
    }

    public function testPointsAccumulateWithMultipleAwards(): void
    {
        // Given
        $userId = UuidMother::random();
        $wallet = UserWallet::create(UuidMother::random(), $userId, $this->clock);
        $this->walletRepository->save($wallet);
        
        $task1 = TaskMother::aTask()
            ->withPoints(PointsMother::fromInt(30))
            ->build();
        
        $task2 = TaskMother::aTask()
            ->withPoints(PointsMother::fromInt(20))
            ->build();

        // When
        $this->strategy->awardPoints($task1, $userId);
        $this->strategy->awardPoints($task2, $userId);

        // Then
        $updatedWallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($updatedWallet);
        $this->assertEquals(50, $updatedWallet->balance()->value());
    }

    public function testWalletIsCreatedIfNotExists(): void
    {
        // Given
        $userId = UuidMother::random();
        $task = TaskMother::aTask()
            ->withPoints(PointsMother::fromInt(100))
            ->build();

        // When
        $this->strategy->awardPoints($task, $userId);

        // Then
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($wallet);
        $this->assertEquals(100, $wallet->balance()->value());
    }
}
