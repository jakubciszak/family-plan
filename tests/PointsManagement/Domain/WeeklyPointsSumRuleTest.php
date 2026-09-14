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
use App\TaskManagement\Domain\Service\BonusPointsEvaluator;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WeeklyPointsSumRuleTest extends TestCase
{
    private FixedClock $clock;
    private PointsLedger $ledger;
    private BonusPointsEvaluator $evaluator;
    private Uuid $userId;

    protected function setUp(): void
    {
        // A Wednesday, so the week has a past and a future
        $this->clock = new FixedClock(new DateTimeImmutable('2026-09-16 12:00:00'));
        $this->userId = Uuid::generate();

        $accounts = new InMemoryAccountRepository();
        $this->ledger = new PointsLedger(
            $accounts,
            new InMemoryEntryRepository($accounts),
            new InMemoryUserWalletRepository(),
            $this->clock
        );

        $this->evaluator = new BonusPointsEvaluator(
            $this->createStub(TaskExecutionRepositoryInterface::class),
            $this->ledger,
            $this->clock
        );
    }

    public function testTaskPointsBookedThisWeekMeetTheRule(): void
    {
        $this->book(AccountKind::TASKS, 60);

        $this->assertTrue($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testPointsBelowTheThresholdDoNotMeetTheRule(): void
    {
        $this->book(AccountKind::TASKS, 40);

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testBonusPointsDoNotCountTowardsATaskOnlyRule(): void
    {
        $this->book(AccountKind::TASKS, 30);
        $this->book(AccountKind::BONUSES, 100);

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
    }

    public function testARuleOverEveryAccountCountsBonusesToo(): void
    {
        $this->book(AccountKind::TASKS, 30);
        $this->book(AccountKind::BONUSES, 100);

        $this->assertTrue($this->evaluator->isRuleMet($this->rule(50, []), $this->userId));
    }

    public function testPointsFromLastWeekDoNotCount(): void
    {
        $lastWeek = new FixedClock(new DateTimeImmutable('2026-09-08 12:00:00'));
        $accounts = new InMemoryAccountRepository();
        $ledger = new PointsLedger(
            $accounts,
            new InMemoryEntryRepository($accounts),
            new InMemoryUserWalletRepository(),
            $lastWeek
        );

        $ledger->post($this->userId, AccountKind::TASKS, 90, EntrySource::TASK_EXECUTION, 'Last week');

        $evaluator = new BonusPointsEvaluator(
            $this->createStub(TaskExecutionRepositoryInterface::class),
            $ledger,
            $this->clock
        );

        $this->assertFalse($evaluator->isRuleMet($this->rule(50, ['tasks']), $this->userId));
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

    private function book(AccountKind $kind, int $amount): void
    {
        $this->ledger->post($this->userId, $kind, $amount, EntrySource::TASK_EXECUTION, 'Booked');
    }
}
