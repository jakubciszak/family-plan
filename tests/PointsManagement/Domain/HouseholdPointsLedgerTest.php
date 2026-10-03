<?php

declare(strict_types=1);

namespace App\Tests\PointsManagement\Domain;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\PointsManagement\Infrastructure\Persistence\InMemoryAccountRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryEntryRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HouseholdPointsLedgerTest extends TestCase
{
    public function testBalancesHistoryIdempotencyAndReversalsStayInTheOwningHome(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-09-28 12:00:00'));
        $accounts = new InMemoryAccountRepository();
        $wallets = new InMemoryUserWalletRepository();
        $ledger = new PointsLedger($accounts, new InMemoryEntryRepository($accounts), $wallets, $clock);
        $user = Uuid::generate();
        $a = Uuid::generate();
        $b = Uuid::generate();
        $reference = Uuid::generate();
        foreach ([[$a, 10], [$b, 30], [$a, 10]] as [$team, $amount]) {
            $ledger->post($user, AccountKind::BONUSES, $amount, EntrySource::BONUS_RULE, 'Bonus', $reference, 'week', teamId: $team);
        }
        $this->assertSame(10, $wallets->findByUserId($user, $a)->balance()->value());
        $this->assertSame(30, $wallets->findByUserId($user, $b)->balance()->value());
        $from = new DateTimeImmutable('2026-09-28');
        $to = $from->modify('+1 day');
        $entry = $ledger->between($user, $from, $to, teamId: $a)[0];
        $this->assertFalse($ledger->takeBackBonus($user, $entry->id(), $b));
        $this->assertTrue($ledger->takeBackBonus($user, $entry->id(), $a));
        $this->assertTrue($ledger->takeBackBonus($user, $entry->id(), $a));
        $this->assertSame(0, $ledger->sumBetween($user, $from, $to, teamId: $a));
        $this->assertSame(['2026-09-28' => 30], $ledger->perDayBetween($user, $from, $to, teamId: $b));
        $this->assertSame(30, $wallets->findByUserId($user, $b)->balance()->value());
    }
}
