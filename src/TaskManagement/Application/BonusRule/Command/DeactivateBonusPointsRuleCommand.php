<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Command;

final readonly class DeactivateBonusPointsRuleCommand
{
    public function __construct(
        public string $id
    ) {
    }
}
