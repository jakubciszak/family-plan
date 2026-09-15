<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Service;

use App\Allowance\Domain\Entity\MoneyAccount;
use App\Allowance\Domain\Entity\MoneyTransaction;
use App\Allowance\Domain\Repository\MoneyAccountRepositoryInterface;
use App\Allowance\Domain\Repository\MoneyEntryRepositoryInterface;
use App\Allowance\Domain\Repository\MoneyTransactionRepositoryInterface;
use App\Allowance\Domain\ValueObject\AccountKind;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class MoneyLedger
{
    public function __construct(
        private MoneyAccountRepositoryInterface $accounts,
        private MoneyEntryRepositoryInterface $entries,
        private MoneyTransactionRepositoryInterface $transactions,
        private ClockInterface $clock
    ) {
    }

    public function transfer(
        Uuid $userId,
        AccountRef $from,
        AccountRef $to,
        Money $amount,
        TransactionType $type,
        string $description,
        ?Uuid $reference = null,
        ?DateTimeImmutable $on = null
    ): MoneyTransaction {
        $source = $this->accountFor($userId, $from);
        $destination = $this->accountFor($userId, $to);

        $transaction = MoneyTransaction::open(
            Uuid::generate(),
            $userId,
            $type,
            $description,
            $on ?? $this->clock->now(),
            $reference
        );

        $transaction->transfer($source, $destination, $amount, $this->clock);
        $transaction->seal();

        $this->transactions->save($transaction);

        foreach ($transaction->entries() as $entry) {
            $this->entries->save($entry);
        }

        $this->accounts->save($source);
        $this->accounts->save($destination);

        return $transaction;
    }

    public function accountFor(Uuid $userId, AccountRef $ref): MoneyAccount
    {
        $account = $this->accounts->find($userId, $ref->kind(), $ref->reference());

        if ($account === null) {
            $account = MoneyAccount::open(Uuid::generate(), $userId, $ref->kind(), $this->clock, $ref->reference());
            $this->accounts->save($account);
        }

        return $account;
    }

    public function balance(Uuid $userId, AccountRef $ref): Money
    {
        return $this->accounts->find($userId, $ref->kind(), $ref->reference())?->balance() ?? Money::zero();
    }

    /**
     * @return array<string, Money> balance per account kind, goals left out
     */
    public function balances(Uuid $userId): array
    {
        $balances = [];

        foreach (AccountKind::cases() as $kind) {
            if ($kind !== AccountKind::GOAL) {
                $balances[$kind->value] = Money::zero();
            }
        }

        foreach ($this->accounts->ofUser($userId) as $account) {
            if ($account->kind() === AccountKind::GOAL) {
                continue;
            }

            $balances[$account->kind()->value] = $account->balance();
        }

        return $balances;
    }

    /**
     * @return array<string, Money> balance per goal id
     */
    public function goalBalances(Uuid $userId): array
    {
        $balances = [];

        foreach ($this->accounts->ofUser($userId) as $account) {
            if ($account->kind() === AccountKind::GOAL && $account->reference() !== null) {
                $balances[$account->reference()->value()] = $account->balance();
            }
        }

        return $balances;
    }
}
