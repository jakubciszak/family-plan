<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use DateTimeImmutable;

final readonly class StatusChangeRuleCreated
{
    public function __construct(
        public Uuid $ruleId,
        public Uuid $taskTemplateId,
        public string $name,
        public StatusChangeConditionType $conditionType,
        public DateTimeImmutable $occurredAt
    ) {
    }
}
