<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\Entity\MoneyAccount;
use App\Allowance\Domain\Entity\MoneyTransaction;
use App\Allowance\Domain\ValueObject\AccountKind;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class MoneyTransactionTest extends TestCase
{
    public function testMoneyTakenOffOneAccountLandsOnTheOther(): void
    {
        $userId = Uuid::generate();
        $earnings = $this->account($userId, AccountKind::EARNINGS);
        $pending = $this->account($userId, AccountKind::PENDING);

        $transaction = $this->transaction($userId);
        $transaction->transfer($earnings, $pending, Money::fromMinorUnits(2500), $this->clock());
        $transaction->seal();

        $this->assertSame(-2500, $earnings->balance()->minorUnits());
        $this->assertSame(2500, $pending->balance()->minorUnits());
        $this->assertCount(2, $transaction->entries());
    }

    public function testATransactionWithoutEntriesMovesNothingAndIsRefused(): void
    {
        $this->expectException(DomainException::class);

        $this->transaction(Uuid::generate())->seal();
    }

    public function testMoneyCannotBeMovedOntoTheAccountItCameFrom(): void
    {
        $userId = Uuid::generate();
        $account = $this->account($userId, AccountKind::AVAILABLE);

        $this->expectException(DomainException::class);

        $this->transaction($userId)->transfer($account, $account, Money::fromMinorUnits(100), $this->clock());
    }

    public function testNothingCanBeTransferred(): void
    {
        $userId = Uuid::generate();

        $this->expectException(\InvalidArgumentException::class);

        $this->transaction($userId)->transfer(
            $this->account($userId, AccountKind::EARNINGS),
            $this->account($userId, AccountKind::PENDING),
            Money::zero(),
            $this->clock()
        );
    }

    public function testAnAccountThatHoldsMoneyNeverGoesBelowZero(): void
    {
        $userId = Uuid::generate();

        $this->expectException(DomainException::class);

        $this->transaction($userId)->transfer(
            $this->account($userId, AccountKind::AVAILABLE),
            $this->account($userId, AccountKind::EXPENSES),
            Money::fromMinorUnits(1),
            $this->clock()
        );
    }

    public function testAGoalAccountIsAlwaysKeptForSomething(): void
    {
        $this->expectException(DomainException::class);

        MoneyAccount::open(Uuid::generate(), Uuid::generate(), AccountKind::GOAL, $this->clock());
    }

    private function account(Uuid $userId, AccountKind $kind): MoneyAccount
    {
        return MoneyAccount::open(Uuid::generate(), $userId, $kind, $this->clock());
    }

    private function transaction(Uuid $userId): MoneyTransaction
    {
        return MoneyTransaction::open(
            Uuid::generate(),
            $userId,
            TransactionType::WEEK_CLOSED,
            'Allowance',
            $this->clock()->now()
        );
    }

    private function clock(): ClockInterface
    {
        return new FixedClock(new DateTimeImmutable('2026-09-14 10:00:00'));
    }
}
