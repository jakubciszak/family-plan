<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Application\Command;

final readonly class UpdateNotificationPolicyCommand
{
    /**
     * @param list<string> $channels
     */
    public function __construct(
        public string $event,
        public array $channels
    ) {
    }
}
