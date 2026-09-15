<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

final readonly class Settlement
{
    /**
     * @param SettlementLine[] $lines
     */
    private function __construct(private array $lines)
    {
    }

    /**
     * @param SettlementLine[] $lines
     */
    public static function of(array $lines): self
    {
        return new self(array_values($lines));
    }

    public static function nothing(): self
    {
        return new self([]);
    }

    /**
     * @return SettlementLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function total(): Money
    {
        return array_reduce(
            $this->lines,
            static fn (Money $carried, SettlementLine $line) => $carried->plus($line->amount()),
            Money::zero()
        );
    }

    public function points(): int
    {
        return array_sum(array_map(static fn (SettlementLine $line) => $line->points(), $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->total()->isZero();
    }

    public function toArray(): array
    {
        return array_map(static fn (SettlementLine $line) => $line->toArray(), $this->lines);
    }
}
