<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\SpendGoalCommand;
use App\Allowance\Application\Service\Goals;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SpendGoalHandler
{
    public function __construct(
        private SavingsGoalRepositoryInterface $goals,
        private MoneyLedger $ledger,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(SpendGoalCommand $command): void
    {
        $goal = Goals::open($this->goals, $command->goalId);

        $this->ledger->transfer(
            $goal->userId(),
            AccountRef::goal($goal->id()),
            AccountRef::expenses(),
            Money::fromMinorUnits($command->amount),
            TransactionType::GOAL_SPENDING,
            $command->description,
            $goal->id(),
            null,
            ['goal' => $goal->name()]
        );

        $goal->noteProgress($this->ledger->balance($goal->userId(), AccountRef::goal($goal->id())), $this->clock);

        $this->goals->save($goal);
    }
}
