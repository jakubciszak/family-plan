<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;
use InvalidArgumentException;

final readonly class RuleConfig
{
    private function __construct(
        private RuleType $type,
        private ?Uuid $taskTemplateId,
        private ?int $requiredDays,
        private ?int $requiredCount
    ) {
        $this->validate();
    }

    public static function consecutiveDays(Uuid $taskTemplateId, int $requiredDays): self
    {
        if ($requiredDays < 2) {
            throw new InvalidArgumentException('Required days must be at least 2');
        }

        return new self(
            RuleType::CONSECUTIVE_DAYS,
            $taskTemplateId,
            $requiredDays,
            null
        );
    }

    public static function monthlyTaskCount(int $requiredCount): self
    {
        if ($requiredCount < 1) {
            throw new InvalidArgumentException('Required count must be at least 1');
        }

        return new self(
            RuleType::MONTHLY_TASK_COUNT,
            null,
            null,
            $requiredCount
        );
    }

    public function type(): RuleType
    {
        return $this->type;
    }

    public function taskTemplateId(): ?Uuid
    {
        return $this->taskTemplateId;
    }

    public function requiredDays(): ?int
    {
        return $this->requiredDays;
    }

    public function requiredCount(): ?int
    {
        return $this->requiredCount;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'taskTemplateId' => $this->taskTemplateId?->value(),
            'requiredDays' => $this->requiredDays,
            'requiredCount' => $this->requiredCount,
        ];
    }

    public static function fromArray(array $data): self
    {
        $type = RuleType::from($data['type']);

        return new self(
            $type,
            isset($data['taskTemplateId']) ? Uuid::fromString($data['taskTemplateId']) : null,
            $data['requiredDays'] ?? null,
            $data['requiredCount'] ?? null
        );
    }

    private function validate(): void
    {
        match ($this->type) {
            RuleType::CONSECUTIVE_DAYS => $this->validateConsecutiveDays(),
            RuleType::MONTHLY_TASK_COUNT => $this->validateMonthlyTaskCount(),
        };
    }

    private function validateConsecutiveDays(): void
    {
        if ($this->taskTemplateId === null) {
            throw new InvalidArgumentException('Task template ID is required for consecutive days rule');
        }

        if ($this->requiredDays === null || $this->requiredDays < 2) {
            throw new InvalidArgumentException('Required days must be at least 2');
        }
    }

    private function validateMonthlyTaskCount(): void
    {
        if ($this->requiredCount === null || $this->requiredCount < 1) {
            throw new InvalidArgumentException('Required count must be at least 1');
        }
    }
}
