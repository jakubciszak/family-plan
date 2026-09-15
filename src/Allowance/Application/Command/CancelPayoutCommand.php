<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class CancelPayoutCommand
{
    public function __construct(
        public string $payoutId
    ) {
    }
}
