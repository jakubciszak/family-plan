<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\WeekClosure;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface WeekClosureRepositoryInterface
{
    public function find(Uuid $userId, WeekStart $week): ?WeekClosure;

    /**
     * @return WeekClosure[]
     */
    public function ofUserBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    public function save(WeekClosure $closure): void;

    public function remove(WeekClosure $closure): void;
}
