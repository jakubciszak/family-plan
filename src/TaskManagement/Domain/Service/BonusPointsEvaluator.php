<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\ValueObject\RuleType;

class BonusPointsEvaluator
{
    private readonly StreakDailyPoints $streakPoints;

    public function __construct(
        private readonly TaskExecutionRepositoryInterface $executionRepository,
        private readonly ?PointsLedger $ledger = null,
        private readonly ?ClockInterface $clock = null
    ) {
        $this->streakPoints = new StreakDailyPoints($executionRepository, $ledger);
    }

    public function isRuleMet(BonusPointsRule $rule, Uuid $userId): bool
    {
        if (!$rule->isActive()) {
            return false;
        }

        return match ($rule->type()) {
            RuleType::CONSECUTIVE_DAYS => $this->evaluateConsecutiveDays($rule, $userId),
            RuleType::MONTHLY_TASK_COUNT => $this->evaluateMonthlyTaskCount($rule, $userId),
            RuleType::WEEKLY_POINTS_SUM => $this->evaluateWeeklyPointsSum($rule, $userId),
        };
    }

    /**
     * The stretch of time a single payout covers, so the same bonus is not booked twice.
     */
    public function periodKey(BonusPointsRule $rule, Uuid $userId): string
    {
        $now = $this->now();

        return match ($rule->type()) {
            RuleType::WEEKLY_POINTS_SUM => $now->format('o-\WW'),
            RuleType::MONTHLY_TASK_COUNT => $now->format('Y-m'),
            RuleType::CONSECUTIVE_DAYS => $this->streakStart($rule, $userId),
        };
    }

    private function evaluateConsecutiveDays(BonusPointsRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $requiredDays = $config->requiredDays();

        if ($requiredDays === null) {
            return false;
        }

        $perDay = $this->streakDays($config, $userId, $requiredDays);

        return PointsStreak::longest($perDay, $config->pointsPerDay() ?? 1) >= $requiredDays;
    }

    private function evaluateMonthlyTaskCount(BonusPointsRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $requiredCount = $config->requiredCount();

        if ($requiredCount === null) {
            return false;
        }

        $completedCount = $this->executionRepository->countApprovedInCurrentMonth($userId);

        return $completedCount >= $requiredCount;
    }

    private function evaluateWeeklyPointsSum(BonusPointsRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $requiredPoints = $config->requiredPoints();

        if ($requiredPoints === null || $this->ledger === null) {
            return false;
        }

        $monday = $this->now()->modify('monday this week')->setTime(0, 0);

        $booked = $this->ledger->sumBetween(
            $userId,
            $monday,
            $monday->modify('+7 days'),
            $config->accountKinds()
        );

        return $booked >= $requiredPoints;
    }

    private function streakStart(BonusPointsRule $rule, Uuid $userId): string
    {
        $config = $rule->config();
        $requiredDays = $config->requiredDays() ?? 2;

        $streak = PointsStreak::current(
            $this->streakDays($config, $userId, $requiredDays),
            $config->pointsPerDay() ?? 1
        );

        return $streak === [] ? $this->now()->format('Y-m-d') : (string) reset($streak);
    }

    /**
     * @return array<string, int>
     */
    private function streakDays(RuleConfig $config, Uuid $userId, int $requiredDays): array
    {
        $now = $this->now();

        return $this->streakPoints->perDay(
            $config,
            $userId,
            $now->modify(sprintf('-%d days', $requiredDays * 2)),
            $now->modify('+1 day')
        );
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
    }
}
