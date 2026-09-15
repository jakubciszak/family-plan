<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\PlanGoalCommand;
use App\Allowance\Application\Service\BookingDay;
use App\Allowance\Domain\Entity\SavingsGoal;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PlanGoalHandler
{
    public function __construct(
        private SavingsGoalRepositoryInterface $goals,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(PlanGoalCommand $command): void
    {
        $this->goals->save(SavingsGoal::plan(
            Uuid::fromString($command->id),
            Uuid::fromString($command->userId),
            $command->name,
            Money::fromMinorUnits($command->target),
            BookingDay::from($command->wantedBy),
            $this->clock
        ));
    }
}
