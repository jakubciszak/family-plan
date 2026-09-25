<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Domain;

use App\Notifications\Domain\ValueObject\PushOptions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PushOptionsTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-25 18:00:00+00:00');
    }

    public function testWithoutAnythingSaidItIsKeptTwelveHours(): void
    {
        $options = PushOptions::from([], $this->now);

        $this->assertSame(43200, $options->ttl);
        $this->assertNull($options->tag);
        $this->assertNull($options->topic());
        $this->assertSame('normal', $options->urgency);
    }

    public function testItNeverOutlivesWhatItSays(): void
    {
        $options = PushOptions::from(['ttl' => 86400, 'expires_at' => '2026-09-25T19:30:00+00:00'], $this->now);

        $this->assertSame(5400, $options->ttl);
    }

    public function testAnAlmostExpiredOneStillGetsAMinute(): void
    {
        $options = PushOptions::from(['expires_at' => '2026-09-25T18:00:10+00:00'], $this->now);

        $this->assertSame(60, $options->ttl);
    }

    public function testTheTopicFitsWhatPushServicesAccept(): void
    {
        $first = PushOptions::from(['tag' => 'task-0b9d1f6e-5b7a-4d52-9d61-1c8b2f0f2a11'], $this->now);
        $again = PushOptions::from(['tag' => 'task-0b9d1f6e-5b7a-4d52-9d61-1c8b2f0f2a11'], $this->now);
        $other = PushOptions::from(['tag' => 'task-2'], $this->now);

        $this->assertSame(32, strlen((string) $first->topic()));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/', (string) $first->topic());
        $this->assertSame($first->topic(), $again->topic());
        $this->assertNotSame($first->topic(), $other->topic());
    }

    public function testOnlyKnownUrgenciesPass(): void
    {
        $this->assertSame('high', PushOptions::from(['urgency' => 'high'], $this->now)->urgency);
        $this->assertSame('normal', PushOptions::from(['urgency' => 'panic'], $this->now)->urgency);
    }

    public function testItIsOutdatedOnceItExpired(): void
    {
        $this->assertTrue(PushOptions::isOutdated(['expires_at' => '2026-09-25T17:59:00+00:00'], $this->now));
        $this->assertFalse(PushOptions::isOutdated(['expires_at' => '2026-09-25T18:01:00+00:00'], $this->now));
    }

    public function testItIsOutdatedAfterWaitingLongerThanAPushServiceWouldKeepIt(): void
    {
        $this->assertTrue(PushOptions::isOutdated(['ttl' => 600, 'queued_at' => '2026-09-25T17:40:00+00:00'], $this->now));
        $this->assertFalse(PushOptions::isOutdated(['ttl' => 3600, 'queued_at' => '2026-09-25T17:40:00+00:00'], $this->now));
        $this->assertFalse(PushOptions::isOutdated([], $this->now));
    }
}
