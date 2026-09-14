<?php

declare(strict_types=1);

namespace App\Tests\PointsManagement\Domain;

use App\PointsManagement\Domain\Entity\Entry;
use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\ValueObject\EntrySource;
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
        $wallet->summarise(50, $this->clock);

        // Then
        $this->assertEquals(50, $wallet->balance()->value());
    }

    public function testPointsAccumulateWithMultipleAwards(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);

        // When
        $wallet->summarise(30, $this->clock);
        $wallet->summarise(50, $this->clock);
        $wallet->summarise(60, $this->clock);

        // Then
        $this->assertEquals(60, $wallet->balance()->value());
    }

    public function testAwardingPointsRecordsDomainEvent(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->pullDomainEvents(); // Clear creation event

        // When
        $wallet->summarise(100, $this->clock);
        $events = $wallet->pullDomainEvents();

        // Then
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\PointsManagement\Domain\Event\PointsAwarded::class, $events[0]);
        $this->assertEquals(100, $events[0]->points);
    }

    public function testAnEntryCannotBeForZeroPoints(): void
    {
        // Then
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('An entry cannot be for zero points');

        // When
        Entry::record(
            UuidMother::random(),
            UuidMother::random(),
            0,
            EntrySource::ADJUSTMENT,
            'Invalid',
            $this->clock->now()
        );
    }

    public function testRestatingTheSameTotalRecordsNothing(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->summarise(40, $this->clock);
        $wallet->pullDomainEvents();

        // When
        $wallet->summarise(40, $this->clock);

        // Then
        $this->assertSame([], $wallet->pullDomainEvents());
    }

    public function testPointsCanBeDeducted(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->summarise(100, $this->clock);

        // When
        $wallet->summarise(70, $this->clock);

        // Then
        $this->assertEquals(70, $wallet->balance()->value());
    }

    public function testSummaryFollowsTheLedgerDownAsWellAsUp(): void
    {
        // Given
        $wallet = UserWallet::create(UuidMother::random(), UuidMother::random(), $this->clock);
        $wallet->summarise(50, $this->clock);

        // When
        $wallet->summarise(20, $this->clock);

        // Then
        $this->assertEquals(20, $wallet->balance()->value());
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
