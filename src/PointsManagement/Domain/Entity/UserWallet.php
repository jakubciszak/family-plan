<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Entity;

use App\PointsManagement\Domain\Event\PointsAwarded;
use App\PointsManagement\Domain\Event\UserWalletCreated;
use App\PointsManagement\Domain\ValueObject\PointsBalance;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Summary account over the user's detail accounts in PointsManagement.
 * Its balance is derived - only PointsLedger may set it, by summing the detail accounts.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_wallets')]
#[ORM\Index(columns: ['user_id'])]
class UserWallet
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'uuid', unique: true)]
        private Uuid $userId,
        
        #[ORM\Column(type: 'integer')]
        private int $balance,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(Uuid $id, Uuid $userId, ClockInterface $clock): self
    {
        $now = $clock->now();
        $wallet = new self(
            $id,
            $userId,
            0, // Start with zero balance
            $now
        );

        $wallet->record(new UserWalletCreated($id, $userId, $now));

        return $wallet;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function balance(): PointsBalance
    {
        return PointsBalance::fromInt($this->balance);
    }

    /**
     * Restate the summary from the detail accounts.
     */
    public function summarise(int $total, ClockInterface $clock): void
    {
        if ($total === $this->balance) {
            return;
        }

        $difference = $total - $this->balance;
        $now = $clock->now();

        $this->balance = $total;
        $this->updatedAt = $now;

        if ($difference > 0) {
            $this->record(new PointsAwarded(
                $this->id,
                $this->userId,
                $difference,
                'Points booked in the ledger',
                $now
            ));
        }
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
