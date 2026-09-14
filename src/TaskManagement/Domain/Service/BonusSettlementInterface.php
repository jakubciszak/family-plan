<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;

interface BonusSettlementInterface
{
    public function settleFor(Uuid $userId): void;
}
