<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Service;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Infrastructure\Persistence\InMemoryAccountRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryEntryRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Domain\Period\ClosedWeeksInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Service\BonusPointsEvaluator;
use App\TaskManagement\Domain\Service\BonusPointsPayout;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BonusPointsPayoutTest extends TestCase
{
    private const WEEK = ['2026-09-21' => 5, '2026-09-22' => 1, '2026-09-23' => 2, '2026-09-24' => 4, '2026-09-25' => 2, '2026-09-26' => 2, '2026-09-27' => 3];

    private FixedClock $clock;
    private PointsLedger $ledger;
    private BonusPointsPayout $payout;
    private Uuid $userId;
    private Uuid $teamId;
    private string $settledBefore = '';

    /**
     * @var TaskExecution[]
     */
    private array $approved = [];

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new DateTimeImmutable('2026-09-28 19:00:00'));
        $this->userId = Uuid::generate();
        $this->teamId = Uuid::generate();
        $teamId = $this->teamId;

        $accounts = new InMemoryAccountRepository();
        $this->ledger = new PointsLedger($accounts, new InMemoryEntryRepository($accounts), new InMemoryUserWalletRepository(), $this->clock);

        $executions = $this->createStub(TaskExecutionRepositoryInterface::class);
        $executions->method('findApprovedByUserSince')->willReturnCallback(fn () => $this->approved);

        $rules = $this->createStub(BonusPointsRuleRepositoryInterface::class);
        $rules->method('findActiveByTeamId')->willReturn([BonusPointsRule::create(
            Uuid::generate(),
            $teamId,
            'Seria',
            'Pięć dni po dwa punkty',
            Points::fromInt(5),
            RuleConfig::consecutiveDays(5, 2, null, ['tasks'])
        )]);

        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('ofUser')->willReturn([new TeamMembership(Uuid::generate(), $teamId, $this->userId, TeamRole::member(), new DateTimeImmutable('2026-09-01'))]);

        $closedWeeks = $this->createStub(ClosedWeeksInterface::class);
        $closedWeeks->method('isClosedFor')->willReturnCallback(fn (Uuid $userId, DateTimeImmutable $day) => $day->format('Y-m-d') < $this->settledBefore);

        $this->payout = new BonusPointsPayout(
            $rules,
            $memberships,
            new BonusPointsEvaluator($executions, $this->ledger, $this->clock),
            $this->ledger,
            $closedWeeks,
            $this->clock
        );
    }

    public function testAStreakCompletedByADayBookedTheNextMorningBelongsToThatDay(): void
    {
        $this->earn(self::WEEK);

        $this->payout->settleFor($this->userId);

        $this->assertSame(['2026-09-27' => 5], $this->bonuses());
    }

    public function testAStreakCompletedTodayIsBookedRightNow(): void
    {
        $this->clock->setTime(new DateTimeImmutable('2026-09-27 18:30:00'));
        $this->earn(self::WEEK);

        $this->payout->settleFor($this->userId);

        $entries = $this->ledger->between($this->userId, new DateTimeImmutable('2026-09-21'), new DateTimeImmutable('2026-10-05'), [AccountKind::BONUSES], teamId: $this->teamId);
        $this->assertCount(1, $entries);
        $this->assertSame('2026-09-27 18:30', $entries[0]->bookedAt()->format('Y-m-d H:i'));
    }

    public function testABonusDueInASettledWeekLandsOnToday(): void
    {
        $this->settledBefore = '2026-09-28';
        $this->earn(self::WEEK);

        $this->payout->settleFor($this->userId);

        $this->assertSame(['2026-09-28' => 5], $this->bonuses());
    }

    public function testAStreakCompletedByALateApprovalIsStillPaidOnTheDayItWasCompleted(): void
    {
        $this->clock->setTime(new DateTimeImmutable('2026-09-30 08:00:00'));
        $this->earn(self::WEEK);

        $this->payout->settleFor($this->userId);

        $this->assertSame(['2026-09-27' => 5], $this->bonuses());
    }

    public function testOnlyTheCyclesOfAFinishedRunCompletedWithinTheBacklogWindowArePaidLate(): void
    {
        $this->earn(array_fill_keys(array_map(static fn (int $day) => sprintf('2026-09-%02d', $day), range(15, 24)), 2));

        $this->payout->settleFor($this->userId);

        $this->assertSame(['2026-09-24' => 5], $this->bonuses());
    }

    public function testAStreakCompletedBeforeTheBacklogWindowIsNotPaidLate(): void
    {
        $this->clock->setTime(new DateTimeImmutable('2026-10-06 08:00:00'));
        $this->earn(self::WEEK);

        $this->payout->settleFor($this->userId);

        $this->assertSame([], $this->bonuses());
    }

    public function testSettlingAgainPaysTheStreakOnce(): void
    {
        $this->earn(self::WEEK);

        $this->payout->settleFor($this->userId);
        $this->payout->settleFor($this->userId);

        $this->assertSame(['2026-09-27' => 5], $this->bonuses());
    }

    /**
     * @param array<string, int> $pointsPerDay
     */
    private function earn(array $pointsPerDay): void
    {
        foreach ($pointsPerDay as $day => $points) {
            $this->approved[] = TaskExecution::takeFromTemplate(
                Uuid::generate(),
                Uuid::generate(),
                TaskName::fromString('Zmywanie'),
                'opis',
                Points::fromInt($points),
                $this->userId,
                new DateTimeImmutable($day . ' 17:00:00')
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function bonuses(): array
    {
        return $this->ledger->perDayBetween($this->userId, new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-10-12'), [AccountKind::BONUSES], teamId: $this->teamId);
    }
}
