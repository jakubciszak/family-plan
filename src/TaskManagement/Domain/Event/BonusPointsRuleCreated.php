<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleType;
use DateTimeImmutable;

final readonly class BonusPointsRuleCreated
{
    public function __construct(
        public Uuid $ruleId,
        public string $name,
        public Points $bonusPoints,
        public RuleType $type,
        public DateTimeImmutable $occurredAt
    ) {
    }
}
