<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;

final class InMemoryBonusPointsRuleRepository implements BonusPointsRuleRepositoryInterface
{
    /** @var array<string, BonusPointsRule> */
    private array $rules = [];

    public function save(BonusPointsRule $rule): void
    {
        $this->rules[$rule->id()->value()] = $rule;
    }

    public function findById(Uuid $id): ?BonusPointsRule
    {
        return $this->rules[$id->value()] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->rules);
    }

    public function findActive(): array
    {
        return array_values(array_filter(
            $this->rules,
            fn(BonusPointsRule $rule) => $rule->isActive()
        ));
    }

    public function delete(BonusPointsRule $rule): void
    {
        unset($this->rules[$rule->id()->value()]);
    }
}
