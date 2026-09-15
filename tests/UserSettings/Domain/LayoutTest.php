<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\UserSettings\Domain\ValueObject\Layout;
use DomainException;
use PHPUnit\Framework\TestCase;

class LayoutTest extends TestCase
{
    private const PLACES = ['week', 'tasks', 'standings'];

    public function testAnArrangementKeepsTheOrderItWasGiven(): void
    {
        $layout = Layout::of(self::PLACES, ['standings', 'week']);

        $this->assertSame(['standings', 'week'], $layout->order());
    }

    public function testWhatIsLeftOutIsRememberedAsHidden(): void
    {
        $layout = Layout::of(self::PLACES, ['week']);

        $this->assertSame(['tasks', 'standings'], $layout->hidden());
        $this->assertFalse($layout->shows('tasks'));
        $this->assertTrue($layout->shows('week'));
    }

    public function testTheSamePlaceTwiceCountsOnce(): void
    {
        $layout = Layout::of(self::PLACES, ['week', 'week', 'tasks']);

        $this->assertSame(['week', 'tasks'], $layout->order());
    }

    public function testAPlaceNobodyKnowsCannotBeArranged(): void
    {
        $this->expectException(DomainException::class);

        Layout::of(self::PLACES, ['week', 'pogoda']);
    }

    public function testEverythingIsShownUntilSomebodySaysOtherwise(): void
    {
        $this->assertSame(self::PLACES, Layout::everything(self::PLACES)->order());
    }
}
