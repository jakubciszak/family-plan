<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\Entity\AllowanceRule;
use App\Allowance\Domain\ValueObject\ConversionRate;
use App\Allowance\Domain\ValueObject\Money;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AllowanceRuleTest extends TestCase
{
    public function testPointsBelowTheMinimumEarnNothing(): void
    {
        $rule = $this->rule(minimumPoints: 50, amount: 10, perPoints: 1);

        $this->assertSame(0, $rule->earnedOn(49)->minorUnits());
    }

    public function testOnlyThePointsAboveTheMinimumArePaid(): void
    {
        $rule = $this->rule(minimumPoints: 50, amount: 10, perPoints: 1);

        $this->assertSame(0, $rule->earnedOn(50)->minorUnits());
        $this->assertSame(200, $rule->earnedOn(70)->minorUnits());
    }

    public function testWithoutAMinimumEveryPointCounts(): void
    {
        $rule = $this->rule(minimumPoints: 0, amount: 250, perPoints: 10);

        $this->assertSame(25, $rule->earnedOn(1)->minorUnits());
    }

    public function testADeactivatedRulePaysNothing(): void
    {
        $rule = $this->rule(minimumPoints: 0, amount: 100, perPoints: 1);
        $rule->deactivate($this->clock());

        $this->assertSame(0, $rule->earnedOn(30)->minorUnits());
    }

    public function testTheRateCanBeChangedLater(): void
    {
        $rule = $this->rule(minimumPoints: 0, amount: 100, perPoints: 1);

        $rule->adjust(20, ConversionRate::of(Money::fromMinorUnits(50), 1), $this->clock());

        $this->assertSame(0, $rule->earnedOn(19)->minorUnits());
        $this->assertSame(0, $rule->earnedOn(20)->minorUnits());
        $this->assertSame(250, $rule->earnedOn(25)->minorUnits());
    }

    private function rule(int $minimumPoints, int $amount, int $perPoints): AllowanceRule
    {
        return AllowanceRule::define(
            Uuid::generate(),
            Uuid::generate(),
            AccountKind::TASKS,
            $minimumPoints,
            ConversionRate::of(Money::fromMinorUnits($amount), $perPoints),
            $this->clock()
        );
    }

    private function clock(): ClockInterface
    {
        return new FixedClock(new DateTimeImmutable('2026-09-14 10:00:00'));
    }
}
