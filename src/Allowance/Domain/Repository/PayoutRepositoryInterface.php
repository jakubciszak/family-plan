<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\Payout;
use App\Shared\Domain\ValueObject\Uuid;

interface PayoutRepositoryInterface
{
    public function find(Uuid $id): ?Payout;

    /**
     * @return Payout[]
     */
    public function awaitingConfirmation(Uuid $userId): array;

    /**
     * @return Payout[]
     */
    public function ofUser(Uuid $userId, int $limit = 50): array;

    public function save(Payout $payout): void;
}
