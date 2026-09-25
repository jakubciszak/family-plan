<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command;

/**
 * Phones of these people drop the tray entries with these tags: what they said is no longer true.
 */
final readonly class RetractPushCommand
{
    /**
     * @param list<string> $userIds
     * @param list<string> $tags
     */
    public function __construct(
        public array $userIds,
        public array $tags
    ) {
    }
}
