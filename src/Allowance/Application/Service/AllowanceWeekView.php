<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Allowance\Domain\Repository\AllowanceRuleRepositoryInterface;
use App\Allowance\Domain\Repository\WeekClosureRepositoryInterface;
use App\Allowance\Domain\Service\AllowanceCalculator;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\PointsManagement\Domain\ValueObject\AccountKind as PointsAccountKind;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * One week of a member: the points they collected, and what those points are worth.
 */
final readonly class AllowanceWeekView
{
    public function __construct(
        private WeeklyPoints $points,
        private AllowanceRuleRepositoryInterface $rules,
        private AllowanceCalculator $calculator,
        private WeekClosureRepositoryInterface $closures,
        private Households $households,
        private ClockInterface $clock,
        private string $currency
    ) {
    }

    public function of(Uuid $userId, WeekStart $week): array
    {
        $teamId = $this->households->teamOf($userId);
        $closure = $this->closures->find($userId, $week);

        $tasksPerDay = $this->points->tasksPerDay($userId, $week);
        $bonusPerDay = $this->points->bonusPerDay($userId, $week);

        $expected = $this->calculator->settle(
            $teamId === null ? [] : $this->rules->ofTeam($teamId),
            [
                PointsAccountKind::TASKS->value => array_sum($tasksPerDay),
                PointsAccountKind::BONUSES->value => array_sum($bonusPerDay),
            ]
        );

        $today = $this->clock->now()->format('Y-m-d');
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $week->monday()->modify(sprintf('+%d days', $offset))->format('Y-m-d');

            $days[] = [
                'date' => $day,
                'points' => $tasksPerDay[$day] ?? 0,
                'bonus' => $bonusPerDay[$day] ?? 0,
                'isToday' => $day === $today,
            ];
        }

        return [
            'weekStart' => $week->value(),
            'currency' => $this->currency,
            'days' => $days,
            'points' => array_sum(array_column($days, 'points')),
            'bonusPoints' => array_sum(array_column($days, 'bonus')),
            'isOver' => $week->nextMonday() <= $this->clock->now(),
            'expected' => [
                'total' => $expected->total()->minorUnits(),
                'lines' => $expected->toArray(),
            ],
            'closure' => $closure === null ? null : [
                'id' => $closure->id()->value(),
                'total' => $closure->total()->minorUnits(),
                'lines' => $closure->breakdown(),
                'closedAt' => $closure->closedAt()->format('c'),
            ],
        ];
    }
}
