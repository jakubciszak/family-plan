<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\TaskManagement\Domain\ValueObject\ExecutionLimit;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExecutionLimitTest extends TestCase
{
    public function testUnlimitedNeverRunsOut(): void
    {
        $limit = ExecutionLimit::unlimited();

        $this->assertTrue($limit->allowsAnother(0));
        $this->assertTrue($limit->allowsAnother(999));
        $this->assertNull($limit->remaining(999));
    }

    public function testOnceIsSpentAfterTheFirstRun(): void
    {
        $limit = ExecutionLimit::once();

        $this->assertTrue($limit->allowsAnother(0));
        $this->assertSame(1, $limit->remaining(0));

        $this->assertFalse($limit->allowsAnother(1));
        $this->assertSame(0, $limit->remaining(1));
    }

    public function testThreeTimesADayRunsOutAtThree(): void
    {
        $limit = ExecutionLimit::perDay(3);

        $this->assertTrue($limit->allowsAnother(2));
        $this->assertSame(1, $limit->remaining(2));
        $this->assertFalse($limit->allowsAnother(3));
    }

    public function testDailyWindowStartsAtMidnight(): void
    {
        $limit = ExecutionLimit::perDay(3);

        $this->assertEquals(
            new DateTimeImmutable('2026-09-12 00:00:00'),
            $limit->windowStart(new DateTimeImmutable('2026-09-12 17:42:11'))
        );
    }

    public function testWeeklyWindowStartsOnMonday(): void
    {
        $limit = ExecutionLimit::perWeek(2);

        $this->assertEquals(
            new DateTimeImmutable('2026-09-07 00:00:00'),
            $limit->windowStart(new DateTimeImmutable('2026-09-12 17:42:11'))
        );
    }

    public function testMonthlyWindowStartsOnTheFirst(): void
    {
        $limit = ExecutionLimit::perMonth(5);

        $this->assertEquals(
            new DateTimeImmutable('2026-09-01 00:00:00'),
            $limit->windowStart(new DateTimeImmutable('2026-09-12 17:42:11'))
        );
    }

    public function testOnceAndUnlimitedHaveNoWindow(): void
    {
        $this->assertNull(ExecutionLimit::once()->windowStart(new DateTimeImmutable()));
        $this->assertNull(ExecutionLimit::unlimited()->windowStart(new DateTimeImmutable()));
    }

    public function testLimitSurvivesARoundTripThroughAnArray(): void
    {
        foreach ([
            ExecutionLimit::unlimited(),
            ExecutionLimit::once(),
            ExecutionLimit::perDay(3),
            ExecutionLimit::perWeek(2),
            ExecutionLimit::perMonth(10),
        ] as $limit) {
            $this->assertEquals($limit, ExecutionLimit::fromArray($limit->toArray()));
        }
    }

    public function testCountMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExecutionLimit::perDay(0);
    }
}
