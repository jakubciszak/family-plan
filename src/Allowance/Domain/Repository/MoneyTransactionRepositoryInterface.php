<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\MoneyTransaction;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface MoneyTransactionRepositoryInterface
{
    public function save(MoneyTransaction $transaction): void;

    public function find(Uuid $id): ?MoneyTransaction;

    /**
     * @return MoneyTransaction[]
     */
    public function ofUser(Uuid $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, int $limit = 100): array;
}
