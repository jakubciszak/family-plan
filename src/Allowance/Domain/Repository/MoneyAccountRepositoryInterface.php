<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\MoneyAccount;
use App\Allowance\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;

interface MoneyAccountRepositoryInterface
{
    public function find(Uuid $userId, AccountKind $kind, ?Uuid $reference = null): ?MoneyAccount;

    /**
     * @return MoneyAccount[]
     */
    public function ofUser(Uuid $userId): array;

    public function save(MoneyAccount $account): void;
}
