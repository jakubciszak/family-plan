<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\Event\PartyRoleStarted;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DomainException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'party_roles')]
#[ORM\Index(columns: ['party_id'])]
#[ORM\Index(columns: ['type'])]
class PartyRole
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\ManyToOne(targetEntity: Party::class)]
        #[ORM\JoinColumn(name: 'party_id', nullable: false)]
        private Party $party,

        #[ORM\Column(type: 'party_role_type', length: 50)]
        private PartyRoleType $type,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $startedAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $endedAt = null
    ) {
    }

    public static function start(Uuid $id, Party $party, PartyRoleType $type): self
    {
        if (!$type->isPlayedBy($party->type())) {
            throw new DomainException(sprintf(
                'A %s cannot play the role %s',
                $party->type()->value(),
                $type->value()
            ));
        }

        $role = new self($id, $party, $type, new DateTimeImmutable());
        $role->record(new PartyRoleStarted($id, $party->id(), $type, $role->startedAt));

        return $role;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function party(): Party
    {
        return $this->party;
    }

    public function partyId(): Uuid
    {
        return $this->party->id();
    }

    public function type(): PartyRoleType
    {
        return $this->type;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }

    public function end(): void
    {
        if ($this->endedAt !== null) {
            throw new DomainException('This role has already ended');
        }

        $this->endedAt = new DateTimeImmutable();
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
