<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Command\ActivateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\CreateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\DeactivateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\UpdateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Handler\ActivateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\CreateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\DeactivateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\UpdateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\GetAllBonusPointsRulesQueryHandler;
use App\TaskManagement\Application\BonusRule\Query\GetAllBonusPointsRulesQuery;
use App\TaskManagement\Infrastructure\Persistence\InMemoryBonusPointsRuleRepository;
use App\TaskManagement\Domain\ValueObject\RuleType;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for Bonus Points Rule Management
 * Tests the complete workflow of managing bonus rules
 */
class BonusPointsRuleManagementTest extends TestCase
{
    private InMemoryBonusPointsRuleRepository $repository;
    private CreateBonusPointsRuleHandler $createHandler;
    private UpdateBonusPointsRuleHandler $updateHandler;
    private ActivateBonusPointsRuleHandler $activateHandler;
    private DeactivateBonusPointsRuleHandler $deactivateHandler;
    private GetAllBonusPointsRulesQueryHandler $queryHandler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBonusPointsRuleRepository();
        $this->createHandler = new CreateBonusPointsRuleHandler($this->repository);
        $this->updateHandler = new UpdateBonusPointsRuleHandler($this->repository);
        $this->activateHandler = new ActivateBonusPointsRuleHandler($this->repository);
        $this->deactivateHandler = new DeactivateBonusPointsRuleHandler($this->repository);
        $this->queryHandler = new GetAllBonusPointsRulesQueryHandler($this->repository);
    }

    public function testAdminCanCreateConsecutiveDaysRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $taskTemplateId = Uuid::generate()->value();
        
        $command = new CreateBonusPointsRuleCommand(
            $ruleId,
            'Dishwasher Streak',
            'Earn 20 bonus points for emptying dishwasher 5 consecutive days',
            20,
            RuleType::CONSECUTIVE_DAYS->value,
            [
                'taskTemplateId' => $taskTemplateId,
                'requiredDays' => 5
            ]
        );

        // When
        ($this->createHandler)($command);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertNotNull($rule);
        $this->assertEquals('Dishwasher Streak', $rule->name());
        $this->assertEquals(20, $rule->bonusPoints()->value());
        $this->assertEquals(RuleType::CONSECUTIVE_DAYS, $rule->type());
        $this->assertTrue($rule->isActive());
    }

    public function testAdminCanCreateMonthlyTaskCountRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        
        $command = new CreateBonusPointsRuleCommand(
            $ruleId,
            'Monthly Champion',
            'Earn 30 bonus points for completing 20 tasks in a month',
            30,
            RuleType::MONTHLY_TASK_COUNT->value,
            [
                'requiredCount' => 20
            ]
        );

        // When
        ($this->createHandler)($command);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertNotNull($rule);
        $this->assertEquals('Monthly Champion', $rule->name());
        $this->assertEquals(30, $rule->bonusPoints()->value());
        $this->assertEquals(RuleType::MONTHLY_TASK_COUNT, $rule->type());
        $this->assertTrue($rule->isActive());
    }

    public function testAdminCanUpdateRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $createCommand = new CreateBonusPointsRuleCommand(
            $ruleId,
            'Original Name',
            'Original Description',
            20,
            RuleType::MONTHLY_TASK_COUNT->value,
            ['requiredCount' => 10]
        );
        ($this->createHandler)($createCommand);

        // When
        $updateCommand = new UpdateBonusPointsRuleCommand(
            $ruleId,
            'Updated Name',
            'Updated Description',
            50
        );
        ($this->updateHandler)($updateCommand);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertEquals('Updated Name', $rule->name());
        $this->assertEquals('Updated Description', $rule->description());
        $this->assertEquals(50, $rule->bonusPoints()->value());
    }

    public function testAdminCanDeactivateRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $createCommand = new CreateBonusPointsRuleCommand(
            $ruleId,
            'Test Rule',
            'Test Description',
            20,
            RuleType::MONTHLY_TASK_COUNT->value,
            ['requiredCount' => 10]
        );
        ($this->createHandler)($createCommand);

        // When
        $deactivateCommand = new DeactivateBonusPointsRuleCommand($ruleId);
        ($this->deactivateHandler)($deactivateCommand);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertFalse($rule->isActive());
    }

    public function testAdminCanReactivateRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $createCommand = new CreateBonusPointsRuleCommand(
            $ruleId,
            'Test Rule',
            'Test Description',
            20,
            RuleType::MONTHLY_TASK_COUNT->value,
            ['requiredCount' => 10]
        );
        ($this->createHandler)($createCommand);
        ($this->deactivateHandler)(new DeactivateBonusPointsRuleCommand($ruleId));

        // When
        $activateCommand = new ActivateBonusPointsRuleCommand($ruleId);
        ($this->activateHandler)($activateCommand);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertTrue($rule->isActive());
    }

    public function testCanListAllRules(): void
    {
        // Given - Create multiple rules
        $createCommands = [
            new CreateBonusPointsRuleCommand(
                Uuid::generate()->value(),
                'Rule 1',
                'Description 1',
                20,
                RuleType::MONTHLY_TASK_COUNT->value,
                ['requiredCount' => 10]
            ),
            new CreateBonusPointsRuleCommand(
                Uuid::generate()->value(),
                'Rule 2',
                'Description 2',
                30,
                RuleType::MONTHLY_TASK_COUNT->value,
                ['requiredCount' => 20]
            ),
        ];

        foreach ($createCommands as $command) {
            ($this->createHandler)($command);
        }

        // When
        $rules = ($this->queryHandler)(new GetAllBonusPointsRulesQuery());

        // Then
        $this->assertCount(2, $rules);
    }

    public function testCanListOnlyActiveRules(): void
    {
        // Given - Create rules and deactivate one
        $ruleId1 = Uuid::generate()->value();
        $ruleId2 = Uuid::generate()->value();
        
        ($this->createHandler)(new CreateBonusPointsRuleCommand(
            $ruleId1,
            'Active Rule',
            'Description',
            20,
            RuleType::MONTHLY_TASK_COUNT->value,
            ['requiredCount' => 10]
        ));
        
        ($this->createHandler)(new CreateBonusPointsRuleCommand(
            $ruleId2,
            'Inactive Rule',
            'Description',
            30,
            RuleType::MONTHLY_TASK_COUNT->value,
            ['requiredCount' => 20]
        ));
        
        ($this->deactivateHandler)(new DeactivateBonusPointsRuleCommand($ruleId2));

        // When
        $rules = ($this->queryHandler)(new GetAllBonusPointsRulesQuery(activeOnly: true));

        // Then
        $this->assertCount(1, $rules);
        $this->assertEquals('Active Rule', $rules[0]->name());
    }
}
