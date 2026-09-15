<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Domain;

use App\Allowance\Domain\Entity\SavingsGoal;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SavingsGoalTest extends TestCase
{
    public function testAGoalIsReachedOnceEnoughIsPutAside(): void
    {
        $goal = $this->goal(20000);

        $goal->noteProgress(Money::fromMinorUnits(19999), $this->clock());
        $this->assertNull($goal->reachedAt());

        $goal->noteProgress(Money::fromMinorUnits(20000), $this->clock());
        $this->assertNotNull($goal->reachedAt());
    }

    public function testTakingMoneyBackOffAGoalUndoesReachingIt(): void
    {
        $goal = $this->goal(20000);
        $goal->noteProgress(Money::fromMinorUnits(20000), $this->clock());

        $goal->noteProgress(Money::fromMinorUnits(15000), $this->clock());

        $this->assertNull($goal->reachedAt());
    }

    public function testRaisingTheTargetPutsTheGoalOutOfReachAgain(): void
    {
        $goal = $this->goal(20000);
        $goal->noteProgress(Money::fromMinorUnits(20000), $this->clock());

        $goal->adjust('Rower', Money::fromMinorUnits(50000), null);
        $goal->noteProgress(Money::fromMinorUnits(20000), $this->clock());

        $this->assertNull($goal->reachedAt());
        $this->assertSame('Rower', $goal->name());
    }

    public function testAGoalNeedsAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SavingsGoal::plan(Uuid::generate(), Uuid::generate(), '   ', Money::fromMinorUnits(100), null, $this->clock());
    }

    public function testAClosedGoalCannotBeClosedAgain(): void
    {
        $goal = $this->goal(20000);
        $goal->close($this->clock());

        $this->expectException(DomainException::class);

        $goal->close($this->clock());
    }

    private function goal(int $target): SavingsGoal
    {
        return SavingsGoal::plan(
            Uuid::generate(),
            Uuid::generate(),
            'Hulajnoga',
            Money::fromMinorUnits($target),
            null,
            $this->clock()
        );
    }

    private function clock(): ClockInterface
    {
        return new FixedClock(new DateTimeImmutable('2026-09-14 10:00:00'));
    }
}
