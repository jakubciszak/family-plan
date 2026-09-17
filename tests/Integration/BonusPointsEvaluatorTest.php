<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Infrastructure\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\Service\BonusPointsEvaluator;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\UserManagement\Domain\Entity\User;
use DateTimeImmutable;

class BonusPointsEvaluatorTest extends IntegrationTestCase
{
    private BonusPointsEvaluator $evaluator;

    private TaskExecutionRepositoryInterface $executions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executions = $this->service(TaskExecutionRepositoryInterface::class);
        $this->evaluator = new BonusPointsEvaluator($this->executions);
    }

    public function testAStreakOfApprovedRunsMeetsTheRule(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();

        foreach (['-2 days', '-1 day', 'now'] as $day) {
            $this->approvedRun($doer, $template, $day);
        }

        $rule = $this->consecutiveDaysRule($template->id(), 3);

        $this->assertTrue($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testAShorterStreakDoesNotMeetTheRule(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();

        foreach (['-1 day', 'now'] as $day) {
            $this->approvedRun($doer, $template, $day);
        }

        $rule = $this->consecutiveDaysRule($template->id(), 3);

        $this->assertFalse($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testAStreakThatBrokeDoesNotMeetTheRuleAgain(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();

        foreach (['-5 days', '-4 days', '-3 days', '-1 day', 'now'] as $day) {
            $this->approvedRun($doer, $template, $day);
        }

        $rule = $this->consecutiveDaysRule($template->id(), 3);

        $this->assertFalse($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testADayShortOfTheThresholdBreaksTheRun(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();

        foreach (['-4 days', '-3 days', '-1 day', 'now'] as $day) {
            $this->approvedRun($doer, $template, $day);
        }

        $rule = $this->consecutiveDaysRule($template->id(), 4);

        $this->assertFalse($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testRunsOfSomebodyElseDoNotCount(): void
    {
        $doer = $this->user('Dziecko');
        $sibling = $this->user('Rodzenstwo');
        $template = $this->taskType();

        foreach (['-2 days', '-1 day', 'now'] as $day) {
            $this->approvedRun($sibling, $template, $day);
        }

        $rule = $this->consecutiveDaysRule($template->id(), 3);

        $this->assertFalse($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testAnInactiveRuleIsNeverMet(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();

        foreach (['-2 days', '-1 day', 'now'] as $day) {
            $this->approvedRun($doer, $template, $day);
        }

        $rule = $this->consecutiveDaysRule($template->id(), 3);
        $rule->deactivate();

        $this->assertFalse($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testEnoughRunsThisMonthMeetTheMonthlyRule(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();

        foreach (range(1, 3) as $ignored) {
            $this->approvedRun($doer, $template, 'now');
        }

        $rule = BonusPointsRule::create(
            Uuid::generate(),
            Uuid::generate(),
            'Trzy w miesiacu',
            'opis',
            Points::fromInt(50),
            RuleConfig::monthlyTaskCount(3)
        );

        $this->assertTrue($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    public function testTooFewRunsThisMonthDoNotMeetTheMonthlyRule(): void
    {
        $doer = $this->user('Dziecko');
        $template = $this->taskType();
        $this->approvedRun($doer, $template, 'now');

        $rule = BonusPointsRule::create(
            Uuid::generate(),
            Uuid::generate(),
            'Trzy w miesiacu',
            'opis',
            Points::fromInt(50),
            RuleConfig::monthlyTaskCount(3)
        );

        $this->assertFalse($this->evaluator->isRuleMet($rule, $doer->id()));
    }

    private function consecutiveDaysRule(Uuid $templateId, int $days): BonusPointsRule
    {
        return BonusPointsRule::create(
            Uuid::generate(),
            Uuid::generate(),
            sprintf('%d dni z rzedu', $days),
            'opis',
            Points::fromInt(50),
            RuleConfig::consecutiveDays($days, 1, $templateId)
        );
    }

    private function taskType(): TaskTemplate
    {
        $template = TaskTemplate::create(
            Uuid::generate(),
            TaskName::fromString('Zmywanie po obiedzie'),
            'opis',
            Points::fromInt(10),
            Frequency::fromString('daily'),
            ScheduleConfig::daily()
        );

        $this->service(TaskTemplateRepositoryInterface::class)->save($template);

        return $template;
    }

    private function approvedRun(User $doer, TaskTemplate $template, string $day): void
    {
        $approver = $this->user('Rodzic');

        $execution = TaskExecution::takeFromTemplate(
            Uuid::generate(),
            $template->id(),
            $template->name(),
            $template->description(),
            $template->points(),
            $doer->id(),
            new DateTimeImmutable($day)
        );
        $clock = new FixedClock(new DateTimeImmutable($day));
        $execution->complete($doer->id(), $clock);
        $execution->approve($approver->id(), $clock);

        $this->executions->save($execution);
    }
}
