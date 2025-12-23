<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Entity;

use App\PointsManagement\Domain\Event\PointsAwarded;
use App\PointsManagement\Domain\Event\UserWalletCreated;
use App\PointsManagement\Domain\ValueObject\PointsBalance;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * UserWallet aggregate - represents a user's points account (Wallet/Account archetypal pattern)
 * This is a separate aggregate from User, following DDD principles
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

    public static function create(Uuid $id, Uuid $userId): self
    {
        $wallet = new self(
            $id,
            $userId,
            0, // Start with zero balance
            new DateTimeImmutable()
        );

        $wallet->record(new UserWalletCreated($id, $userId, new DateTimeImmutable()));

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
     * Award points to the wallet (credit operation)
     */
    public function awardPoints(int $points, string $reason): void
    {
        if ($points <= 0) {
            throw new \DomainException('Points to award must be positive');
        }

        $newBalance = $this->balance()->add($points);
        $this->balance = $newBalance->value();
        $this->updatedAt = new DateTimeImmutable();

        $this->record(new PointsAwarded(
            $this->id,
            $this->userId,
            $points,
            $reason,
            new DateTimeImmutable()
        ));
    }

    /**
     * Deduct points from the wallet (debit operation)
     */
    public function deductPoints(int $points, string $reason): void
    {
        if ($points <= 0) {
            throw new \DomainException('Points to deduct must be positive');
        }

        $newBalance = $this->balance()->subtract($points);
        $this->balance = $newBalance->value();
        $this->updatedAt = new DateTimeImmutable();
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
