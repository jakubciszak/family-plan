<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Query;

final readonly class FindStatusChangeRuleByIdQuery
{
    public function __construct(
        public string $id
    ) {
    }
}
