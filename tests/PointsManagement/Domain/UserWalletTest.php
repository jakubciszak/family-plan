<?php

declare(strict_types=1);

namespace App\Tests\PointsManagement\Domain;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\ValueObject\PointsBalance;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\Tests\Shared\Mother\UuidMother;
use PHPUnit\Framework\TestCase;

/**
 * Test for UserWallet aggregate (Wallet/Account archetypal pattern)
 */
class UserWalletTest extends TestCase
{
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FixedClock();
    }

    public function testWalletCanBeCreated(): void
    {
        // Given
        $walletId = UuidMother::random();
        $userId = UuidMother::random();

        // When
        $wallet = UserWallet::create($walletId, $userId, $this->clock);

        // Then
        $this->assertEquals($walletId, $wallet->id());
        $this->assertEquals($userId, $wallet->userId());
        $this->assertEquals(0, $wallet->balance()->value());
    }

    public function testWalletCreationRecordsDomainEvent(): void
    {
        // Given
        $walletId = UuidMother::random();
        $userId = UuidMother::random();

        // When
        $wallet = UserWallet::create($walletId, $userId, $this->clock);
        $events = $wallet->pullDomainEvents();

        // Then
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\PointsManagement\Domain\Event\UserWalletCreated::class, $events[0]);
    }

    public function testPointsCanBeAwarded(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);

        // When
        $wallet->awardPoints(50, 'Task completion', $this->clock);

        // Then
        $this->assertEquals(50, $wallet->balance()->value());
    }

    public function testPointsAccumulateWithMultipleAwards(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);

        // When
        $wallet->awardPoints(30, 'First task', $this->clock);
        $wallet->awardPoints(20, 'Second task', $this->clock);
        $wallet->awardPoints(10, 'Third task', $this->clock);

        // Then
        $this->assertEquals(60, $wallet->balance()->value());
    }

    public function testAwardingPointsRecordsDomainEvent(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->pullDomainEvents(); // Clear creation event

        // When
        $wallet->awardPoints(100, 'Task approved', $this->clock);
        $events = $wallet->pullDomainEvents();

        // Then
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\PointsManagement\Domain\Event\PointsAwarded::class, $events[0]);
        $this->assertEquals(100, $events[0]->points);
    }

    public function testCannotAwardZeroPoints(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);

        // Then
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Points to award must be positive');

        // When
        $wallet->awardPoints(0, 'Invalid', $this->clock);
    }

    public function testCannotAwardNegativePoints(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);

        // Then
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Points to award must be positive');

        // When
        $wallet->awardPoints(-10, 'Invalid', $this->clock);
    }

    public function testPointsCanBeDeducted(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->awardPoints(100, 'Initial points', $this->clock);

        // When
        $wallet->deductPoints(30, 'Redemption', $this->clock);

        // Then
        $this->assertEquals(70, $wallet->balance()->value());
    }

    public function testCannotDeductMoreThanBalance(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->awardPoints(50, 'Initial points', $this->clock);

        // Then
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient points balance');

        // When
        $wallet->deductPoints(100, 'Too much', $this->clock);
    }

    public function testPointsBalanceValueObject(): void
    {
        // When
        $balance = PointsBalance::fromInt(100);

        // Then
        $this->assertEquals(100, $balance->value());
    }

    public function testPointsBalanceCanBeAdded(): void
    {
        // Given
        $balance = PointsBalance::fromInt(50);

        // When
        $newBalance = $balance->add(30);

        // Then
        $this->assertEquals(80, $newBalance->value());
        $this->assertEquals(50, $balance->value()); // Original unchanged (immutable)
    }

    public function testPointsBalanceCanBeSubtracted(): void
    {
        // Given
        $balance = PointsBalance::fromInt(100);

        // When
        $newBalance = $balance->subtract(40);

        // Then
        $this->assertEquals(60, $newBalance->value());
        $this->assertEquals(100, $balance->value()); // Original unchanged (immutable)
    }
}
