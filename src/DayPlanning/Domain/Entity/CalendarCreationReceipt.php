<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'day_planning_idempotency')]
class CalendarCreationReceipt
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $ownerId,
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $requestKey,
        #[ORM\Column(length: 64)]
        private string $requestHash,
        #[ORM\Column(type: 'json')]
        private array $response,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
    }
}
