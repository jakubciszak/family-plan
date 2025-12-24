<?php

declare(strict_types=1);

namespace App\PointsManagement\Infrastructure\Persistence;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\Repository\UserWalletRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory implementation of UserWalletRepository for testing
 */
final class InMemoryUserWalletRepository implements UserWalletRepositoryInterface
{
    /**
     * @var array<string, UserWallet>
     */
    private array $wallets = [];

    public function save(UserWallet $wallet): void
    {
        $this->wallets[$wallet->id()->value()] = $wallet;
    }

    public function findById(Uuid $id): ?UserWallet
    {
        return $this->wallets[$id->value()] ?? null;
    }

    public function findByUserId(Uuid $userId): ?UserWallet
    {
        foreach ($this->wallets as $wallet) {
            if ($wallet->userId()->equals($userId)) {
                return $wallet;
            }
        }
        
        return null;
    }

    public function delete(UserWallet $wallet): void
    {
        unset($this->wallets[$wallet->id()->value()]);
    }
}
