<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\Entity\Payout;
use App\Allowance\Domain\Event\PayoutOffered;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\PayoutStatus;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class PayoutTest extends TestCase
{
    public function testAnOfferedPayoutWaitsForTheOwnerToConfirmIt(): void
    {
        $payout = $this->offer(2000);

        $this->assertTrue($payout->isAwaitingConfirmation());
        $this->assertNull($payout->settledAt());
    }

    public function testConfirmingRecordsWhichTransactionMovedTheMoney(): void
    {
        $payout = $this->offer(2000);
        $transactionId = Uuid::generate();

        $payout->confirm($transactionId, $this->clock());

        $this->assertSame(PayoutStatus::CONFIRMED, $payout->status());
        $this->assertTrue($transactionId->equals($payout->transactionId()));
    }

    public function testTheSamePayoutCannotBeConfirmedTwice(): void
    {
        $payout = $this->offer(2000);
        $payout->confirm(Uuid::generate(), $this->clock());

        $this->expectException(DomainException::class);

        $payout->confirm(Uuid::generate(), $this->clock());
    }

    public function testACancelledPayoutCannotBeConfirmedAfterwards(): void
    {
        $payout = $this->offer(2000);
        $payout->cancel($this->clock());

        $this->expectException(DomainException::class);

        $payout->confirm(Uuid::generate(), $this->clock());
    }

    public function testNothingCanBePaidOut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->offer(0);
    }

    public function testOfferingAPayoutAnnouncesItSoTheOwnerCanBeTold(): void
    {
        $id = Uuid::generate();
        $userId = Uuid::generate();
        $offeredBy = Uuid::generate();

        $payout = Payout::offer($id, $userId, Money::fromMinorUnits(2500), $offeredBy, null, $this->clock());

        $events = $payout->pullDomainEvents();
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertInstanceOf(PayoutOffered::class, $event);
        $this->assertTrue($userId->equals($event->userId()));
        $this->assertTrue($id->equals($event->payoutId()));
        $this->assertSame(2500, $event->amount());
    }

    public function testTheAnnouncementIsHandedOverOnlyOnce(): void
    {
        $payout = $this->offer(2000);

        $payout->pullDomainEvents();

        $this->assertSame([], $payout->pullDomainEvents());
    }

    public function testConfirmingDoesNotAnnounceAnything(): void
    {
        $payout = $this->offer(2000);
        $payout->pullDomainEvents();

        $payout->confirm(Uuid::generate(), $this->clock());

        $this->assertSame([], $payout->pullDomainEvents());
    }

    private function offer(int $amount): Payout
    {
        return Payout::offer(
            Uuid::generate(),
            Uuid::generate(),
            Money::fromMinorUnits($amount),
            Uuid::generate(),
            null,
            $this->clock()
        );
    }

    private function clock(): ClockInterface
    {
        return new FixedClock(new DateTimeImmutable('2026-09-14 10:00:00'));
    }
}
