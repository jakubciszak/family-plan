<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication\Domain;

use App\Notifications\Communication\Domain\Service\ChannelResolver;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use PHPUnit\Framework\TestCase;

class ChannelResolverTest extends TestCase
{
    private ChannelResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ChannelResolver();
    }

    public function testOnlyChannelsAllowedByThePolicyAndWantedByTheUserSurvive(): void
    {
        $resolved = $this->resolver->resolve(
            NotificationChannels::fromArray(['email', 'in_app']),
            ['email' => true, 'sms' => true, 'in_app' => false]
        );

        $this->assertSame(['email'], $resolved->toArray());
    }

    public function testChannelTheUserWantsButThePolicyForbidsIsDropped(): void
    {
        $resolved = $this->resolver->resolve(
            NotificationChannels::fromArray(['in_app']),
            ['email' => true, 'sms' => true, 'in_app' => true]
        );

        $this->assertSame(['in_app'], $resolved->toArray());
    }

    public function testEventWithNoChannelsReachesNobody(): void
    {
        $resolved = $this->resolver->resolve(
            NotificationChannels::none(),
            ['email' => true, 'in_app' => true]
        );

        $this->assertTrue($resolved->isEmpty());
    }

    public function testUserWithoutAnySettingsFallsBackToTheChannelDefaults(): void
    {
        $resolved = $this->resolver->resolve(
            NotificationChannels::fromArray(['email', 'sms', 'in_app']),
            []
        );

        $this->assertSame(['email', 'in_app'], $resolved->toArray());
    }

    public function testChannelMissingFromOlderPreferencesUsesItsDefault(): void
    {
        $resolved = $this->resolver->resolve(
            NotificationChannels::fromArray(['email', 'in_app']),
            ['email' => false, 'sms' => false]
        );

        $this->assertSame(['in_app'], $resolved->toArray());
    }

    public function testChannelsKeepAStableOrderRegardlessOfInput(): void
    {
        $resolved = $this->resolver->resolve(
            NotificationChannels::fromArray(['in_app', 'email']),
            ['email' => true, 'in_app' => true]
        );

        $this->assertSame(['email', 'in_app'], $resolved->toArray());
    }

    public function testUnsupportedChannelIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NotificationChannels::fromArray(['carrier_pigeon']);
    }
}
