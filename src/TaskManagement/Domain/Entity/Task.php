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

class Task
{
    private array $domainEvents = [];

    private function __construct(
        private Uuid $id,
        private TaskName $name,
        private string $description,
        private Points $points,
        private Frequency $frequency,
        private TaskStatus $status,
        private ?Uuid $assignedUserId,
        private ?Uuid $completedByUserId,
        private ?DateTimeImmutable $completedAt,
        private ?Uuid $approvedByAdminId,
        private ?DateTimeImmutable $approvedAt,
        private DateTimeImmutable $createdAt,
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
}
