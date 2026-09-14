<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Double;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Service\BonusSettlementInterface;

final class SettlesNoBonus implements BonusSettlementInterface
{
    public function settleFor(Uuid $userId): void
    {
    }
}
