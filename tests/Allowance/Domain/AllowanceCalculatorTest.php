<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\Entity\AllowanceRule;
use App\Allowance\Domain\Service\AllowanceCalculator;
use App\Allowance\Domain\ValueObject\ConversionRate;
use App\Allowance\Domain\ValueObject\Money;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AllowanceCalculatorTest extends TestCase
{
    public function testEachPointsAccountIsSettledOnItsOwnTerms(): void
    {
        $settlement = (new AllowanceCalculator())->settle(
            [
                $this->rule(AccountKind::TASKS, minimumPoints: 50, amount: 20, perPoints: 1),
                $this->rule(AccountKind::BONUSES, minimumPoints: 0, amount: 10, perPoints: 1),
            ],
            ['tasks' => 60, 'bonuses' => 25]
        );

        $this->assertSame(1450, $settlement->total()->minorUnits());
        $this->assertSame(85, $settlement->points());
        $this->assertCount(2, $settlement->lines());
    }

    public function testAnAccountThatMissedItsMinimumIsListedButPaysNothing(): void
    {
        $settlement = (new AllowanceCalculator())->settle(
            [$this->rule(AccountKind::TASKS, minimumPoints: 50, amount: 20, perPoints: 1)],
            ['tasks' => 30]
        );

        $lines = $settlement->lines();

        $this->assertSame(0, $settlement->total()->minorUnits());
        $this->assertFalse($lines[0]->reachedMinimum());
        $this->assertSame(30, $lines[0]->points());
    }

    public function testWithoutAnyRuleNothingIsOwed(): void
    {
        $settlement = (new AllowanceCalculator())->settle([], ['tasks' => 200]);

        $this->assertTrue($settlement->isEmpty());
        $this->assertSame([], $settlement->toArray());
    }

    private function rule(AccountKind $account, int $minimumPoints, int $amount, int $perPoints): AllowanceRule
    {
        return AllowanceRule::define(
            Uuid::generate(),
            Uuid::generate(),
            $account,
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
