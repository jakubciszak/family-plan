<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\Entity\MoneyAccount;
use App\Allowance\Domain\Entity\MoneyTransaction;
use App\Allowance\Domain\ValueObject\AccountKind;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HouseholdMoneyTransactionTest extends TestCase
{
    public function testTransferRejectsAccountsInDifferentHomesBeforePosting(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-09-28'));
        $user = Uuid::generate();
        $a = Uuid::generate();
        $b = Uuid::generate();
        $from = MoneyAccount::open(Uuid::generate(), $user, AccountKind::INCOME, $clock, teamId: $a);
        $to = MoneyAccount::open(Uuid::generate(), $user, AccountKind::AVAILABLE, $clock, teamId: $b);
        $transfer = MoneyTransaction::open(Uuid::generate(), $user, TransactionType::INCOME, 'Gift', $clock->now(), teamId: $a);
        try {
            $transfer->transfer($from, $to, Money::fromMinorUnits(100), $clock);
            $this->fail('Cross-household transfer must be rejected');
        } catch (\DomainException $exception) {
            $this->assertSame(0, $from->balance()->minorUnits());
            $this->assertSame(0, $to->balance()->minorUnits());
            $this->assertSame([], $transfer->entries());
        }
    }
}
