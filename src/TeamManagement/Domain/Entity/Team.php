<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Event\TeamCreated;
use App\TeamManagement\Domain\Event\MemberInvited;
use App\TeamManagement\Domain\Event\MemberAdded;
use App\TeamManagement\Domain\Event\MemberRemoved;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'teams')]
class Team
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'string', length: 255)]
        private TeamName $name,
        
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description,
        
        #[ORM\Column(type: 'uuid')]
        private Uuid $createdBy,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(
        Uuid $id,
        TeamName $name,
        ?string $description,
        Uuid $createdBy
    ): self {
        $team = new self(
            $id,
            $name,
            $description,
            $createdBy,
            new DateTimeImmutable()
        );

        $team->record(new TeamCreated($id, $name, $createdBy, new DateTimeImmutable()));

        return $team;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): TeamName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateName(TeamName $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new DateTimeImmutable();
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
