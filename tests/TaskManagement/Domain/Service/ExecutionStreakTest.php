<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Service\ExecutionStreak;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExecutionStreakTest extends TestCase
{
    public function testNoExecutionsMeanNoStreak(): void
    {
        $this->assertSame(0, ExecutionStreak::longest([]));
    }

    public function testOneExecutionIsAStreakOfOne(): void
    {
        $this->assertSame(1, ExecutionStreak::longest([$this->executionOn('2026-03-01')]));
    }

    public function testDaysInARowCount(): void
    {
        $streak = ExecutionStreak::longest([
            $this->executionOn('2026-03-01'),
            $this->executionOn('2026-03-02'),
            $this->executionOn('2026-03-03'),
        ]);

        $this->assertSame(3, $streak);
    }

    public function testAGapBreaksTheStreak(): void
    {
        $streak = ExecutionStreak::longest([
            $this->executionOn('2026-03-01'),
            $this->executionOn('2026-03-02'),
            $this->executionOn('2026-03-05'),
            $this->executionOn('2026-03-06'),
        ]);

        $this->assertSame(2, $streak);
    }

    public function testTheLongestRunWins(): void
    {
        $streak = ExecutionStreak::longest([
            $this->executionOn('2026-03-01'),
            $this->executionOn('2026-03-02'),
            $this->executionOn('2026-03-03'),
            $this->executionOn('2026-03-10'),
        ]);

        $this->assertSame(3, $streak);
    }

    public function testTwoRunsOnTheSameDayCountOnce(): void
    {
        $streak = ExecutionStreak::longest([
            $this->executionOn('2026-03-01 08:00'),
            $this->executionOn('2026-03-01 20:00'),
            $this->executionOn('2026-03-02 09:00'),
        ]);

        $this->assertSame(2, $streak);
    }

    public function testOrderOfExecutionsDoesNotMatter(): void
    {
        $streak = ExecutionStreak::longest([
            $this->executionOn('2026-03-03'),
            $this->executionOn('2026-03-01'),
            $this->executionOn('2026-03-02'),
        ]);

        $this->assertSame(3, $streak);
    }

    public function testAMonthBoundaryIsStillARun(): void
    {
        $streak = ExecutionStreak::longest([
            $this->executionOn('2026-03-31'),
            $this->executionOn('2026-04-01'),
        ]);

        $this->assertSame(2, $streak);
    }

    private function executionOn(string $day): TaskExecution
    {
        return TaskExecution::takeFromTemplate(
            Uuid::generate(),
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            'opis',
            Points::fromInt(10),
            Uuid::generate(),
            new DateTimeImmutable($day)
        );
    }
}
