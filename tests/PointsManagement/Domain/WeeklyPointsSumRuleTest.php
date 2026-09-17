<?php

declare(strict_types=1);

namespace App\Tests\PointsManagement\Domain;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\PointsManagement\Infrastructure\Persistence\InMemoryAccountRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryEntryRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Service\BonusPointsEvaluator;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WeeklyPointsSumRuleTest extends TestCase
{
    private const TODAY = '2026-09-16 12:00:00';

    private FixedClock $clock;
    private PointsLedger $ledger;
    private BonusPointsEvaluator $evaluator;
    private Uuid $userId;

    /**
     * @var TaskExecution[]
     */
    private array $approved = [];

    protected function setUp(): void
    {
        // A Wednesday, so the week has a past and a future
        $this->clock = new FixedClock(new DateTimeImmutable(self::TODAY));
        $this->userId = Uuid::generate();

        $accounts = new InMemoryAccountRepository();
        $this->ledger = new PointsLedger(
            $accounts,
            new InMemoryEntryRepository($accounts),
            new InMemoryUserWalletRepository(),
            $this->clock
        );

        $executions = $this->createStub(TaskExecutionRepositoryInterface::class);
        $executions->method('findApprovedByUserSince')->willReturnCallback(fn () => $this->approved);

        $this->evaluator = new BonusPointsEvaluator($executions, $this->ledger, $this->clock);
    }

    public function testTaskPointsEarnedThisWeekMeetTheRule(): void
    {
        $this->approve(60, 'now');

        $this->assertTrue($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testPointsBelowTheThresholdDoNotMeetTheRule(): void
    {
        $this->approve(40, 'now');

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testBonusPointsDoNotCountTowardsATaskOnlyRule(): void
    {
        $this->approve(30, 'now');
        $this->book(AccountKind::BONUSES, 100);

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testARuleOverEveryAccountCountsBonusesToo(): void
    {
        $this->approve(30, 'now');
        $this->book(AccountKind::BONUSES, 100);

        $this->assertTrue($this->evaluator->isRuleMet($this->rule(50, []), $this->userId));
    }

    public function testWorkDoneLastWeekDoesNotCountEvenWhenApprovedThisWeek(): void
    {
        $this->approve(90, 'last sunday');
        $this->approve(20, 'now');

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testWorkDoneThisWeekCountsEvenWhenApprovedLater(): void
    {
        $this->approve(60, 'monday this week');

        $this->assertTrue($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testThePeriodKeyIsTheIsoWeekSoTheBonusIsPaidOnce(): void
    {
        $this->assertSame('2026-W38', $this->evaluator->periodKey($this->rule(50, ['tasks']), $this->userId));
    }

    public function testTheLedgerRefusesASecondEntryForTheSameRuleAndWeek(): void
    {
        $rule = $this->rule(50, ['tasks']);
        $periodKey = $this->evaluator->periodKey($rule, $this->userId);

        $this->ledger->post($this->userId, AccountKind::BONUSES, 25, EntrySource::BONUS_RULE, 'Bonus', $rule->id(), $periodKey);
        $this->ledger->post($this->userId, AccountKind::BONUSES, 25, EntrySource::BONUS_RULE, 'Bonus', $rule->id(), $periodKey);

        $this->assertSame(25, $this->ledger->balances($this->userId)[AccountKind::BONUSES->value]);
    }

    /**
     * @param string[] $accounts
     */
    private function rule(int $requiredPoints, array $accounts): BonusPointsRule
    {
        return BonusPointsRule::create(
            Uuid::generate(),
            Uuid::generate(),
            'Tygodniowa zbiórka',
            'Zbierz punkty w ciągu tygodnia',
            Points::fromInt(25),
            RuleConfig::weeklyPointsSum($requiredPoints, $accounts)
        );
    }

    private function approve(int $points, string $when): void
    {
        $this->approved[] = TaskExecution::takeFromTemplate(
            Uuid::generate(),
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            'opis',
            Points::fromInt($points),
            $this->userId,
            (new DateTimeImmutable(self::TODAY))->modify($when)
        );
    }

    private function book(AccountKind $kind, int $amount): void
    {
        $this->ledger->post($this->userId, $kind, $amount, EntrySource::BONUS_RULE, 'Booked');
    }
}
