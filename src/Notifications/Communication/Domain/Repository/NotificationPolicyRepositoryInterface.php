<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Domain\Repository;

use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;

interface NotificationPolicyRepositoryInterface
{
    public function save(NotificationPolicy $policy): void;

    public function findByEvent(NotificationEvent $event): ?NotificationPolicy;

    /**
     * @return NotificationPolicy[]
     */
    public function findAll(): array;
}
