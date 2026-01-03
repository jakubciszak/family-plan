<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Command\ActivateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\CreateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\DeactivateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Query\GetAllBonusPointsRulesQuery;
use App\TaskManagement\Domain\ValueObject\RuleType;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

/**
 * Context for bonus points rules scenarios
 * Handles behavioral scenarios for bonus points management
 */
final class BonusPointsContext extends AcceptanceContext
{
    private array $bonusRules = [];
    private ?\Throwable $lastException = null;

    /**
     * @BeforeScenario
     */
    public function resetState(BeforeScenarioScope $scope): void
    {
        $this->reset();
        $this->bonusRules = [];
        $this->lastException = null;
    }

    /**
     * @Given there is a bonus rule :ruleName that awards :points bonus points for completing :requiredCount tasks in a month
     */
    public function thereIsABonusRuleThatAwardsBonusPointsForCompletingTasksInAMonth(
        string $ruleName,
        int $points,
        int $requiredCount
    ): void {
        $ruleId = Uuid::generate();

        $command = new CreateBonusPointsRuleCommand(
            $ruleId->value(),
            $ruleName,
            "Earn {$points} bonus points for completing {$requiredCount} tasks in a month",
            $points,
            RuleType::MONTHLY_TASK_COUNT->value,
            [
                'requiredCount' => $requiredCount
            ]
        );

        ($this->createBonusPointsRuleHandler)($command);
        $this->bonusRules[$ruleName] = $ruleId;
    }

    /**
     * @Given there is a bonus rule :ruleName that awards :points bonus points for :consecutiveDays consecutive days of task completion
     */
    public function thereIsABonusRuleThatAwardsBonusPointsForConsecutiveDaysOfTaskCompletion(
        string $ruleName,
        int $points,
        int $consecutiveDays
    ): void {
        $ruleId = Uuid::generate();
        $taskTemplateId = Uuid::generate(); // For simplicity, using a generic template

        $command = new CreateBonusPointsRuleCommand(
            $ruleId->value(),
            $ruleName,
            "Earn {$points} bonus points for {$consecutiveDays} consecutive days",
            $points,
            RuleType::CONSECUTIVE_DAYS->value,
            [
                'taskTemplateId' => $taskTemplateId->value(),
                'requiredDays' => $consecutiveDays
            ]
        );

        ($this->createBonusPointsRuleHandler)($command);
        $this->bonusRules[$ruleName] = $ruleId;
    }

    /**
     * @When the bonus rule :ruleName is activated
     */
    public function theBonusRuleIsActivated(string $ruleName): void
    {
        $ruleId = $this->bonusRules[$ruleName];

        try {
            $command = new ActivateBonusPointsRuleCommand($ruleId->value());
            ($this->activateBonusPointsRuleHandler)($command);
        } catch (\Throwable $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @When the bonus rule :ruleName is deactivated
     */
    public function theBonusRuleIsDeactivated(string $ruleName): void
    {
        $ruleId = $this->bonusRules[$ruleName];

        try {
            $command = new DeactivateBonusPointsRuleCommand($ruleId->value());
            ($this->deactivateBonusPointsRuleHandler)($command);
        } catch (\Throwable $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @Then there should be :count active bonus rule(s)
     */
    public function thereShouldBeActiveBonusRules(int $count): void
    {
        $rules = ($this->getAllBonusPointsRulesQueryHandler)(new GetAllBonusPointsRulesQuery(activeOnly: true));

        Assert::assertCount(
            $count,
            $rules,
            "Expected {$count} active bonus rules"
        );
    }

    /**
     * @Then bonus rule :ruleName should be active
     */
    public function bonusRuleShouldBeActive(string $ruleName): void
    {
        $ruleId = $this->bonusRules[$ruleName];
        $rule = $this->bonusPointsRuleRepository->findById($ruleId);

        Assert::assertNotNull($rule, "Bonus rule {$ruleName} not found");
        Assert::assertTrue($rule->isActive(), "Bonus rule {$ruleName} should be active");
    }

    /**
     * @Then bonus rule :ruleName should be inactive
     */
    public function bonusRuleShouldBeInactive(string $ruleName): void
    {
        $ruleId = $this->bonusRules[$ruleName];
        $rule = $this->bonusPointsRuleRepository->findById($ruleId);

        Assert::assertNotNull($rule, "Bonus rule {$ruleName} not found");
        Assert::assertFalse($rule->isActive(), "Bonus rule {$ruleName} should be inactive");
    }

    /**
     * @Then all bonus rules should be listed
     */
    public function allBonusRulesShouldBeListed(): void
    {
        $rules = ($this->getAllBonusPointsRulesQueryHandler)(new GetAllBonusPointsRulesQuery(activeOnly: false));

        Assert::assertCount(
            count($this->bonusRules),
            $rules,
            "Expected all bonus rules to be listed"
        );
    }
}
