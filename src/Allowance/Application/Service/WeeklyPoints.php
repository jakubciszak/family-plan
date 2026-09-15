<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Allowance\Domain\ValueObject\WeekStart;
use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Service\DailyPoints;

/**
 * The same points the week calendar shows, split by the account they belong to.
 */
final readonly class WeeklyPoints
{
    public function __construct(
        private TaskExecutionRepositoryInterface $executions,
        private PointsLedger $ledger
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function of(Uuid $userId, WeekStart $week): array
    {
        return [
            AccountKind::TASKS->value => array_sum($this->tasksPerDay($userId, $week)),
            AccountKind::BONUSES->value => array_sum($this->bonusPerDay($userId, $week)),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function tasksPerDay(Uuid $userId, WeekStart $week): array
    {
        $earned = $this->executions->findApprovedByUserSince($userId, $week->monday());

        return array_filter(
            DailyPoints::perDay($earned),
            static fn (string $day) => $day < $week->nextMonday()->format('Y-m-d'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array<string, int>
     */
    public function bonusPerDay(Uuid $userId, WeekStart $week): array
    {
        return $this->ledger->perDayBetween($userId, $week->monday(), $week->nextMonday(), [AccountKind::BONUSES]);
    }
}
