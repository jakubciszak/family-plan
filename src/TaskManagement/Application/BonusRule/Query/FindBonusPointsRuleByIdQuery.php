<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\BonusRule\Query;

final readonly class FindBonusPointsRuleByIdQuery
{
    public function __construct(
        public string $id
    ) {
    }
}
