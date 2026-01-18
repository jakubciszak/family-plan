<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Command;

final readonly class CreateBonusPointsRuleCommand
{
    public function __construct(
        public string $id,
        public string $teamId,
        public string $name,
        public string $description,
        public int $bonusPoints,
        public string $ruleType,
        public array $ruleConfig
    ) {
    }
}
