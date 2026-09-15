<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\AdjustGoalCommand;
use App\Allowance\Application\Service\BookingDay;
use App\Allowance\Application\Service\Goals;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AdjustGoalHandler
{
    public function __construct(
        private SavingsGoalRepositoryInterface $goals,
        private MoneyLedger $ledger,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(AdjustGoalCommand $command): void
    {
        $goal = Goals::open($this->goals, $command->goalId);

        $goal->adjust($command->name, Money::fromMinorUnits($command->target), BookingDay::from($command->wantedBy));
        $goal->noteProgress($this->ledger->balance($goal->userId(), AccountRef::goal($goal->id())), $this->clock);

        $this->goals->save($goal);
    }
}
