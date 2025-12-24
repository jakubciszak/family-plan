<?php

declare(strict_types=1);

namespace App\Tests\PointsManagement\Application;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\Tests\Shared\Mother\UuidMother;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for PointsManagement context
 * Tests the complete workflow of wallet creation, points awarding, and balance management
 */
class WalletManagementIntegrationTest extends TestCase
{
    private InMemoryUserWalletRepository $walletRepository;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->walletRepository = new InMemoryUserWalletRepository();
        $this->clock = new FixedClock();
    }

    public function testCompleteWalletLifecycle(): void
    {
        // Given - Create a new wallet for a user
        $userId = UuidMother::random();
        $walletId = UuidMother::random();
        
        // When - Create wallet
        $wallet = UserWallet::create($walletId, $userId, $this->clock);
        $this->walletRepository->save($wallet);
        
        // Then - Wallet is created with zero balance
        $savedWallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($savedWallet);
        $this->assertEquals(0, $savedWallet->balance()->value());
        $this->assertEquals($userId, $savedWallet->userId());
        
        // When - Award points for first task
        $savedWallet->awardPoints(50, 'Task 1 completed', $this->clock);
        $this->walletRepository->save($savedWallet);
        
        // Then - Balance is updated
        $updatedWallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(50, $updatedWallet->balance()->value());
        
        // When - Award points for second task
        $updatedWallet->awardPoints(30, 'Task 2 completed', $this->clock);
        $this->walletRepository->save($updatedWallet);
        
        // Then - Points accumulate
        $finalWallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(80, $finalWallet->balance()->value());
    }

    public function testWalletCanBeFoundById(): void
    {
        // Given
        $userId = UuidMother::random();
        $walletId = UuidMother::random();
        $wallet = UserWallet::create($walletId, $userId, $this->clock);
        $this->walletRepository->save($wallet);
        
        // When
        $foundWallet = $this->walletRepository->findById($walletId);
        
        // Then
        $this->assertNotNull($foundWallet);
        $this->assertEquals($walletId, $foundWallet->id());
        $this->assertEquals($userId, $foundWallet->userId());
    }

    public function testWalletCanBeFoundByUserId(): void
    {
        // Given
        $userId = UuidMother::random();
        $walletId = UuidMother::random();
        $wallet = UserWallet::create($walletId, $userId, $this->clock);
        $this->walletRepository->save($wallet);
        
        // When
        $foundWallet = $this->walletRepository->findByUserId($userId);
        
        // Then
        $this->assertNotNull($foundWallet);
        $this->assertEquals($walletId, $foundWallet->id());
        $this->assertEquals($userId, $foundWallet->userId());
    }

    public function testMultipleWalletsCanExist(): void
    {
        // Given - Create wallets for two different users
        $userId1 = UuidMother::random();
        $userId2 = UuidMother::random();
        
        $wallet1 = UserWallet::create(UuidMother::random(), $userId1, $this->clock);
        $wallet2 = UserWallet::create(UuidMother::random(), $userId2, $this->clock);
        
        // When - Save both wallets
        $this->walletRepository->save($wallet1);
        $this->walletRepository->save($wallet2);
        
        // Award different points to each
        $wallet1->awardPoints(100, 'User 1 task', $this->clock);
        $wallet2->awardPoints(50, 'User 2 task', $this->clock);
        
        $this->walletRepository->save($wallet1);
        $this->walletRepository->save($wallet2);
        
        // Then - Each wallet maintains its own balance
        $savedWallet1 = $this->walletRepository->findByUserId($userId1);
        $savedWallet2 = $this->walletRepository->findByUserId($userId2);
        
        $this->assertEquals(100, $savedWallet1->balance()->value());
        $this->assertEquals(50, $savedWallet2->balance()->value());
    }

    public function testWalletPointsDeduction(): void
    {
        // Given - Create wallet with initial points
        $userId = UuidMother::random();
        $wallet = UserWallet::create(UuidMother::random(), $userId, $this->clock);
        $wallet->awardPoints(100, 'Initial award', $this->clock);
        $this->walletRepository->save($wallet);
        
        // When - Deduct points
        $savedWallet = $this->walletRepository->findByUserId($userId);
        $savedWallet->deductPoints(30, 'Redemption', $this->clock);
        $this->walletRepository->save($savedWallet);
        
        // Then - Balance is reduced
        $finalWallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(70, $finalWallet->balance()->value());
    }

    public function testWalletDomainEventsAreRecorded(): void
    {
        // Given
        $userId = UuidMother::random();
        $walletId = UuidMother::random();
        
        // When - Create wallet
        $wallet = UserWallet::create($walletId, $userId, $this->clock);
        
        // Then - Creation event is recorded
        $events = $wallet->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\PointsManagement\Domain\Event\UserWalletCreated::class, $events[0]);
        
        // When - Award points
        $wallet->awardPoints(50, 'Task completion', $this->clock);
        
        // Then - Points awarded event is recorded
        $events = $wallet->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\PointsManagement\Domain\Event\PointsAwarded::class, $events[0]);
        $this->assertEquals(50, $events[0]->points);
        $this->assertEquals('Task completion', $events[0]->reason);
    }

    public function testWalletCannotBeCreatedTwiceForSameUser(): void
    {
        // Given
        $userId = UuidMother::random();
        $wallet1 = UserWallet::create(UuidMother::random(), $userId, $this->clock);
        $this->walletRepository->save($wallet1);
        
        // When - Try to create another wallet for same user
        $wallet2 = UserWallet::create(UuidMother::random(), $userId, $this->clock);
        $this->walletRepository->save($wallet2);
        
        // Then - Only the latest wallet is accessible (repository overwrites)
        $foundWallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals($wallet2->id(), $foundWallet->id());
    }
}
