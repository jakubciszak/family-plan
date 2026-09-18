<?php

declare(strict_types=1);

namespace App\Tests\ActionPlanning;

use App\ActionPlanning\Domain\Entity\ActionPlan;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActionPlanTest extends TestCase
{
    public function testSavesOrderedStepsAndStagesWithoutARequiredEstimate(): void
    {
        $plan = ActionPlan::create(Uuid::generate(), Uuid::generate(), ' Room ', [
            ['name' => ' Bed ', 'stages' => []],
            ['name' => 'Wardrobe', 'stages' => [' Take off LEGO ', 'Dust shelf', 'Put LEGO back']],
        ], null, 10);

        self::assertSame('Room', $plan->describe()['name']);
        self::assertSame('Bed', $plan->describe()['steps'][0]['name']);
        self::assertSame(['Take off LEGO', 'Dust shelf', 'Put LEGO back'], $plan->describe()['steps'][1]['stages']);
        self::assertNull($plan->describe()['estimatedMinutes']);
        self::assertSame(10, $plan->describe()['reminderMinutes']);
    }

    public function testFailedRevisionDoesNotPartiallyChangeThePlan(): void
    {
        $plan = ActionPlan::create(Uuid::generate(), Uuid::generate(), 'Room', [['name' => 'Bed', 'stages' => []]], 30, 10);
        $before = $plan->describe();
        try {
            $plan->revise('Kitchen', [['name' => 'Sink', 'stages' => ['']]], 15, 5);
            self::fail('Expected invalid stage to be rejected');
        } catch (\DomainException) {
            self::assertSame($before, $plan->describe());
        }
    }

    #[DataProvider('invalidPlans')]
    public function testRejectsInvalidPlans(string $name, array $steps, ?int $duration, int $reminder): void
    {
        $this->expectException(\DomainException::class);
        ActionPlan::create(Uuid::generate(), Uuid::generate(), $name, $steps, $duration, $reminder);
    }

    public static function invalidPlans(): iterable
    {
        $steps = [['name' => 'Bed', 'stages' => []]];
        yield 'blank name' => ['  ', $steps, null, 10];
        yield 'long name' => [str_repeat('ą', 161), $steps, null, 10];
        yield 'no steps' => ['Room', [], null, 10];
        yield 'blank stage' => ['Room', [['name' => 'Bed', 'stages' => [' ']]], null, 10];
        yield 'nested object' => ['Room', [['name' => 'Bed', 'stages' => [['name' => 'Pillow']]]], null, 10];
        yield 'malformed step' => ['Room', ['Bed'], null, 10];
        yield 'missing stages' => ['Room', [['name' => 'Bed']], null, 10];
        yield 'non-list steps' => ['Room', ['bed' => $steps[0]], null, 10];
        yield 'zero duration' => ['Room', $steps, 0, 10];
        yield 'negative duration' => ['Room', $steps, -1, 10];
        yield 'excessive duration' => ['Room', $steps, 1441, 10];
        yield 'zero interval' => ['Room', $steps, null, 0];
        yield 'excessive interval' => ['Room', $steps, null, 121];
        yield 'excessive activities' => ['Room', array_fill(0, 6, ['name' => 'Shelf', 'stages' => array_fill(0, 100, 'Dust')]), null, 10];
    }
}
