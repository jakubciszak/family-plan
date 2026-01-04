<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Command\ActivateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\CreateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\DeactivateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Query\GetAllStatusChangeRulesQuery;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

/**
 * Context for status change rules scenarios
 * Handles behavioral scenarios for status change rules management
 */
final class StatusChangeRulesContext extends AcceptanceContext
{
    private array $statusChangeRules = [];
    private array $taskTemplates = [];
    private ?\Throwable $lastException = null;

    /**
     * @BeforeScenario
     */
    public function resetState(BeforeScenarioScope $scope): void
    {
        $this->reset();
        $this->statusChangeRules = [];
        $this->taskTemplates = [];
        $this->lastException = null;
    }

    /**
     * @Given /^że istnieje reguła zmiany statusu "([^"]*)" która wymaga (\d+) dzień karencji po zakończeniu$/
     * @Given /^że istnieje reguła zmiany statusu "([^"]*)" która wymaga (\d+) dni karencji po zakończeniu$/
     * @Given /^istnieje reguła zmiany statusu "([^"]*)" która wymaga (\d+) dzień karencji po zakończeniu$/
     * @Given /^istnieje reguła zmiany statusu "([^"]*)" która wymaga (\d+) dni karencji po zakończeniu$/
     */
    public function thereIsAStatusChangeRuleThatRequiresDaysCooldownAfterCompletion(
        string $ruleName,
        int $cooldownDays
    ): void {
        $ruleId = Uuid::generate();
        $taskTemplateId = $this->getOrCreateTaskTemplate('Generic Task');

        $command = new CreateStatusChangeRuleCommand(
            $ruleId->value(),
            $taskTemplateId->value(),
            $ruleName,
            "Task cannot be assigned within {$cooldownDays} days of last execution",
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            [
                'cooldownDays' => $cooldownDays
            ]
        );

        ($this->createStatusChangeRuleHandler)($command);
        $this->statusChangeRules[$ruleName] = $ruleId;
    }

    /**
     * @Given /^że istnieje reguła zmiany statusu "([^"]*)" dla zadania "([^"]*)" która wymaga wykonania innego zadania dzisiaj$/
     * @Given /^istnieje reguła zmiany statusu "([^"]*)" dla zadania "([^"]*)" która wymaga wykonania innego zadania dzisiaj$/
     */
    public function thereIsAStatusChangeRuleForTaskThatRequiresAnotherTaskToBeCompletedToday(
        string $ruleName,
        string $taskName
    ): void {
        $ruleId = Uuid::generate();
        $taskTemplateId = $this->getOrCreateTaskTemplate($taskName);
        $requiredTaskTemplateId = $this->getOrCreateTaskTemplate('Required Task');

        $command = new CreateStatusChangeRuleCommand(
            $ruleId->value(),
            $taskTemplateId->value(),
            $ruleName,
            "Task cannot be assigned unless required task was completed today",
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY->value,
            [
                'requiredTaskTemplateId' => $requiredTaskTemplateId->value()
            ]
        );

        ($this->createStatusChangeRuleHandler)($command);
        $this->statusChangeRules[$ruleName] = $ruleId;
    }

    /**
     * @When /^reguła zmiany statusu "([^"]*)" zostaje aktywowana$/
     */
    public function theStatusChangeRuleIsActivated(string $ruleName): void
    {
        $ruleId = $this->statusChangeRules[$ruleName];

        try {
            $command = new ActivateStatusChangeRuleCommand($ruleId->value());
            ($this->activateStatusChangeRuleHandler)($command);
        } catch (\Throwable $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @When /^reguła zmiany statusu "([^"]*)" zostaje dezaktywowana$/
     */
    public function theStatusChangeRuleIsDeactivated(string $ruleName): void
    {
        $ruleId = $this->statusChangeRules[$ruleName];

        try {
            $command = new DeactivateStatusChangeRuleCommand($ruleId->value());
            ($this->deactivateStatusChangeRuleHandler)($command);
        } catch (\Throwable $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @Then /^powinno być (\d+) aktywna reguła zmiany statusu$/
     * @Then /^powinno być (\d+) aktywne reguły zmiany statusu$/
     * @Then /^powinno być (\d+) aktywnych reguł zmiany statusu$/
     */
    public function thereShouldBeActiveStatusChangeRules(int $count): void
    {
        $rules = ($this->getAllStatusChangeRulesQueryHandler)(
            new GetAllStatusChangeRulesQuery(activeOnly: true)
        );

        Assert::assertCount(
            $count,
            $rules,
            "Expected {$count} active status change rules"
        );
    }

    /**
     * @Then /^reguła zmiany statusu "([^"]*)" powinna być aktywna$/
     */
    public function statusChangeRuleShouldBeActive(string $ruleName): void
    {
        $ruleId = $this->statusChangeRules[$ruleName];
        $rule = $this->statusChangeRuleRepository->findById($ruleId);

        Assert::assertNotNull($rule, "Status change rule {$ruleName} not found");
        Assert::assertTrue($rule->isActive(), "Status change rule {$ruleName} should be active");
    }

    /**
     * @Then /^reguła zmiany statusu "([^"]*)" powinna być nieaktywna$/
     */
    public function statusChangeRuleShouldBeInactive(string $ruleName): void
    {
        $ruleId = $this->statusChangeRules[$ruleName];
        $rule = $this->statusChangeRuleRepository->findById($ruleId);

        Assert::assertNotNull($rule, "Status change rule {$ruleName} not found");
        Assert::assertFalse($rule->isActive(), "Status change rule {$ruleName} should be inactive");
    }

    /**
     * Helper to get or create a task template ID
     */
    private function getOrCreateTaskTemplate(string $name): Uuid
    {
        if (!isset($this->taskTemplates[$name])) {
            $this->taskTemplates[$name] = Uuid::generate();
        }

        return $this->taskTemplates[$name];
    }
}
