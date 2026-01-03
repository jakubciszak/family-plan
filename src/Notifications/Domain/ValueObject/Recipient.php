<?php

declare(strict_types=1);

namespace App\Notifications\Domain\ValueObject;

final readonly class Recipient
{
    private function __construct(
        private string $value,
        private bool $isEmail
    ) {
    }

    public static function email(string $email): self
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email address cannot be empty');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }

        return new self($email, true);
    }

    public static function phoneNumber(string $phoneNumber): self
    {
        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException('Phone number cannot be empty');
        }

        return new self($phoneNumber, false);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmail(): bool
    {
        return $this->isEmail;
    }

    public function isPhoneNumber(): bool
    {
        return !$this->isEmail;
    }
}
