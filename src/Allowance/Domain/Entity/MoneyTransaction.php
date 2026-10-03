<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Money only ever moves between two accounts, so every transaction balances out to nothing.
 */
#[ORM\Entity]
#[ORM\Table(name: 'allowance_transactions')]
#[ORM\Index(columns: ['user_id', 'booked_at'])]
class MoneyTransaction
{
    /** @var MoneyEntry[] */
    #[ORM\Transient]
    private array $entries = [];

    #[ORM\Column(type: 'string', length: 36)]
    private string $teamId = '';

    public function teamId(): ?Uuid
    {
        return $this->teamId === '' ? null : Uuid::fromString($this->teamId);
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 30, enumType: TransactionType::class)]
        private TransactionType $type,

        #[ORM\Column(type: 'string', length: 255)]
        private string $description,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $bookedAt,

        #[ORM\Column(type: 'string', length: 36, nullable: true)]
        private ?string $reference,

        #[ORM\Column(type: 'json', nullable: true)]
        private ?array $context = null
    ) {
    }

    public static function open(
        Uuid $id,
        Uuid $userId,
        TransactionType $type,
        string $description,
        DateTimeImmutable $bookedAt,
        ?Uuid $reference = null,
        array $context = [], ?Uuid $teamId = null): self {
        $entity = new self($id, $userId, $type, $description, $bookedAt, $reference?->value(), $context ?: null);
        $entity->teamId = $teamId?->value() ?? '';

        return $entity;
    }

    public function transfer(MoneyAccount $from, MoneyAccount $to, Money $amount, ClockInterface $clock): void
    {
        $amount->assertPositive('A transfer');

        foreach ([$from, $to] as $account) {
            if (!$account->userId()->equals($this->userId) || $account->teamId()?->value() !== $this->teamId()?->value()) {
                throw new \DomainException('Accounts must belong to the transaction owner and household');
            }
        }

        if ($from->id()->equals($to->id())) {
            throw new \DomainException('Money cannot be moved onto the account it came from');
        }

        $this->book($from, $amount->negated(), $clock);
        $this->book($to, $amount, $clock);
    }

    public function seal(): void
    {
        if ($this->entries === []) {
            throw new \DomainException('A transaction without entries moves nothing');
        }

        $sum = array_reduce(
            $this->entries,
            static fn (Money $carried, MoneyEntry $entry) => $carried->plus($entry->amount()),
            Money::zero()
        );

        if (!$sum->isZero()) {
            throw new \DomainException('A transaction has to balance out to nothing');
        }
    }

    /**
     * @return MoneyEntry[]
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function type(): TransactionType
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function bookedAt(): DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function context(): array
    {
        return $this->context ?? [];
    }

    private function book(MoneyAccount $account, Money $amount, ClockInterface $clock): void
    {
        $entry = MoneyEntry::record(Uuid::generate(), $this->id, $account->id(), $amount, $this->bookedAt);

        $account->post($entry, $clock);

        $this->entries[] = $entry;
    }
}
