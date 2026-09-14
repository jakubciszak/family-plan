<?php

declare(strict_types=1);

namespace App\PointsManagement\Infrastructure\Persistence;

use App\PointsManagement\Domain\Entity\Account;
use App\PointsManagement\Domain\Repository\AccountRepositoryInterface;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryAccountRepository implements AccountRepositoryInterface
{
    /** @var array<string, Account> */
    private array $accounts = [];

    public function find(Uuid $userId, AccountKind $kind): ?Account
    {
        return $this->accounts[$this->key($userId, $kind)] ?? null;
    }

    public function ofUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->accounts,
            static fn (Account $account) => $account->userId()->equals($userId)
        ));
    }

    public function save(Account $account): void
    {
        $this->accounts[$this->key($account->userId(), $account->kind())] = $account;
    }

    private function key(Uuid $userId, AccountKind $kind): string
    {
        return $userId->value() . '|' . $kind->value;
    }
}
