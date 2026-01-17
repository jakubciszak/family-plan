<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Command;

final readonly class CreateStatusChangeRuleCommand
{
    public function __construct(
        public string $id,
        public string $teamId,
        public string $taskTemplateId,
        public string $name,
        public string $description,
        public string $conditionType,
        public array $conditionConfig
    ) {
    }
}
