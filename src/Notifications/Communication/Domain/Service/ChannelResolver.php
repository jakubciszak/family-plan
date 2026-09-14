<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Domain\Service;

use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;

final readonly class ChannelResolver
{
    /**
     * @param array<string, bool> $userChannelSettings
     */
    public function resolve(NotificationChannels $policyChannels, array $userChannelSettings): NotificationChannels
    {
        return NotificationChannels::fromArray(array_filter(
            $policyChannels->toArray(),
            static fn(string $channel) => $userChannelSettings[$channel]
                ?? NotificationChannels::isEnabledByDefaultForUser($channel)
        ));
    }
}
