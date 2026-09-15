<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

enum PushDelivery
{
    case Delivered;
    case Gone;
    case Failed;
}
