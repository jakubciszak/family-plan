<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use InvalidArgumentException;

enum TaskStatus: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function fromString(string $value): self
    {
        return match(strtolower($value)) {
            'new' => self::NEW,
            'pending' => self::PENDING,
            'completed' => self::COMPLETED,
            'approved' => self::APPROVED,
            'rejected' => self::REJECTED,
            default => throw new InvalidArgumentException(sprintf('Invalid task status: %s', $value))
        };
    }

    public function label(): string
    {
        return match($this) {
            self::NEW => 'New',
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function isNew(): bool
    {
        return $this === self::NEW;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }
}
