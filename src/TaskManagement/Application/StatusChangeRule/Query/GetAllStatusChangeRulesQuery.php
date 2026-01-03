<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Query;

final readonly class GetAllStatusChangeRulesQuery
{
    public function __construct(
        public bool $activeOnly = false,
        public ?string $taskTemplateId = null
    ) {
    }
}
