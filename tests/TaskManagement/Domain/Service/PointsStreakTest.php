<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Service\DailyPoints;
use App\TaskManagement\Domain\Service\PointsStreak;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PointsStreakTest extends TestCase
{
    public function testTheCurrentRunEndsOnTheLatestQualifyingDay(): void
    {
        $run = PointsStreak::current(DailyPoints::perDay([
            $this->executionOn('2026-03-01', 30),
            $this->executionOn('2026-03-03', 30),
            $this->executionOn('2026-03-04', 30),
        ]), 20);

        $this->assertSame(['2026-03-03', '2026-03-04'], $run);
    }

    public function testThereIsNoCurrentRunWithoutQualifyingDays(): void
    {
        $this->assertSame([], PointsStreak::current(DailyPoints::perDay([$this->executionOn('2026-03-01', 5)]), 20));
    }

    public function testARunThatReachesTheDayBeforeIsStillAlive(): void
    {
        $run = PointsStreak::aliveOn(DailyPoints::perDay([
            $this->executionOn('2026-03-03', 30),
            $this->executionOn('2026-03-04', 30),
        ]), '2026-03-05', 20);

        $this->assertSame(['2026-03-03', '2026-03-04'], $run);
    }

    public function testARunThatStoppedEarlierIsBroken(): void
    {
        $run = PointsStreak::aliveOn(DailyPoints::perDay([
            $this->executionOn('2026-03-02', 30),
            $this->executionOn('2026-03-04', 5),
        ]), '2026-03-05', 20);

        $this->assertSame([], $run);
    }

    private function executionOn(string $day, int $points = 10): TaskExecution
    {
        return TaskExecution::takeFromTemplate(
            Uuid::generate(),
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            'opis',
            Points::fromInt($points),
            Uuid::generate(),
            new DateTimeImmutable($day)
        );
    }
}
