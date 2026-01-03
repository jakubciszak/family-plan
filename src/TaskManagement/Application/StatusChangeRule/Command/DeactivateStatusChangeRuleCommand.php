<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Command;

final readonly class DeactivateStatusChangeRuleCommand
{
    public function __construct(
        public string $id
    ) {
    }
}
