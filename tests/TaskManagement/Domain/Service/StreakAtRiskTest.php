<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Service;

use App\TaskManagement\Domain\Service\StreakAtRisk;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class StreakAtRiskTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-09-15');
    }

    public function testCompletedCycleIsNotAtRiskAndOnlyNewCycleDaysAreCounted(): void
    {
        $counted = array_fill_keys(['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'], 2);
        $this->assertSame(0, StreakAtRisk::days($counted, 2, $this->today, 5));
        $counted['2026-09-09'] = 2;
        ksort($counted);
        $this->assertSame(1, StreakAtRisk::days($counted, 2, $this->today, 5));
    }

    public function testStreakRunningUntilYesterdayIsAtRisk(): void
    {
        $counted = [
            '2026-09-12' => 3,
            '2026-09-13' => 3,
            '2026-09-14' => 3,
        ];

        $this->assertSame(3, StreakAtRisk::days($counted, 3, $this->today));
    }

    public function testNothingIsAtRiskOnceTodayCounts(): void
    {
        $counted = [
            '2026-09-13' => 3,
            '2026-09-14' => 3,
            '2026-09-15' => 3,
        ];

        $this->assertSame(0, StreakAtRisk::days($counted, 3, $this->today));
    }

    public function testTodayBelowTheThresholdStillCountsAsNotDone(): void
    {
        $counted = [
            '2026-09-14' => 3,
            '2026-09-15' => 1,
        ];

        $this->assertSame(1, StreakAtRisk::days($counted, 3, $this->today));
    }

    public function testStreakBrokenBeforeYesterdayIsNotAtRisk(): void
    {
        $counted = [
            '2026-09-10' => 3,
            '2026-09-11' => 3,
        ];

        $this->assertSame(0, StreakAtRisk::days($counted, 3, $this->today));
    }

    public function testChildWithoutAnyStreakIsLeftAlone(): void
    {
        $this->assertSame(0, StreakAtRisk::days([], 3, $this->today));
    }

    public function testDaysBelowTheThresholdDoNotBuildAStreak(): void
    {
        $counted = [
            '2026-09-13' => 1,
            '2026-09-14' => 2,
        ];

        $this->assertSame(0, StreakAtRisk::days($counted, 3, $this->today));
    }

    public function testOnlyTheRunEndingYesterdayIsCounted(): void
    {
        $counted = [
            '2026-09-01' => 3,
            '2026-09-02' => 3,
            '2026-09-03' => 3,
            '2026-09-13' => 3,
            '2026-09-14' => 3,
        ];

        $this->assertSame(2, StreakAtRisk::days($counted, 3, $this->today));
    }
}
