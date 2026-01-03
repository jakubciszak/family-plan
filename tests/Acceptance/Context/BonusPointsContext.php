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
     * @Given /^że istnieje reguła bonusowa "([^"]*)" która przyznaje (\d+) punktów bonusowych za wykonanie (\d+) zadań w miesiącu$/
     * @Given /^istnieje reguła bonusowa "([^"]*)" która przyznaje (\d+) punktów bonusowych za wykonanie (\d+) zadań w miesiącu$/
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
     * @Given /^że istnieje reguła bonusowa "([^"]*)" która przyznaje (\d+) punktów bonusowych za (\d+) kolejnych dni wykonywania zadań$/
     * @Given /^istnieje reguła bonusowa "([^"]*)" która przyznaje (\d+) punktów bonusowych za (\d+) kolejnych dni wykonywania zadań$/
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
     * @When /^reguła bonusowa "([^"]*)" zostaje aktywowana$/
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
     * @When /^reguła bonusowa "([^"]*)" zostaje dezaktywowana$/
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
     * @Then /^powinno być (\d+) aktywn(?:a|ych) reguł?(?:a|y)? bonusow(?:a|ych)$/
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
     * @Then /^reguła bonusowa "([^"]*)" powinna być aktywna$/
     */
    public function bonusRuleShouldBeActive(string $ruleName): void
    {
        $ruleId = $this->bonusRules[$ruleName];
        $rule = $this->bonusPointsRuleRepository->findById($ruleId);

        Assert::assertNotNull($rule, "Bonus rule {$ruleName} not found");
        Assert::assertTrue($rule->isActive(), "Bonus rule {$ruleName} should be active");
    }

    /**
     * @Then /^reguła bonusowa "([^"]*)" powinna być nieaktywna$/
     */
    public function bonusRuleShouldBeInactive(string $ruleName): void
    {
        $ruleId = $this->bonusRules[$ruleName];
        $rule = $this->bonusPointsRuleRepository->findById($ruleId);

        Assert::assertNotNull($rule, "Bonus rule {$ruleName} not found");
        Assert::assertFalse($rule->isActive(), "Bonus rule {$ruleName} should be inactive");
    }

    /**
     * @Then /^wszystkie reguły bonusowe powinny być wylistowane$/
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
