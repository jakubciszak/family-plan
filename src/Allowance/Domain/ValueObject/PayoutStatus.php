<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

enum PayoutStatus: string
{
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
