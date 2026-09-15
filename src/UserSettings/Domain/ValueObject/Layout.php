<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

use DomainException;

/**
 * An ordered pick of named places, kept as the owner arranged them.
 */
final readonly class Layout
{
    /**
     * @param string[] $order
     */
    private function __construct(
        private array $order,
        private array $known
    ) {
    }

    /**
     * @param string[] $known
     * @param string[] $order
     */
    public static function of(array $known, array $order): self
    {
        $wanted = [];

        foreach ($order as $name) {
            if (!is_string($name) || !in_array($name, $known, true)) {
                throw new DomainException(sprintf('There is no place called "%s" to arrange', is_string($name) ? $name : '?'));
            }

            if (!in_array($name, $wanted, true)) {
                $wanted[] = $name;
            }
        }

        return new self($wanted, $known);
    }

    /**
     * @param string[] $known
     */
    public static function everything(array $known): self
    {
        return new self(array_values($known), $known);
    }

    /**
     * @return string[]
     */
    public function order(): array
    {
        return $this->order;
    }

    /**
     * @return string[] places the owner left out
     */
    public function hidden(): array
    {
        return array_values(array_diff($this->known, $this->order));
    }

    public function shows(string $name): bool
    {
        return in_array($name, $this->order, true);
    }
}
