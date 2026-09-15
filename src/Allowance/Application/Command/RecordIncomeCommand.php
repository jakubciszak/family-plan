<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class RecordIncomeCommand
{
    public function __construct(
        public string $id,
        public string $userId,
        public int $amount,
        public string $description,
        public ?string $on
    ) {
    }
}
