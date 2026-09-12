<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Service;

use App\Shared\Infrastructure\Clock\SystemClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Service\DailyPoints;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DailyPointsTest extends TestCase
{
    public function testPointsOfOneDayAddUp(): void
    {
        $perDay = DailyPoints::perDay([
            $this->execution('2026-03-01 08:00', 10),
            $this->execution('2026-03-01 18:00', 25),
        ]);

        $this->assertSame(['2026-03-01' => 35], $perDay);
    }

    public function testDaysComeBackOldestFirst(): void
    {
        $perDay = DailyPoints::perDay([
            $this->execution('2026-03-03', 10),
            $this->execution('2026-03-01', 10),
            $this->execution('2026-03-02', 10),
        ]);

        $this->assertSame(['2026-03-01', '2026-03-02', '2026-03-03'], array_keys($perDay));
    }

    public function testTheDayIsWhenTheTaskWasDoneNotWhenItWasTaken(): void
    {
        $execution = $this->execution('2026-03-01', 10);
        $execution->complete(Uuid::generate(), new SystemClock());

        $this->assertSame(
            $execution->completedAt()->format('Y-m-d'),
            array_key_first(DailyPoints::perDay([$execution]))
        );
    }

    public function testOnlyDaysReachingTheThresholdAreListed(): void
    {
        $days = DailyPoints::daysReaching([
            $this->execution('2026-03-01', 30),
            $this->execution('2026-03-02', 10),
            $this->execution('2026-03-03', 20),
        ], 20);

        $this->assertSame(['2026-03-01', '2026-03-03'], $days);
    }

    public function testATasklessDayIsNotListed(): void
    {
        $this->assertSame([], DailyPoints::daysReaching([], 1));
    }

    private function execution(string $when, int $points): TaskExecution
    {
        return TaskExecution::takeFromTemplate(
            Uuid::generate(),
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            'opis',
            Points::fromInt($points),
            Uuid::generate(),
            new DateTimeImmutable($when)
        );
    }
}
