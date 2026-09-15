<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Allowance\Domain\Entity\MoneyEntry;
use App\Allowance\Domain\Entity\MoneyTransaction;
use App\Allowance\Domain\Repository\MoneyAccountRepositoryInterface;
use App\Allowance\Domain\Repository\MoneyEntryRepositoryInterface;
use App\Allowance\Domain\Repository\MoneyTransactionRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * The history as the owner reads it: what happened, when, and which way the money went.
 */
final readonly class LedgerView
{
    public function __construct(
        private MoneyTransactionRepositoryInterface $transactions,
        private MoneyEntryRepositoryInterface $entries,
        private MoneyAccountRepositoryInterface $accounts,
        private string $currency
    ) {
    }

    public function of(Uuid $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, int $limit = 100): array
    {
        $transactions = $this->transactions->ofUser($userId, $from, $to, $limit);

        $kinds = [];
        foreach ($this->accounts->ofUser($userId) as $account) {
            $kinds[$account->id()->value()] = $account;
        }

        $byTransaction = [];
        foreach ($this->entries->ofTransactions(array_map(
            static fn (MoneyTransaction $transaction) => $transaction->id()->value(),
            $transactions
        )) as $entry) {
            $byTransaction[$entry->transactionId()->value()][] = $entry;
        }

        $bookings = [];

        foreach ($transactions as $transaction) {
            $booked = $byTransaction[$transaction->id()->value()] ?? [];

            $bookings[] = [
                'id' => $transaction->id()->value(),
                'type' => $transaction->type()->value,
                'description' => $transaction->description(),
                'bookedAt' => $transaction->bookedAt()->format('c'),
                'amount' => $this->moved($booked),
                'reference' => $transaction->reference(),
                'context' => $transaction->context(),
                'entries' => array_map(
                    static function (MoneyEntry $entry) use ($kinds): array {
                        $account = $kinds[$entry->accountId()->value()] ?? null;

                        return [
                            'account' => $account?->kind()->value,
                            'goalId' => $account?->reference()?->value(),
                            'amount' => $entry->amount()->minorUnits(),
                        ];
                    },
                    $booked
                ),
            ];
        }

        return [
            'currency' => $this->currency,
            'bookings' => $bookings,
        ];
    }

    /**
     * @param MoneyEntry[] $entries
     */
    private function moved(array $entries): int
    {
        $moved = 0;

        foreach ($entries as $entry) {
            if ($entry->amount()->isPositive()) {
                $moved += $entry->amount()->minorUnits();
            }
        }

        return $moved;
    }
}
