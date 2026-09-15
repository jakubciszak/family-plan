<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\ValueObject\ConversionRate;
use App\Allowance\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConversionRateTest extends TestCase
{
    public function testAWholeAmountPerPointJustMultiplies(): void
    {
        $rate = ConversionRate::of(Money::fromMinorUnits(35), 1);

        $this->assertSame(245, $rate->convert(7)->minorUnits());
    }

    public function testAnAmountSpreadOverSeveralPointsIsSplitExactly(): void
    {
        $rate = ConversionRate::of(Money::fromMinorUnits(500), 3);

        $this->assertSame(1167, $rate->convert(7)->minorUnits());
    }

    public function testHalfAMinorUnitRoundsUp(): void
    {
        $rate = ConversionRate::of(Money::fromMinorUnits(5), 2);

        $this->assertSame(3, $rate->convert(1)->minorUnits());
    }

    public function testNoPointsEarnNothing(): void
    {
        $rate = ConversionRate::of(Money::fromMinorUnits(500), 3);

        $this->assertSame(0, $rate->convert(0)->minorUnits());
        $this->assertSame(0, $rate->convert(-5)->minorUnits());
    }

    public function testAnAmountForNoPointsIsNotARate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConversionRate::of(Money::fromMinorUnits(100), 0);
    }

    public function testARateCannotTakeMoneyAway(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConversionRate::of(Money::fromMinorUnits(-100), 1);
    }
}
