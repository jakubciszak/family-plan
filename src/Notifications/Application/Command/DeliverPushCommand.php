<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command;

final readonly class DeliverPushCommand
{
    public function __construct(
        public string $userId,
        public string $message,
        public ?string $subject = null,
        public array $additionalParameters = []
    ) {
    }
}
