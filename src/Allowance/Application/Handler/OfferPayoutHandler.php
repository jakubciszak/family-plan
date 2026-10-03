<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\OfferPayoutCommand;
use App\Allowance\Domain\Entity\Payout;
use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OfferPayoutHandler
{
    public function __construct(
        private PayoutRepositoryInterface $payouts,
        private MoneyLedger $ledger,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(OfferPayoutCommand $command): void
    {
        $teamId = $command->teamId === null ? null : \App\Shared\Domain\ValueObject\Uuid::fromString($command->teamId);
        $userId = Uuid::fromString($command->userId);
        $amount = Money::fromMinorUnits($command->amount);

        if ($amount->isGreaterThan($this->stillFree($userId, $teamId))) {
            throw new \DomainException('There is less waiting to be paid out than that');
        }

        $this->payouts->save(Payout::offer(
            Uuid::fromString($command->id),
            $userId,
            $amount,
            Uuid::fromString($command->offeredBy),
            $command->note,
            $this->clock, $teamId
        ));
    }

    private function stillFree(Uuid $userId, ?Uuid $teamId = null): Money
    {
        $free = $this->ledger->balance($userId, AccountRef::pending(), teamId: $teamId);

        foreach ($this->payouts->awaitingConfirmation($userId, $teamId) as $awaiting) {
            $free = $free->minus($awaiting->amount());
        }

        return $free;
    }
}
