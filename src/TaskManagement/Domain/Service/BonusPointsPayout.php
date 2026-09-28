<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Period\ClosedWeeksInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use DateTimeImmutable;

/**
 * Books the bonus of every rule the user has just met.
 * The ledger refuses a second entry for the same rule within one period,
 * so calling this after every approval is safe.
 */
final readonly class BonusPointsPayout implements BonusSettlementInterface
{
    public function __construct(
        private BonusPointsRuleRepositoryInterface $rules,
        private TeamMembershipRepositoryInterface $memberships,
        private BonusPointsEvaluator $evaluator,
        private PointsLedger $ledger,
        private ClosedWeeksInterface $closedWeeks,
        private ClockInterface $clock
    ) {
    }

    public function settleFor(Uuid $userId): void
    {
        foreach ($this->memberships->ofUser($userId) as $membership) {
            foreach ($this->rules->findActiveByTeamId($membership->teamId()) as $rule) {
                foreach ($this->evaluator->earnedPeriods($rule, $userId) as $periodKey => $day) {
                    $this->ledger->post(
                        $userId,
                        AccountKind::BONUSES,
                        $rule->bonusPoints()->value(),
                        EntrySource::BONUS_RULE,
                        sprintf('Bonus: %s', $rule->name()),
                        $rule->id(),
                        (string) $periodKey,
                        $this->bookedOn($userId, $day)
                    );
                }
            }
        }
    }

    private function bookedOn(Uuid $userId, string $day): ?DateTimeImmutable
    {
        $noon = DailyPoints::day($day)->setTime(12, 0);

        if ($day >= $this->clock->now()->format('Y-m-d') || $this->closedWeeks->isClosedFor($userId, $noon)) {
            return null;
        }

        return $noon;
    }
}
