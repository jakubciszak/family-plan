<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Allowance\Domain\Entity\SavingsGoal;
use App\Allowance\Domain\Entity\WeekClosure;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Allowance\Domain\Repository\WeekClosureRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Goals with what is already put aside, and how close the owner is to getting there.
 */
final readonly class GoalsView
{
    private const WEEKS_OF_PACE = 8;

    public function __construct(
        private SavingsGoalRepositoryInterface $goals,
        private MoneyLedger $ledger,
        private WeekClosureRepositoryInterface $closures,
        private ClockInterface $clock,
        private string $currency
    ) {
    }

    public function of(Uuid $userId, bool $openOnly = true): array
    {
        $saved = $this->ledger->goalBalances($userId);
        $pace = $this->weeklyPace($userId);
        $available = $this->ledger->balance($userId, AccountRef::available());

        return [
            'currency' => $this->currency,
            'weeklyPace' => $pace->minorUnits(),
            'available' => $available->minorUnits(),
            'goals' => array_map(
                fn (SavingsGoal $goal) => $this->describe($goal, $saved[$goal->id()->value()] ?? Money::zero(), $pace),
                $this->goals->ofUser($userId, $openOnly)
            ),
        ];
    }

    private function describe(SavingsGoal $goal, Money $saved, Money $pace): array
    {
        $missing = $goal->target()->minus($saved);
        $missing = $missing->isNegative() ? Money::zero() : $missing;

        return [
            'id' => $goal->id()->value(),
            'name' => $goal->name(),
            'target' => $goal->target()->minorUnits(),
            'saved' => $saved->minorUnits(),
            'missing' => $missing->minorUnits(),
            'percent' => $goal->target()->isPositive()
                ? min(100, intdiv($saved->minorUnits() * 100, $goal->target()->minorUnits()))
                : 0,
            'wantedBy' => $goal->wantedBy()?->format('Y-m-d'),
            'weeksLeft' => $this->weeksLeft($goal),
            'weeksAtThisPace' => $this->weeksAtThisPace($missing, $pace),
            'perWeekNeeded' => $this->perWeekNeeded($goal, $missing),
            'reached' => $goal->reachedAt() !== null,
            'reachedAt' => $goal->reachedAt()?->format('c'),
            'open' => $goal->isOpen(),
        ];
    }

    private function weeklyPace(Uuid $userId): Money
    {
        $from = $this->clock->now()->modify(sprintf('-%d weeks', self::WEEKS_OF_PACE))->setTime(0, 0);
        $closures = $this->closures->ofUserBetween($userId, $from, $this->clock->now());

        if ($closures === []) {
            return Money::zero();
        }

        $earned = array_reduce(
            $closures,
            static fn (Money $carried, WeekClosure $closure) => $carried->plus($closure->total()),
            Money::zero()
        );

        return Money::fromMinorUnits(intdiv($earned->minorUnits(), count($closures)));
    }

    private function weeksLeft(SavingsGoal $goal): ?int
    {
        if ($goal->wantedBy() === null) {
            return null;
        }

        $days = (int) $this->clock->now()->setTime(0, 0)->diff($goal->wantedBy())->format('%r%a');

        return max(0, (int) ceil($days / 7));
    }

    private function weeksAtThisPace(Money $missing, Money $pace): ?int
    {
        if (!$pace->isPositive() || $missing->isZero()) {
            return $missing->isZero() ? 0 : null;
        }

        return (int) ceil($missing->minorUnits() / $pace->minorUnits());
    }

    private function perWeekNeeded(SavingsGoal $goal, Money $missing): ?int
    {
        $weeksLeft = $this->weeksLeft($goal);

        if ($weeksLeft === null || $missing->isZero()) {
            return null;
        }

        return $weeksLeft === 0 ? $missing->minorUnits() : (int) ceil($missing->minorUnits() / $weeksLeft);
    }
}
