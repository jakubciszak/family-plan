<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Command;

final readonly class UpdateBonusPointsRuleCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $bonusPoints
    ) {
    }
}
