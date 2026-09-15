<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\ConfirmPayoutCommand;
use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConfirmPayoutHandler
{
    public function __construct(
        private PayoutRepositoryInterface $payouts,
        private MoneyLedger $ledger,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(ConfirmPayoutCommand $command): void
    {
        $payout = $this->payouts->find(Uuid::fromString($command->payoutId));

        if ($payout === null || !$payout->userId()->equals(Uuid::fromString($command->userId))) {
            throw new \DomainException('No such payout is waiting for you');
        }

        $transaction = $this->ledger->transfer(
            $payout->userId(),
            AccountRef::pending(),
            AccountRef::available(),
            $payout->amount(),
            TransactionType::PAYOUT,
            $payout->note() ?? '',
            $payout->id()
        );

        $payout->confirm($transaction->id(), $this->clock);

        $this->payouts->save($payout);
    }
}
