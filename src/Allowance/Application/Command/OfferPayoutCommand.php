<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class OfferPayoutCommand
{
    public function __construct(
        public string $id,
        public string $userId,
        public int $amount,
        public string $offeredBy,
        public ?string $note
    ) {
    }
}
