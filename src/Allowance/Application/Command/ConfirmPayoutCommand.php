<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class ConfirmPayoutCommand
{
    public function __construct(
        public string $payoutId,
        public string $userId
    ) {
    }
}
