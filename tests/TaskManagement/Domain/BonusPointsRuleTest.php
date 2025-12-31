<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleType;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Tests for BonusPointsRule domain entity
 * Behavioral approach: Tests the functionality of creating and managing bonus rules
 */
class BonusPointsRuleTest extends TestCase
{
    public function testConsecutiveDaysRuleCanBeCreated(): void
    {
        // Given
        $ruleId = Uuid::generate();
        $name = 'Dishwasher Streak Bonus';
        $description = 'Earn 20 bonus points for emptying dishwasher 5 consecutive days';
        $bonusPoints = Points::fromInt(20);
        $config = RuleConfig::consecutiveDays(
            taskTemplateId: Uuid::generate(),
            requiredDays: 5
        );

        // When
        $rule = BonusPointsRule::create(
            $ruleId,
            $name,
            $description,
            $bonusPoints,
            $config
        );

        // Then
        $this->assertEquals($ruleId, $rule->id());
        $this->assertEquals($name, $rule->name());
        $this->assertEquals($description, $rule->description());
        $this->assertEquals($bonusPoints, $rule->bonusPoints());
        $this->assertEquals(RuleType::CONSECUTIVE_DAYS, $rule->type());
        $this->assertTrue($rule->isActive());
    }

    public function testMonthlyTaskCountRuleCanBeCreated(): void
    {
        // Given
        $ruleId = Uuid::generate();
        $name = 'Monthly Task Champion';
        $description = 'Earn 30 bonus points for completing at least 20 tasks in a month';
        $bonusPoints = Points::fromInt(30);
        $config = RuleConfig::monthlyTaskCount(
            requiredCount: 20
        );

        // When
        $rule = BonusPointsRule::create(
            $ruleId,
            $name,
            $description,
            $bonusPoints,
            $config
        );

        // Then
        $this->assertEquals($ruleId, $rule->id());
        $this->assertEquals($bonusPoints, $rule->bonusPoints());
        $this->assertEquals(RuleType::MONTHLY_TASK_COUNT, $rule->type());
        $this->assertTrue($rule->isActive());
    }

    public function testRuleCanBeDeactivated(): void
    {
        // Given
        $rule = $this->createSampleRule();

        // When
        $rule->deactivate();

        // Then
        $this->assertFalse($rule->isActive());
    }

    public function testRuleCanBeReactivated(): void
    {
        // Given
        $rule = $this->createSampleRule();
        $rule->deactivate();

        // When
        $rule->activate();

        // Then
        $this->assertTrue($rule->isActive());
    }

    public function testRuleCanBeUpdated(): void
    {
        // Given
        $rule = $this->createSampleRule();
        $newName = 'Updated Rule Name';
        $newDescription = 'Updated description';
        $newBonusPoints = Points::fromInt(50);

        // When
        $rule->update($newName, $newDescription, $newBonusPoints);

        // Then
        $this->assertEquals($newName, $rule->name());
        $this->assertEquals($newDescription, $rule->description());
        $this->assertEquals($newBonusPoints, $rule->bonusPoints());
    }

    public function testBonusPointsCannotBeNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Points::fromInt(-10);
    }

    private function createSampleRule(): BonusPointsRule
    {
        return BonusPointsRule::create(
            Uuid::generate(),
            'Sample Rule',
            'Sample Description',
            Points::fromInt(20),
            RuleConfig::consecutiveDays(
                taskTemplateId: Uuid::generate(),
                requiredDays: 5
            )
        );
    }
}
