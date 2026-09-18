<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\TaskName;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ExecutionCorrectionTest extends TestCase
{
    public function testAWeekCanCrossTheYearBoundary(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-01-02'));
        $execution = $this->approved($clock);
        $execution->moveTo(new DateTimeImmutable('2025-12-31'), $clock);

        $this->assertSame('2025-12-31', $execution->earnedOn()->format('Y-m-d'));
        $this->assertSame('2026-01-02', $execution->approvedAt()->format('Y-m-d'));
    }

    public function testMovingToAFutureDayInTheSameWeekIsRefused(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-01-02'));
        $execution = $this->approved($clock);

        $this->expectException(DomainException::class);
        $execution->moveTo(new DateTimeImmutable('2026-01-03'), $clock);
    }

    private function approved(FixedClock $clock): TaskExecution
    {
        $member = Uuid::generate();
        $execution = TaskExecution::createOneTime(
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            '',
            Points::fromInt(10),
            $clock->now(),
            $member
        );
        $execution->complete($member, $clock);
        $execution->approve(Uuid::generate(), $clock);

        return $execution;
    }
}
