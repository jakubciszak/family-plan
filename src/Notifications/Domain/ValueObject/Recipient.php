<?php

declare(strict_types=1);

namespace App\Notifications\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class Recipient
{
    private const EMAIL = 'email';
    private const PHONE_NUMBER = 'phone_number';
    private const USER_ID = 'user_id';

    private function __construct(
        private string $value,
        private string $type
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

        return new self($email, self::EMAIL);
    }

    public static function phoneNumber(string $phoneNumber): self
    {
        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException('Phone number cannot be empty');
        }

        return new self($phoneNumber, self::PHONE_NUMBER);
    }

    public static function userId(string $userId): self
    {
        if (empty($userId)) {
            throw new \InvalidArgumentException('User id cannot be empty');
        }

        if (!Uuid::isValid($userId)) {
            throw new \InvalidArgumentException('Invalid user id');
        }

        return new self($userId, self::USER_ID);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmail(): bool
    {
        return $this->type === self::EMAIL;
    }

    public function isPhoneNumber(): bool
    {
        return $this->type === self::PHONE_NUMBER;
    }

    public function isUserId(): bool
    {
        return $this->type === self::USER_ID;
    }
}
