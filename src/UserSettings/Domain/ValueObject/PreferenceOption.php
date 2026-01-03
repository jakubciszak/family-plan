<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

final readonly class PreferenceOption
{
    private function __construct(
        private string $name,
        private bool $enabled
    ) {
    }

    public static function create(string $name, bool $enabled): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Option name cannot be empty');
        }

        return new self($name, $enabled);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): self
    {
        return new self($this->name, true);
    }

    public function disable(): self
    {
        return new self($this->name, false);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'enabled' => $this->enabled,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['enabled']);
    }
}
