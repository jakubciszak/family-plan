<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ExecutionStatus;
use App\TaskManagement\Domain\Event\TaskExecutionCreated;
use App\TaskManagement\Domain\Event\TaskExecutionCompleted;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\State\ExecutionStateInterface;
use App\TaskManagement\Domain\State\ExecutionStateFactory;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'task_executions')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['routine_task_id'])]
#[ORM\Index(columns: ['assigned_user_id'])]
#[ORM\Index(columns: ['scheduled_for'])]
class TaskExecution
{
    #[ORM\Transient]
    private array $domainEvents = [];
    
    #[ORM\Transient]
    private ?ExecutionStateInterface $state = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $routineTaskId,
        
        #[ORM\Column(type: 'task_name', nullable: true)]
        private ?TaskName $name,
        
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description,
        
        #[ORM\Column(type: 'points', nullable: true)]
        private ?Points $points,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $scheduledFor,
        
        #[ORM\Column(type: 'execution_status')]
        private ExecutionStatus $status,
        
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

    public static function createFromRoutineTask(
        Uuid $id,
        Uuid $routineTaskId,
        DateTimeImmutable $scheduledFor,
        ?Uuid $assignedUserId = null
    ): self {
        $execution = new self(
            $id,
            $routineTaskId,
            null,  // Name will be fetched from RoutineTask
            null,  // Description will be fetched from RoutineTask
            null,  // Points will be fetched from RoutineTask
            $scheduledFor,
            ExecutionStatus::PENDING,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $execution->record(new TaskExecutionCreated(
            $id,
            $routineTaskId,
            $scheduledFor,
            new DateTimeImmutable()
        ));

        return $execution;
    }

    public static function createOneTime(
        Uuid $id,
        TaskName $name,
        string $description,
        Points $points,
        DateTimeImmutable $scheduledFor,
        ?Uuid $assignedUserId = null
    ): self {
        $execution = new self(
            $id,
            null,  // No routine task for one-time executions
            $name,
            $description,
            $points,
            $scheduledFor,
            ExecutionStatus::PENDING,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $execution->record(new TaskExecutionCreated(
            $id,
            null,
            $scheduledFor,
            new DateTimeImmutable()
        ));

        return $execution;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function routineTaskId(): ?Uuid
    {
        return $this->routineTaskId;
    }

    public function name(): ?TaskName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function points(): ?Points
    {
        return $this->points;
    }

    public function scheduledFor(): DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function status(): ExecutionStatus
    {
        return $this->status;
    }

    public function assignedUserId(): ?Uuid
    {
        return $this->assignedUserId;
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

    public function assignTo(Uuid $userId): void
    {
        $this->assignedUserId = $userId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function complete(Uuid $userId): void
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
    public function transitionToState(ExecutionStateInterface $newState, Uuid $userId): void
    {
        $this->status = ExecutionStatus::COMPLETED;
        $this->completedByUserId = $userId;
        $this->completedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->state = $newState;

        $this->record(new TaskExecutionCompleted($this->id, $userId, $this->completedAt));
    }

    // Internal method called by state objects to transition to approved state
    public function transitionToApproved(Uuid $adminId): void
    {
        $this->status = ExecutionStatus::APPROVED;
        $this->approvedByAdminId = $adminId;
        $this->approvedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->state = ExecutionStateFactory::createFromStatus(ExecutionStatus::APPROVED);

        $this->record(new TaskExecutionApproved($this->id, $adminId, $this->approvedAt));
    }

    // Internal method called by state objects to transition to rejected state
    public function transitionToRejected(): void
    {
        $this->status = ExecutionStatus::REJECTED;
        $this->updatedAt = new DateTimeImmutable();
        $this->state = ExecutionStateFactory::createFromStatus(ExecutionStatus::REJECTED);
    }

    private function getState(): ExecutionStateInterface
    {
        if ($this->state === null) {
            $this->state = ExecutionStateFactory::createFromStatus($this->status);
        }
        
        return $this->state;
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
