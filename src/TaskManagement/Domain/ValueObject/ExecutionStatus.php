<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use InvalidArgumentException;

enum ExecutionStatus: string
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
            default => throw new InvalidArgumentException(sprintf('Invalid execution status: %s', $value))
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
}
