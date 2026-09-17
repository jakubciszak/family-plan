<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Service;

use App\PointsManagement\Domain\Entity\Account;
use App\PointsManagement\Domain\Entity\Entry;
use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\Repository\AccountRepositoryInterface;
use App\PointsManagement\Domain\Repository\EntryRepositoryInterface;
use App\PointsManagement\Domain\Repository\UserWalletRepositoryInterface;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class PointsLedger
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private EntryRepositoryInterface $entries,
        private UserWalletRepositoryInterface $wallets,
        private ClockInterface $clock
    ) {
    }

    public function post(
        Uuid $userId,
        AccountKind $kind,
        int $amount,
        EntrySource $source,
        string $description,
        ?Uuid $reference = null,
        ?string $periodKey = null,
        ?DateTimeImmutable $bookedAt = null
    ): void {
        $account = $this->accountFor($userId, $kind);

        if ($reference !== null && $periodKey !== null
            && $this->entries->existsFor($account->id(), $reference, $periodKey)) {
            return;
        }

        $entry = Entry::record(
            Uuid::generate(),
            $account->id(),
            $amount,
            $source,
            $description,
            $bookedAt ?? $this->clock->now(),
            $reference,
            $periodKey
        );

        $account->post($entry, $this->clock);

        $this->entries->save($entry);
        $this->accounts->save($account);

        $this->refreshSummary($userId);
    }

    /**
     * Books the opposite of a bonus entry, on the day that bonus was booked.
     * A bonus can be taken back only once; the rule that paid it will not pay it again.
     */
    public function takeBackBonus(Uuid $userId, Uuid $entryId): bool
    {
        $entry = $this->entries->find($entryId);
        $account = $this->accountFor($userId, AccountKind::BONUSES);

        if ($entry === null || !$entry->belongsTo($account->id())) {
            return false;
        }

        $this->post(
            $userId,
            AccountKind::BONUSES,
            -$entry->amount(),
            EntrySource::ADJUSTMENT,
            sprintf('Taken back: %s', $entry->description()),
            $entry->id(),
            'taken-back',
            $entry->bookedAt()
        );

        return true;
    }

    /**
     * @param AccountKind[] $kinds
     */
    public function sumBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds = []): int
    {
        return $this->entries->sumBetween($userId, $from, $to, $kinds ?: AccountKind::all());
    }

    /**
     * @param AccountKind[] $kinds
     * @return array<string, int>
     */
    public function perDayBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds = []): array
    {
        return $this->entries->perDayBetween($userId, $from, $to, $kinds ?: AccountKind::all());
    }

    /**
     * @param AccountKind[] $kinds
     * @return Entry[]
     */
    public function between(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds = []): array
    {
        return $this->entries->between($userId, $from, $to, $kinds ?: AccountKind::all());
    }

    /**
     * @return array<string, int> balance per account kind
     */
    public function balances(Uuid $userId): array
    {
        $balances = [];

        foreach (AccountKind::all() as $kind) {
            $balances[$kind->value] = 0;
        }

        foreach ($this->accounts->ofUser($userId) as $account) {
            $balances[$account->kind()->value] = $account->balance()->value();
        }

        return $balances;
    }

    private function accountFor(Uuid $userId, AccountKind $kind): Account
    {
        $account = $this->accounts->find($userId, $kind);

        if ($account === null) {
            $account = Account::open(Uuid::generate(), $userId, $kind, $this->clock);
            $this->accounts->save($account);
        }

        return $account;
    }

    /**
     * The wallet is the summary account: its balance is the sum of the detail accounts.
     */
    private function refreshSummary(Uuid $userId): void
    {
        $total = array_sum($this->balances($userId));

        $wallet = $this->wallets->findByUserId($userId);

        if ($wallet === null) {
            $wallet = UserWallet::create(Uuid::generate(), $userId, $this->clock);
        }

        $wallet->summarise($total, $this->clock);

        $this->wallets->save($wallet);
    }
}
