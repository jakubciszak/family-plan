<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\StatusChangeRule;

interface StatusChangeRuleRepositoryInterface
{
    public function save(StatusChangeRule $rule): void;

    public function findById(Uuid $id): ?StatusChangeRule;

    /**
     * @return StatusChangeRule[]
     */
    public function findAll(bool $activeOnly = false): array;

    /**
     * Find all active rules for a specific task template
     * @return StatusChangeRule[]
     */
    public function findActiveByTaskTemplateId(Uuid $taskTemplateId): array;
}
