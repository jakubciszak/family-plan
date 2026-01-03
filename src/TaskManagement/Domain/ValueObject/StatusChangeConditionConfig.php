<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;
use InvalidArgumentException;

final readonly class StatusChangeConditionConfig
{
    private function __construct(
        private StatusChangeConditionType $type,
        private ?Uuid $requiredTaskTemplateId,
        private ?int $cooldownDays
    ) {
        $this->validate();
    }

    public static function otherTaskCompletedToday(Uuid $requiredTaskTemplateId): self
    {
        return new self(
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY,
            $requiredTaskTemplateId,
            null
        );
    }

    public static function lastExecutionCooldown(int $cooldownDays): self
    {
        if ($cooldownDays < 1) {
            throw new InvalidArgumentException('Cooldown days must be at least 1');
        }

        return new self(
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN,
            null,
            $cooldownDays
        );
    }

    public function type(): StatusChangeConditionType
    {
        return $this->type;
    }

    public function requiredTaskTemplateId(): ?Uuid
    {
        return $this->requiredTaskTemplateId;
    }

    public function cooldownDays(): ?int
    {
        return $this->cooldownDays;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'requiredTaskTemplateId' => $this->requiredTaskTemplateId?->value(),
            'cooldownDays' => $this->cooldownDays,
        ];
    }

    public static function fromArray(array $data): self
    {
        $type = StatusChangeConditionType::from($data['type']);

        return new self(
            $type,
            isset($data['requiredTaskTemplateId']) ? Uuid::fromString($data['requiredTaskTemplateId']) : null,
            $data['cooldownDays'] ?? null
        );
    }

    private function validate(): void
    {
        match ($this->type) {
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY => $this->validateOtherTaskCompletedToday(),
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN => $this->validateLastExecutionCooldown(),
        };
    }

    private function validateOtherTaskCompletedToday(): void
    {
        if ($this->requiredTaskTemplateId === null) {
            throw new InvalidArgumentException('Required task template ID is mandatory for other_task_completed_today condition');
        }
    }

    private function validateLastExecutionCooldown(): void
    {
        if ($this->cooldownDays === null || $this->cooldownDays < 1) {
            throw new InvalidArgumentException('Cooldown days must be at least 1');
        }
    }
}
