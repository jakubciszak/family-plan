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

    public function find(Uuid $userId, AccountKind $kind, ?Uuid $teamId = null): ?Account
    {
        return $this->accounts[$this->key($userId, $kind, $teamId)] ?? null;
    }

    public function ofUser(Uuid $userId, ?Uuid $teamId = null): array
    {
        return array_values(array_filter(
            $this->accounts,
            static fn (Account $account) => $account->userId()->equals($userId) && $account->teamId()?->value() === $teamId?->value()
        ));
    }

    public function save(Account $account): void
    {
        $this->accounts[$this->key($account->userId(), $account->kind(), $account->teamId())] = $account;
    }

    private function key(Uuid $userId, AccountKind $kind, ?Uuid $teamId = null): string
    {
        return ($teamId?->value() ?? '') . '|' . $userId->value() . '|' . $kind->value;
    }
}
