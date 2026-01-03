<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Command;

final readonly class UpdateStatusChangeRuleCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description
    ) {
    }
}
