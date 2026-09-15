<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\CloseWeekCommand;
use App\Allowance\Application\Service\WeeklyPoints;
use App\Allowance\Domain\Entity\WeekClosure;
use App\Allowance\Domain\Repository\AllowanceRuleRepositoryInterface;
use App\Allowance\Domain\Repository\WeekClosureRepositoryInterface;
use App\Allowance\Domain\Service\AllowanceCalculator;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CloseWeekHandler
{
    public function __construct(
        private WeekClosureRepositoryInterface $closures,
        private AllowanceRuleRepositoryInterface $rules,
        private AllowanceCalculator $calculator,
        private WeeklyPoints $points,
        private MoneyLedger $ledger,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(CloseWeekCommand $command): void
    {
        $userId = Uuid::fromString($command->userId);
        $teamId = Uuid::fromString($command->teamId);
        $week = WeekStart::fromString($command->weekStart);

        if ($this->closures->find($userId, $week) !== null) {
            throw new \DomainException('This week has already been closed');
        }

        if ($week->monday() > $this->clock->now()) {
            throw new \DomainException('A week that has not started yet cannot be closed');
        }

        $settlement = $this->calculator->settle(
            $this->rules->ofTeam($teamId),
            $this->points->of($userId, $week)
        );

        $transactionId = null;

        if (!$settlement->isEmpty()) {
            $transactionId = $this->ledger->transfer(
                $userId,
                AccountRef::earnings(),
                AccountRef::pending(),
                $settlement->total(),
                TransactionType::WEEK_CLOSED,
                sprintf('Allowance for the week of %s', $week->value()),
                null
            )->id();
        }

        $this->closures->save(WeekClosure::close(
            Uuid::generate(),
            $teamId,
            $userId,
            $week,
            $settlement,
            Uuid::fromString($command->closedBy),
            $transactionId,
            $this->clock
        ));
    }
}
