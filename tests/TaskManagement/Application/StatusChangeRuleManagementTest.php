<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Command\ActivateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\CreateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\DeactivateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\UpdateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Handler\ActivateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\CreateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\DeactivateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\UpdateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\GetAllStatusChangeRulesQueryHandler;
use App\TaskManagement\Application\StatusChangeRule\Query\GetAllStatusChangeRulesQuery;
use App\TaskManagement\Infrastructure\Persistence\InMemoryStatusChangeRuleRepository;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for Status Change Rule Management
 * Tests the complete workflow of managing status change rules
 */
class StatusChangeRuleManagementTest extends TestCase
{
    private InMemoryStatusChangeRuleRepository $repository;
    private CreateStatusChangeRuleHandler $createHandler;
    private UpdateStatusChangeRuleHandler $updateHandler;
    private ActivateStatusChangeRuleHandler $activateHandler;
    private DeactivateStatusChangeRuleHandler $deactivateHandler;
    private GetAllStatusChangeRulesQueryHandler $queryHandler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryStatusChangeRuleRepository();
        $this->createHandler = new CreateStatusChangeRuleHandler($this->repository);
        $this->updateHandler = new UpdateStatusChangeRuleHandler($this->repository);
        $this->activateHandler = new ActivateStatusChangeRuleHandler($this->repository);
        $this->deactivateHandler = new DeactivateStatusChangeRuleHandler($this->repository);
        $this->queryHandler = new GetAllStatusChangeRulesQueryHandler($this->repository);
    }

    public function testAdminCanCreateOtherTaskCompletedTodayRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $taskTemplateId = Uuid::generate()->value();
        $requiredTaskTemplateId = Uuid::generate()->value();
        
        $command = new CreateStatusChangeRuleCommand(
            $ruleId,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Morning Exercise Prerequisite',
            'Task cannot be assigned unless morning exercise was completed today',
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY->value,
            [
                'requiredTaskTemplateId' => $requiredTaskTemplateId
            ]
        );

        // When
        ($this->createHandler)($command);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertNotNull($rule);
        $this->assertEquals('Morning Exercise Prerequisite', $rule->name());
        $this->assertEquals(StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY, $rule->conditionType());
        $this->assertTrue($rule->isActive());
    }

    public function testAdminCanCreateLastExecutionCooldownRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $taskTemplateId = Uuid::generate()->value();
        
        $command = new CreateStatusChangeRuleCommand(
            $ruleId,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Cooldown After Completion',
            'Task cannot be assigned within 2 days of last execution',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            [
                'cooldownDays' => 2
            ]
        );

        // When
        ($this->createHandler)($command);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertNotNull($rule);
        $this->assertEquals('Cooldown After Completion', $rule->name());
        $this->assertEquals(StatusChangeConditionType::LAST_EXECUTION_COOLDOWN, $rule->conditionType());
        $this->assertTrue($rule->isActive());
    }

    public function testAdminCanUpdateRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $taskTemplateId = Uuid::generate()->value();
        
        $createCommand = new CreateStatusChangeRuleCommand(
            $ruleId,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Original Name',
            'Original Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 2]
        );
        ($this->createHandler)($createCommand);

        // When
        $updateCommand = new UpdateStatusChangeRuleCommand(
            $ruleId,
            'Updated Name',
            'Updated Description'
        );
        ($this->updateHandler)($updateCommand);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertEquals('Updated Name', $rule->name());
        $this->assertEquals('Updated Description', $rule->description());
    }

    public function testAdminCanDeactivateRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $taskTemplateId = Uuid::generate()->value();
        
        $createCommand = new CreateStatusChangeRuleCommand(
            $ruleId,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Test Rule',
            'Test Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 2]
        );
        ($this->createHandler)($createCommand);

        // When
        $deactivateCommand = new DeactivateStatusChangeRuleCommand($ruleId);
        ($this->deactivateHandler)($deactivateCommand);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertFalse($rule->isActive());
    }

    public function testAdminCanReactivateRule(): void
    {
        // Given
        $ruleId = Uuid::generate()->value();
        $taskTemplateId = Uuid::generate()->value();
        
        $createCommand = new CreateStatusChangeRuleCommand(
            $ruleId,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Test Rule',
            'Test Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 2]
        );
        ($this->createHandler)($createCommand);
        ($this->deactivateHandler)(new DeactivateStatusChangeRuleCommand($ruleId));

        // When
        $activateCommand = new ActivateStatusChangeRuleCommand($ruleId);
        ($this->activateHandler)($activateCommand);

        // Then
        $rule = $this->repository->findById(Uuid::fromString($ruleId));
        $this->assertTrue($rule->isActive());
    }

    public function testCanListAllRules(): void
    {
        // Given - Create multiple rules
        $taskTemplateId = Uuid::generate()->value();
        
        $createCommands = [
            new CreateStatusChangeRuleCommand(
                Uuid::generate()->value(),
                Uuid::generate()->value(),
                $taskTemplateId,
                'Rule 1',
                'Description 1',
                StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
                ['cooldownDays' => 1]
            ),
            new CreateStatusChangeRuleCommand(
                Uuid::generate()->value(),
                Uuid::generate()->value(),
                $taskTemplateId,
                'Rule 2',
                'Description 2',
                StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
                ['cooldownDays' => 2]
            ),
        ];

        foreach ($createCommands as $command) {
            ($this->createHandler)($command);
        }

        // When
        $rules = ($this->queryHandler)(new GetAllStatusChangeRulesQuery());

        // Then
        $this->assertCount(2, $rules);
    }

    public function testCanListOnlyActiveRules(): void
    {
        // Given - Create rules and deactivate one
        $taskTemplateId = Uuid::generate()->value();
        $ruleId1 = Uuid::generate()->value();
        $ruleId2 = Uuid::generate()->value();
        
        ($this->createHandler)(new CreateStatusChangeRuleCommand(
            $ruleId1,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Active Rule',
            'Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 1]
        ));
        
        ($this->createHandler)(new CreateStatusChangeRuleCommand(
            $ruleId2,
            Uuid::generate()->value(),
            $taskTemplateId,
            'Inactive Rule',
            'Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 2]
        ));
        
        ($this->deactivateHandler)(new DeactivateStatusChangeRuleCommand($ruleId2));

        // When
        $rules = ($this->queryHandler)(new GetAllStatusChangeRulesQuery(activeOnly: true));

        // Then
        $this->assertCount(1, $rules);
        $this->assertEquals('Active Rule', $rules[0]->name());
    }

    public function testCanFilterRulesByTaskTemplate(): void
    {
        // Given - Create rules for different task templates
        $taskTemplateId1 = Uuid::generate()->value();
        $taskTemplateId2 = Uuid::generate()->value();
        
        ($this->createHandler)(new CreateStatusChangeRuleCommand(
            Uuid::generate()->value(),
            Uuid::generate()->value(),
            $taskTemplateId1,
            'Template 1 Rule',
            'Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 1]
        ));

        ($this->createHandler)(new CreateStatusChangeRuleCommand(
            Uuid::generate()->value(),
            Uuid::generate()->value(),
            $taskTemplateId2,
            'Template 2 Rule',
            'Description',
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN->value,
            ['cooldownDays' => 2]
        ));

        // When
        $rules = ($this->queryHandler)(new GetAllStatusChangeRulesQuery(
            activeOnly: false,
            taskTemplateId: $taskTemplateId1
        ));

        // Then
        $this->assertCount(1, $rules);
        $this->assertEquals('Template 1 Rule', $rules[0]->name());
    }
}
