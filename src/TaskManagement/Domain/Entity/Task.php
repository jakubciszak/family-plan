<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\TaskStatus;
use App\TaskManagement\Domain\Event\TaskCreated;
use App\TaskManagement\Domain\Event\TaskCompleted;
use App\TaskManagement\Domain\Event\TaskApproved;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['assigned_user_id'])]
class Task
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private Uuid $id,
        
        private TaskName $name,
        
        #[ORM\Column(type: 'text')]
        private string $description,
        
        private Points $points,
        
        private Frequency $frequency,
        
        private TaskStatus $status,
        
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $assignedUserId,
        
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $completedByUserId,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $completedAt,
        
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $approvedByAdminId,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $approvedAt,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(
        Uuid $id,
        TaskName $name,
        string $description,
        Points $points,
        Frequency $frequency,
        ?Uuid $assignedUserId = null
    ): self {
        $task = new self(
            $id,
            $name,
            $description,
            $points,
            $frequency,
            TaskStatus::PENDING,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $task->record(new TaskCreated($id, $name, $points, $frequency, new DateTimeImmutable()));

        return $task;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): TaskName
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function points(): Points
    {
        return $this->points;
    }

    public function frequency(): Frequency
    {
        return $this->frequency;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function assignedUserId(): ?Uuid
    {
        return $this->assignedUserId;
    }

    public function assignTo(Uuid $userId): void
    {
        $this->assignedUserId = $userId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markAsCompleted(Uuid $userId): void
    {
        $this->status = TaskStatus::COMPLETED;
        $this->completedByUserId = $userId;
        $this->completedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();

        $this->record(new TaskCompleted($this->id, $userId, $this->completedAt));
    }

    public function approve(Uuid $adminId): void
    {
        if (!$this->status->isCompleted()) {
            throw new \DomainException('Only completed tasks can be approved');
        }

        $this->status = TaskStatus::APPROVED;
        $this->approvedByAdminId = $adminId;
        $this->approvedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();

        $this->record(new TaskApproved($this->id, $adminId, $this->approvedAt));
    }

    public function reject(): void
    {
        if (!$this->status->isCompleted()) {
            throw new \DomainException('Only completed tasks can be rejected');
        }

        $this->status = TaskStatus::REJECTED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function update(TaskName $name, string $description, Points $points, Frequency $frequency): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->points = $points;
        $this->frequency = $frequency;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function approvedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
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

    // Doctrine mapping methods
    #[ORM\Column(type: 'string')]
    private function getNameForPersistence(): string
    {
        return $this->name->value();
    }

    private function setNameFromPersistence(string $name): void
    {
        $this->name = TaskName::fromString($name);
    }

    #[ORM\Column(type: 'integer')]
    private function getPointsForPersistence(): int
    {
        return $this->points->value();
    }

    private function setPointsFromPersistence(int $points): void
    {
        $this->points = Points::fromInt($points);
    }

    #[ORM\Column(type: 'string')]
    private function getFrequencyForPersistence(): string
    {
        return $this->frequency->value;
    }

    private function setFrequencyFromPersistence(string $frequency): void
    {
        $this->frequency = Frequency::fromString($frequency);
    }

    #[ORM\Column(type: 'string')]
    private function getStatusForPersistence(): string
    {
        return $this->status->value;
    }

    private function setStatusFromPersistence(string $status): void
    {
        $this->status = TaskStatus::fromString($status);
    }
}
