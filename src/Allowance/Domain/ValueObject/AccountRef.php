<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class AccountRef
{
    private function __construct(
        private AccountKind $kind,
        private ?Uuid $reference
    ) {
    }

    public static function of(AccountKind $kind): self
    {
        return new self($kind, null);
    }

    public static function pending(): self
    {
        return new self(AccountKind::PENDING, null);
    }

    public static function available(): self
    {
        return new self(AccountKind::AVAILABLE, null);
    }

    public static function earnings(): self
    {
        return new self(AccountKind::EARNINGS, null);
    }

    public static function income(): self
    {
        return new self(AccountKind::INCOME, null);
    }

    public static function expenses(): self
    {
        return new self(AccountKind::EXPENSES, null);
    }

    public static function goal(Uuid $goalId): self
    {
        return new self(AccountKind::GOAL, $goalId);
    }

    public function kind(): AccountKind
    {
        return $this->kind;
    }

    public function reference(): ?Uuid
    {
        return $this->reference;
    }
}
