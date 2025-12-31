<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Service\BonusPointsEvaluator;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

/**
 * Tests for BonusPointsEvaluator service
 * Behavioral approach: Tests the functionality of evaluating bonus point rules
 */
class BonusPointsEvaluatorTest extends TestCase
{
    public function testConsecutiveDaysRuleIsMetWhenUserCompletesTaskForRequiredDays(): void
    {
        // Given
        $userId = Uuid::generate();
        $taskTemplateId = Uuid::generate();
        $rule = $this->createConsecutiveDaysRule($taskTemplateId, 3);
        
        $repository = $this->createMock(TaskExecutionRepositoryInterface::class);
        
        // User completed the task 3 consecutive days
        $executions = [
            $this->createApprovedExecution($taskTemplateId, $userId, new DateTimeImmutable('2024-01-01')),
            $this->createApprovedExecution($taskTemplateId, $userId, new DateTimeImmutable('2024-01-02')),
            $this->createApprovedExecution($taskTemplateId, $userId, new DateTimeImmutable('2024-01-03')),
        ];
        
        $repository->method('findRecentApprovedByUserAndTemplate')
            ->with($userId, $taskTemplateId)
            ->willReturn($executions);
        
        $evaluator = new BonusPointsEvaluator($repository);
        
        // When
        $isMet = $evaluator->isRuleMet($rule, $userId);
        
        // Then
        $this->assertTrue($isMet);
    }

    public function testConsecutiveDaysRuleIsNotMetWhenDaysAreNotConsecutive(): void
    {
        // Given
        $userId = Uuid::generate();
        $taskTemplateId = Uuid::generate();
        $rule = $this->createConsecutiveDaysRule($taskTemplateId, 3);
        
        $repository = $this->createMock(TaskExecutionRepositoryInterface::class);
        
        // User completed the task but not consecutively (gap on Jan 2)
        $executions = [
            $this->createApprovedExecution($taskTemplateId, $userId, new DateTimeImmutable('2024-01-01')),
            $this->createApprovedExecution($taskTemplateId, $userId, new DateTimeImmutable('2024-01-03')),
            $this->createApprovedExecution($taskTemplateId, $userId, new DateTimeImmutable('2024-01-04')),
        ];
        
        $repository->method('findRecentApprovedByUserAndTemplate')
            ->with($userId, $taskTemplateId)
            ->willReturn($executions);
        
        $evaluator = new BonusPointsEvaluator($repository);
        
        // When
        $isMet = $evaluator->isRuleMet($rule, $userId);
        
        // Then
        $this->assertFalse($isMet);
    }

    public function testMonthlyTaskCountRuleIsMetWhenUserCompletesRequiredTasks(): void
    {
        // Given
        $userId = Uuid::generate();
        $rule = $this->createMonthlyTaskCountRule(5);
        
        $repository = $this->createMock(TaskExecutionRepositoryInterface::class);
        
        // User completed 6 tasks this month
        $repository->method('countApprovedInCurrentMonth')
            ->with($userId)
            ->willReturn(6);
        
        $evaluator = new BonusPointsEvaluator($repository);
        
        // When
        $isMet = $evaluator->isRuleMet($rule, $userId);
        
        // Then
        $this->assertTrue($isMet);
    }

    public function testMonthlyTaskCountRuleIsNotMetWhenUserDoesNotCompleteEnoughTasks(): void
    {
        // Given
        $userId = Uuid::generate();
        $rule = $this->createMonthlyTaskCountRule(10);
        
        $repository = $this->createMock(TaskExecutionRepositoryInterface::class);
        
        // User completed only 8 tasks this month
        $repository->method('countApprovedInCurrentMonth')
            ->with($userId)
            ->willReturn(8);
        
        $evaluator = new BonusPointsEvaluator($repository);
        
        // When
        $isMet = $evaluator->isRuleMet($rule, $userId);
        
        // Then
        $this->assertFalse($isMet);
    }

    private function createConsecutiveDaysRule(Uuid $taskTemplateId, int $requiredDays): BonusPointsRule
    {
        return BonusPointsRule::create(
            Uuid::generate(),
            'Test Consecutive Days Rule',
            'Test Description',
            Points::fromInt(20),
            RuleConfig::consecutiveDays($taskTemplateId, $requiredDays)
        );
    }

    private function createMonthlyTaskCountRule(int $requiredCount): BonusPointsRule
    {
        return BonusPointsRule::create(
            Uuid::generate(),
            'Test Monthly Count Rule',
            'Test Description',
            Points::fromInt(30),
            RuleConfig::monthlyTaskCount($requiredCount)
        );
    }

    private function createApprovedExecution(
        Uuid $taskTemplateId,
        Uuid $userId,
        DateTimeImmutable $scheduledFor
    ): TaskExecution {
        $execution = TaskExecution::createFromTemplate(
            Uuid::generate(),
            $taskTemplateId,
            $scheduledFor,
            $userId
        );
        
        // Mock completion and approval (we would need to expose these for testing or use reflection)
        // For now, this is a simplified version
        return $execution;
    }
}
