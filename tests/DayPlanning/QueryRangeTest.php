<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\ValueObject\QueryRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryRangeTest extends TestCase
{
    #[DataProvider('invalidRanges')]
    public function testInvalidOrAmbiguousQueryTimesAreRejected(mixed $from, mixed $to): void
    {
        $this->expectException(PlanningException::class);
        QueryRange::fromStrings($from, $to);
    }

    public static function invalidRanges(): array
    {
        return [
            ['2026-09-21', '2026-09-22'],
            ['2026-09-21T08:00', '2026-09-21T09:00'],
            ['2026-09-21T08:00:00+99:99', '2026-09-22T09:00:00Z'],
            ['2026-02-30T08:00:00Z', '2026-03-02T09:00:00Z'],
            ['2026-09-21T25:00:00Z', '2026-09-22T09:00:00Z'],
            ['2026-09-21T08:00:00Z', '2026-09-21T08:00:00Z'],
            ['2026-09-21T08:00:00Z', '2026-09-20T08:00:00Z'],
            ['2026-09-21T08:00:00Z', '2027-09-21T08:00:00Z'],
            [[], '2026-09-22T08:00:00Z'],
        ];
    }

    public function testEquivalentOffsetsNormalizeToUtc(): void
    {
        $range = QueryRange::fromStrings('2026-09-21T08:00:00+02:00', '2026-09-21T09:00:00+02:00');
        self::assertSame(['from' => '2026-09-21T06:00:00+00:00', 'to' => '2026-09-21T07:00:00+00:00', 'complete' => true], $range->coverage());
    }
}
