<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\MoneyEntry;

interface MoneyEntryRepositoryInterface
{
    public function save(MoneyEntry $entry): void;

    /**
     * @param string[] $transactionIds
     * @return MoneyEntry[]
     */
    public function ofTransactions(array $transactionIds): array;
}
