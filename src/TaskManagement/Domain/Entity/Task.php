<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\TaskStatus;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\Event\TaskCreated;
use App\TaskManagement\Domain\Event\TaskCompleted;
use App\TaskManagement\Domain\Event\TaskApproved;
use App\TaskManagement\Domain\State\TaskStateInterface;
use App\TaskManagement\Domain\State\TaskStateFactory;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['assigned_user_id'])]
#[ORM\Index(columns: ['parent_task_id'])]
#[ORM\Index(columns: ['is_template'])]
#[ORM\Index(columns: ['scheduled_for'])]
class Task
{
    #[ORM\Transient]
    private array $domainEvents = [];
    
    #[ORM\Transient]
    private ?TaskStateInterface $state = null;

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
        
        #[ORM\Column(type: 'schedule_config', nullable: true)]
        private ?ScheduleConfig $scheduleConfig,
        
        #[ORM\Column(type: 'boolean')]
        private bool $isTemplate,
        
        #[ORM\Column(type: 'boolean')]
        private bool $isActive,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $parentTaskId,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $scheduledFor,
        
        #[ORM\Column(type: 'task_status')]
        private TaskStatus $status,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $assignedUserId,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $completedByUserId,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $completedAt,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $approvedByAdminId,
        
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
            null, // scheduleConfig
            false, // isTemplate
            true, // isActive
            null, // parentTaskId
            null, // scheduledFor
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

    public static function createTemplate(
        Uuid $id,
        TaskName $name,
        string $description,
        Points $points,
        Frequency $frequency,
        ScheduleConfig $scheduleConfig,
        ?Uuid $assignedUserId = null
    ): self {
        $task = new self(
            $id,
            $name,
            $description,
            $points,
            $frequency,
            $scheduleConfig,
            true, // isTemplate
            true, // isActive
            null, // parentTaskId
            null, // scheduledFor
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

    public static function createFromTemplate(
        Uuid $id,
        Uuid $parentTaskId,
        DateTimeImmutable $scheduledFor,
        ?Uuid $assignedUserId = null
    ): self {
        $task = new self(
            $id,
            TaskName::fromString('Pending'), // Will be populated from parent
            '', // Will be populated from parent
            Points::fromInt(0), // Will be populated from parent
            Frequency::ONCE,
            null,
            false, // isTemplate
            true, // isActive
            $parentTaskId,
            $scheduledFor,
            TaskStatus::PENDING,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $task->record(new TaskCreated($id, $task->name, $task->points, $task->frequency, new DateTimeImmutable()));

        return $task;
    }

    public static function createScheduled(
        Uuid $id,
        TaskName $name,
        string $description,
        Points $points,
        DateTimeImmutable $scheduledFor,
        ?Uuid $assignedUserId = null
    ): self {
        $task = new self(
            $id,
            $name,
            $description,
            $points,
            Frequency::ONCE,
            null,
            false, // isTemplate
            true, // isActive
            null, // parentTaskId
            $scheduledFor,
            TaskStatus::PENDING,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $task->record(new TaskCreated($id, $name, $points, $task->frequency, new DateTimeImmutable()));

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

    public function scheduleConfig(): ?ScheduleConfig
    {
        return $this->scheduleConfig;
    }

    public function isTemplate(): bool
    {
        return $this->isTemplate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function parentTaskId(): ?Uuid
    {
        return $this->parentTaskId;
    }

    public function scheduledFor(): ?DateTimeImmutable
    {
        return $this->scheduledFor;
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
        $this->getState()->complete($this, $userId);
    }

    public function approve(Uuid $adminId): void
    {
        $this->getState()->approve($this, $adminId);
    }

    public function reject(): void
    {
        $this->getState()->reject($this);
    }
    
    // Internal method called by state objects to transition to completed state
    public function transitionToState(TaskStateInterface $newState, Uuid $userId): void
    {
        $this->status = TaskStatus::COMPLETED;
        $this->completedByUserId = $userId;
        $this->completedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->state = $newState;

        $this->record(new TaskCompleted($this->id, $userId, $this->completedAt));
    }
    
    // Internal method called by state objects to transition to approved state
    public function transitionToApproved(Uuid $adminId): void
    {
        $this->status = TaskStatus::APPROVED;
        $this->approvedByAdminId = $adminId;
        $this->approvedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->state = TaskStateFactory::createFromStatus(TaskStatus::APPROVED);

        $this->record(new TaskApproved($this->id, $adminId, $this->approvedAt));
    }
    
    // Internal method called by state objects to transition to rejected state
    public function transitionToRejected(): void
    {
        $this->status = TaskStatus::REJECTED;
        $this->updatedAt = new DateTimeImmutable();
        $this->state = TaskStateFactory::createFromStatus(TaskStatus::REJECTED);
    }
    
    private function getState(): TaskStateInterface
    {
        if ($this->state === null) {
            $this->state = TaskStateFactory::createFromStatus($this->status);
        }
        
        return $this->state;
    }

    public function update(TaskName $name, string $description, Points $points, Frequency $frequency, ?ScheduleConfig $scheduleConfig = null): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->points = $points;
        $this->frequency = $frequency;
        if ($scheduleConfig !== null) {
            $this->scheduleConfig = $scheduleConfig;
        }
        $this->updatedAt = new DateTimeImmutable();
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

    public function populateFromTemplate(Task $template): void
    {
        if (!$template->isTemplate()) {
            throw new \DomainException('Can only populate from template tasks');
        }
        
        $this->name = $template->name();
        $this->description = $template->description();
        $this->points = $template->points();
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
