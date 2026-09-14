<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use DateTimeImmutable;

final readonly class StreakDailyPoints
{
    public function __construct(
        private TaskExecutionRepositoryInterface $executionRepository,
        private ?PointsLedger $ledger = null
    ) {
    }

    /**
     * @return array<string, int> points the rule counts on each day, keyed by Y-m-d, oldest first
     */
    public function perDay(
        RuleConfig $config,
        Uuid $userId,
        DateTimeImmutable $since,
        DateTimeImmutable $until
    ): array {
        $kinds = $config->accountKinds();
        $perDay = [];

        if (in_array(AccountKind::TASKS, $kinds, true)) {
            $perDay = DailyPoints::perDay($this->executionRepository->findApprovedByUserSince(
                $userId,
                $since,
                $config->taskTemplateId()
            ));
        }

        foreach ($kinds as $kind) {
            if ($kind === AccountKind::TASKS || $this->ledger === null) {
                continue;
            }

            foreach ($this->ledger->perDayBetween($userId, $since, $until, [$kind]) as $day => $points) {
                $perDay[$day] = ($perDay[$day] ?? 0) + $points;
            }
        }

        ksort($perDay);

        return $perDay;
    }
}
