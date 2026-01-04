<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ValueObject\InvitationStatus;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'team_invitations')]
#[ORM\Index(columns: ['team_id'])]
#[ORM\Index(columns: ['email'])]
#[ORM\Index(columns: ['status'])]
class TeamInvitation
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'uuid')]
        private Uuid $teamId,
        
        #[ORM\Column(type: 'email')]
        private Email $email,
        
        #[ORM\Column(type: 'string', length: 50)]
        private TeamRole $role,
        
        #[ORM\Column(type: 'string', length: 50)]
        private InvitationStatus $status,
        
        #[ORM\Column(type: 'uuid')]
        private Uuid $invitedBy,
        
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $token,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $expiresAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $respondedAt = null
    ) {
    }

    public static function create(
        Uuid $id,
        Uuid $teamId,
        Email $email,
        TeamRole $role,
        Uuid $invitedBy,
        int $expiresInDays = 7
    ): self {
        $token = bin2hex(random_bytes(32));
        $createdAt = new DateTimeImmutable();
        $expiresAt = $createdAt->modify("+{$expiresInDays} days");
        
        return new self(
            $id,
            $teamId,
            $email,
            $role,
            InvitationStatus::pending(),
            $invitedBy,
            $token,
            $createdAt,
            $expiresAt
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function teamId(): Uuid
    {
        return $this->teamId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function role(): TeamRole
    {
        return $this->role;
    }

    public function status(): InvitationStatus
    {
        return $this->status;
    }

    public function invitedBy(): Uuid
    {
        return $this->invitedBy;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function respondedAt(): ?DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function accept(): void
    {
        if (!$this->status->isPending()) {
            throw new \DomainException('Only pending invitations can be accepted');
        }

        if ($this->isExpired()) {
            throw new \DomainException('Invitation has expired');
        }

        $this->status = InvitationStatus::accepted();
        $this->respondedAt = new DateTimeImmutable();
    }

    public function reject(): void
    {
        if (!$this->status->isPending()) {
            throw new \DomainException('Only pending invitations can be rejected');
        }

        $this->status = InvitationStatus::rejected();
        $this->respondedAt = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return new DateTimeImmutable() > $this->expiresAt;
    }

    public function markAsExpired(): void
    {
        if ($this->status->isPending()) {
            $this->status = InvitationStatus::expired();
        }
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
