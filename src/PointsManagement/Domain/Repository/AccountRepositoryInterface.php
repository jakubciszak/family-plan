<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Repository;

use App\PointsManagement\Domain\Entity\Account;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;

interface AccountRepositoryInterface
{
    public function find(Uuid $userId, AccountKind $kind): ?Account;

    /**
     * @return Account[]
     */
    public function ofUser(Uuid $userId): array;

    public function save(Account $account): void;
}
