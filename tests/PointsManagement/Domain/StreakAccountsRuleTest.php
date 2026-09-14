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
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Service\BonusPointsEvaluator;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StreakAccountsRuleTest extends TestCase
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

    public function testAStreakCountsTheAccountTheRuleNames(): void
    {
        $this->book(AccountKind::BONUSES, 20, '-2 days');
        $this->book(AccountKind::BONUSES, 20, '-1 day');
        $this->book(AccountKind::BONUSES, 20, 'now');

        $this->assertTrue($this->evaluator->isRuleMet($this->rule(3, 20, ['bonuses']), $this->userId));
    }

    public function testADayBelowTheThresholdBreaksTheStreakOnThatAccount(): void
    {
        $this->book(AccountKind::BONUSES, 20, '-2 days');
        $this->book(AccountKind::BONUSES, 5, '-1 day');
        $this->book(AccountKind::BONUSES, 20, 'now');

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(3, 20, ['bonuses']), $this->userId));
    }

    public function testPointsOnAnotherAccountDoNotCarryTheStreak(): void
    {
        foreach (['-2 days', '-1 day', 'now'] as $day) {
            $this->approve(20, $day);
        }

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(3, 20, ['bonuses']), $this->userId));
    }

    public function testARuleWithoutAChosenAccountStaysOnTaskPoints(): void
    {
        $this->book(AccountKind::BONUSES, 50, '-2 days');
        $this->book(AccountKind::BONUSES, 50, '-1 day');
        $this->book(AccountKind::BONUSES, 50, 'now');

        $rule = $this->rule(3, 20, []);

        $this->assertSame(['tasks'], $rule->config()->accounts());
        $this->assertFalse($this->evaluator->isRuleMet($rule, $this->userId));
    }

    public function testBothAccountsTogetherAddUpOnTheSameDay(): void
    {
        foreach (['-1 day', 'now'] as $day) {
            $this->book(AccountKind::BONUSES, 12, $day);
            $this->approve(8, $day);
        }

        $this->assertFalse($this->evaluator->isRuleMet($this->rule(2, 20, ['bonuses']), $this->userId));
        $this->assertFalse($this->evaluator->isRuleMet($this->rule(2, 20, ['tasks']), $this->userId));
        $this->assertTrue($this->evaluator->isRuleMet($this->rule(2, 20, ['tasks', 'bonuses']), $this->userId));
    }

    /**
     * @param string[] $accounts
     */
    private function rule(int $requiredDays, int $pointsPerDay, array $accounts): BonusPointsRule
    {
        return BonusPointsRule::create(
            Uuid::generate(),
            Uuid::generate(),
            'Dni z rzędu',
            'Seria dni z punktami',
            Points::fromInt(100),
            RuleConfig::consecutiveDays($requiredDays, $pointsPerDay, null, $accounts)
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

    private function book(AccountKind $kind, int $amount, string $when): void
    {
        $this->clock->setTime((new DateTimeImmutable(self::TODAY))->modify($when));

        $this->ledger->post($this->userId, $kind, $amount, EntrySource::BONUS_RULE, 'Test');

        $this->clock->setTime(new DateTimeImmutable(self::TODAY));
    }
}
