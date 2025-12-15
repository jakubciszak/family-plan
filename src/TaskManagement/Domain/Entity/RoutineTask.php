<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\Event\RoutineTaskCreated;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'routine_tasks')]
#[ORM\Index(columns: ['is_active'])]
#[ORM\Index(columns: ['assigned_user_id'])]
class RoutineTask
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'task_name')]
        private TaskName $name,
        
        #[ORM\Column(type: 'text')]
        private string $description,
        
        #[ORM\Column(type: 'points')]
        private Points $points,
        
        #[ORM\Column(type: 'frequency')]
        private Frequency $frequency,
        
        #[ORM\Column(type: 'schedule_config')]
        private ScheduleConfig $scheduleConfig,
        
        #[ORM\Column(type: 'boolean')]
        private bool $isActive,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $assignedUserId,
        
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
        ScheduleConfig $scheduleConfig,
        ?Uuid $assignedUserId = null
    ): self {
        $routineTask = new self(
            $id,
            $name,
            $description,
            $points,
            $frequency,
            $scheduleConfig,
            true, // isActive
            $assignedUserId,
            new DateTimeImmutable()
        );

        $routineTask->record(new RoutineTaskCreated(
            $id,
            $name,
            $points,
            $frequency,
            $scheduleConfig,
            new DateTimeImmutable()
        ));

        return $routineTask;
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

    public function scheduleConfig(): ScheduleConfig
    {
        return $this->scheduleConfig;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function assignedUserId(): ?Uuid
    {
        return $this->assignedUserId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignTo(Uuid $userId): void
    {
        $this->assignedUserId = $userId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function update(
        TaskName $name,
        string $description,
        Points $points,
        Frequency $frequency,
        ScheduleConfig $scheduleConfig
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->points = $points;
        $this->frequency = $frequency;
        $this->scheduleConfig = $scheduleConfig;
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
