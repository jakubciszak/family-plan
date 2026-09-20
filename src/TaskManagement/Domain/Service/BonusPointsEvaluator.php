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

    /**
     * @return string[]
     */
    public function earnedPeriodKeys(BonusPointsRule $rule, Uuid $userId): array
    {
        if (!$this->isRuleMet($rule, $userId)) {
            return [];
        }

        if ($rule->type() !== RuleType::CONSECUTIVE_DAYS) {
            return [$this->periodKey($rule, $userId)];
        }

        $requiredDays = $rule->config()->requiredDays();
        $run = $this->liveStreak($rule->config(), $userId);
        $cycles = array_filter(array_chunk($run, $requiredDays), static fn (array $cycle) => count($cycle) === $requiredDays);

        return array_values(array_map(static fn (array $cycle) => $cycle[0], $cycles));
    }

    private function evaluateConsecutiveDays(BonusPointsRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $requiredDays = $config->requiredDays();

        if ($requiredDays === null) {
            return false;
        }

        return count($this->liveStreak($config, $userId)) >= $requiredDays;
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

        if ($requiredPoints === null) {
            return false;
        }

        $monday = $this->now()->modify('monday this week')->setTime(0, 0);

        $earned = $this->streakPoints->perDay($config, $userId, $monday, $monday->modify('+7 days'));

        return array_sum($earned) >= $requiredPoints;
    }

    private function streakStart(BonusPointsRule $rule, Uuid $userId): string
    {
        $config = $rule->config();
        $requiredDays = $config->requiredDays() ?? 2;

        $streak = $this->liveStreak($config, $userId);

        return $streak === [] ? $this->now()->format('Y-m-d') : $streak[max(0, intdiv(count($streak), $requiredDays) - 1) * $requiredDays];
    }

    /**
     * @return string[] the run going on right now, empty when it has been broken
     */
    private function liveStreak(RuleConfig $config, Uuid $userId): array
    {
        return PointsStreak::aliveOn(
            $this->streakDays($config, $userId),
            $this->now()->format('Y-m-d'),
            $config->pointsPerDay() ?? 1
        );
    }

    /**
     * @return array<string, int>
     */
    private function streakDays(RuleConfig $config, Uuid $userId): array
    {
        $now = $this->now();

        return $this->streakPoints->perDay(
            $config,
            $userId,
            new DateTimeImmutable('1970-01-01'),
            $now->modify('+1 day')
        );
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
    }
}
