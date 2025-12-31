<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Query;

final readonly class GetAllBonusPointsRulesQuery
{
    public function __construct(
        public bool $activeOnly = false
    ) {
    }
}
