<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\CloseGoalCommand;
use App\Allowance\Application\Service\Goals;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CloseGoalHandler
{
    public function __construct(
        private SavingsGoalRepositoryInterface $goals,
        private MoneyLedger $ledger,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(CloseGoalCommand $command): void
    {
        $goal = Goals::open($this->goals, $command->goalId);
        $putAside = $this->ledger->balance($goal->userId(), AccountRef::goal($goal->id()));

        if ($putAside->isPositive()) {
            $this->ledger->transfer(
                $goal->userId(),
                AccountRef::goal($goal->id()),
                AccountRef::available(),
                $putAside,
                TransactionType::GOAL_RELEASE,
                sprintf('%s was given up on', $goal->name()),
                $goal->id()
            );
        }

        $goal->close($this->clock);

        $this->goals->save($goal);
    }
}
