<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;

final class InMemoryStatusChangeRuleRepository implements StatusChangeRuleRepositoryInterface
{
    /** @var array<string, StatusChangeRule> */
    private array $rules = [];

    public function save(StatusChangeRule $rule): void
    {
        $this->rules[$rule->id()->value()] = $rule;
    }

    public function findById(Uuid $id): ?StatusChangeRule
    {
        return $this->rules[$id->value()] ?? null;
    }

    public function findAll(bool $activeOnly = false): array
    {
        $rules = array_values($this->rules);

        if ($activeOnly) {
            $rules = array_values(array_filter($rules, fn(StatusChangeRule $rule) => $rule->isActive()));
        }

        return $rules;
    }

    public function findActiveByTaskTemplateId(Uuid $taskTemplateId): array
    {
        return array_values(array_filter(
            $this->rules,
            fn(StatusChangeRule $rule) => 
                $rule->taskTemplateId()->equals($taskTemplateId) && $rule->isActive()
        ));
    }
}
