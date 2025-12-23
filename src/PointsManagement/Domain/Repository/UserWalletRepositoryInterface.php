<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Repository;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Repository interface for UserWallet aggregate
 */
interface UserWalletRepositoryInterface
{
    public function save(UserWallet $wallet): void;

    public function findById(Uuid $id): ?UserWallet;

    public function findByUserId(Uuid $userId): ?UserWallet;

    public function delete(UserWallet $wallet): void;
}
