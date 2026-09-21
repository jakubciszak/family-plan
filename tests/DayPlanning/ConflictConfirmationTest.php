<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Application\Service\ConflictConfirmation;
use App\DayPlanning\Domain\Service\BusyIntervals;
use PHPUnit\Framework\TestCase;

final class ConflictConfirmationTest extends TestCase
{
    public function testConfirmationCannotBeReusedForAnotherCallerPayloadOrBusyState(): void
    {
        $confirmation = new ConflictConfirmation('test-secret');
        $data = ['title' => 'Spotkanie', 'start' => '2026-09-21T08:00'];
        $busy = [['personId' => 'anna', 'start' => '2026-09-21T08:00', 'end' => '2026-09-21T09:00']];
        $token = $confirmation->issue('anna', $data, $busy);
        self::assertTrue($confirmation->valid($token, 'anna', $data, $busy));
        self::assertFalse($confirmation->valid($token, 'bartek', $data, $busy));
        self::assertFalse($confirmation->valid($token, 'anna', ['title' => 'Inne'] + $data, $busy));
        self::assertFalse($confirmation->valid($token, 'anna', $data, []));
        self::assertFalse($confirmation->valid($token.'0', 'anna', $data, $busy));
    }

    public function testOverlappingAndAdjacentPrivateIntervalsMergeWithoutSourceIdentifiers(): void
    {
        $busy = (new BusyIntervals())->merge([
            ['personId' => 'anna', 'start' => '2026-09-21T09:00Z', 'end' => '2026-09-21T10:00Z'],
            ['personId' => 'anna', 'start' => '2026-09-21T08:00Z', 'end' => '2026-09-21T09:00Z'],
            ['personId' => 'bartek', 'start' => '2026-09-21T08:00Z', 'end' => '2026-09-21T09:00Z'],
        ]);
        self::assertCount(2, $busy);
        self::assertSame(['kind' => 'busy', 'personId' => 'anna', 'start' => '2026-09-21T08:00Z', 'end' => '2026-09-21T10:00Z'], $busy[0]);
    }
}
