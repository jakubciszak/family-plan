<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Allowance\Domain\Entity\Payout;
use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountKind;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class WalletView
{
    public function __construct(
        private MoneyLedger $ledger,
        private PayoutRepositoryInterface $payouts,
        private string $currency
    ) {
    }

    public function of(Uuid $userId): array
    {
        $balances = $this->ledger->balances($userId);
        $putAside = array_reduce(
            $this->ledger->goalBalances($userId),
            static fn (Money $carried, Money $saved) => $carried->plus($saved),
            Money::zero()
        );

        $awaiting = $this->payouts->awaitingConfirmation($userId);

        return [
            'currency' => $this->currency,
            'pending' => $balances[AccountKind::PENDING->value]->minorUnits(),
            'available' => $balances[AccountKind::AVAILABLE->value]->minorUnits(),
            'putAside' => $putAside->minorUnits(),
            'earned' => $balances[AccountKind::EARNINGS->value]->negated()->minorUnits(),
            'otherIncome' => $balances[AccountKind::INCOME->value]->negated()->minorUnits(),
            'spent' => $balances[AccountKind::EXPENSES->value]->minorUnits(),
            'awaitingConfirmation' => array_map(
                static fn (Payout $payout) => [
                    'id' => $payout->id()->value(),
                    'amount' => $payout->amount()->minorUnits(),
                    'note' => $payout->note(),
                    'offeredAt' => $payout->offeredAt()->format('c'),
                ],
                $awaiting
            ),
        ];
    }
}
