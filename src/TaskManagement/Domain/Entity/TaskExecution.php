<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\Clock\ClockInterface;
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
use DomainException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'task_executions')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['task_template_id'])]
#[ORM\Index(columns: ['assigned_user_id'])]
#[ORM\Index(columns: ['scheduled_for'])]
class TaskExecution
{
    private const BACKLOG_DAYS = 7;

    #[ORM\Transient]
    private array $domainEvents = [];
    
    #[ORM\Transient]
    private ?ExecutionStateInterface $state = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $taskTemplateId,
        
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
        private ?DateTimeImmutable $updatedAt = null,

        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        private ?string $rejectionReason = null
    ) {
    }

    public static function createFromTemplate(
        Uuid $id,
        Uuid $taskTemplateId,
        DateTimeImmutable $scheduledFor,
        ?Uuid $assignedUserId = null
    ): self {
        $execution = new self(
            $id,
            $taskTemplateId,
            null,  // Name will be fetched from TaskTemplate
            null,  // Description will be fetched from TaskTemplate
            null,  // Points will be fetched from TaskTemplate
            $scheduledFor,
            ExecutionStatus::NEW,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $execution->record(new TaskExecutionCreated(
            $id,
            $taskTemplateId,
            $scheduledFor,
            new DateTimeImmutable()
        ));

        return $execution;
    }

    public static function takeFromTemplate(
        Uuid $id,
        Uuid $taskTemplateId,
        TaskName $name,
        string $description,
        Points $points,
        Uuid $assignedUserId,
        DateTimeImmutable $scheduledFor
    ): self {
        $execution = new self(
            $id,
            $taskTemplateId,
            $name,
            $description,
            $points,
            $scheduledFor,
            ExecutionStatus::NEW,
            $assignedUserId,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $execution->record(new TaskExecutionCreated(
            $id,
            $taskTemplateId,
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
            null,  // No template task for one-time executions
            $name,
            $description,
            $points,
            $scheduledFor,
            ExecutionStatus::NEW,
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

    public function taskTemplateId(): ?Uuid
    {
        return $this->taskTemplateId;
    }

    public function templateTaskId(): ?Uuid
    {
        return $this->taskTemplateId;
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

    public function earnedOn(): DateTimeImmutable
    {
        return $this->completedAt ?? $this->scheduledFor;
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

    public function isOpen(): bool
    {
        return $this->status === ExecutionStatus::NEW || $this->status === ExecutionStatus::PENDING;
    }

    public function complete(Uuid $userId, ClockInterface $clock, ?DateTimeImmutable $doneOn = null): void
    {
        if ($doneOn !== null) {
            $this->assertWithinBacklogWindow($doneOn, $clock);
        }

        $this->getState()->complete($this, $userId, $clock, $doneOn);
    }

    public function approve(Uuid $adminId, ClockInterface $clock): void
    {
        $this->getState()->approve($this, $adminId, $clock);
    }

    public function reject(?string $reason = null): void
    {
        $this->getState()->reject($this);
        $this->rejectionReason = $reason;
    }

    public function assertApproved(): void
    {
        if ($this->status !== ExecutionStatus::APPROVED) {
            throw new DomainException('Only an approved execution can be corrected');
        }
    }

    public function moveTo(DateTimeImmutable $day, ClockInterface $clock): void
    {
        $this->assertApproved();

        if ($day->format('o-W') !== $this->earnedOn()->format('o-W')) {
            throw new DomainException('An execution can only be moved within the same week');
        }

        if ($day->setTime(0, 0) > $clock->now()->setTime(0, 0)) {
            throw new DomainException('A task cannot be finished in the future');
        }

        $this->completedAt = $day;
        $this->updatedAt = $clock->now();
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    // Internal method called by state objects to transition to completed state
    public function transitionToState(
        ExecutionStateInterface $newState,
        Uuid $userId,
        ClockInterface $clock,
        ?DateTimeImmutable $doneOn = null
    ): void {
        $this->status = ExecutionStatus::COMPLETED;
        $this->completedByUserId = $userId;
        $this->completedAt = $doneOn ?? $clock->now();
        $this->updatedAt = $clock->now();
        $this->state = $newState;

        $this->record(new TaskExecutionCompleted($this->id, $userId, $this->completedAt));
    }

    private function assertWithinBacklogWindow(DateTimeImmutable $doneOn, ClockInterface $clock): void
    {
        $today = $clock->now()->setTime(0, 0);
        $day = $doneOn->setTime(0, 0);

        if ($day > $today) {
            throw new DomainException('A task cannot be finished on a day that has not come yet');
        }

        if ($day < $today->modify(sprintf('-%d days', self::BACKLOG_DAYS))) {
            throw new DomainException(sprintf('A backlog entry reaches %d days back at most', self::BACKLOG_DAYS));
        }
    }

    // Internal method called by state objects to transition to approved state
    public function transitionToApproved(Uuid $adminId, ClockInterface $clock): void
    {
        $this->status = ExecutionStatus::APPROVED;
        $this->approvedByAdminId = $adminId;
        $this->approvedAt = $clock->now();
        $this->updatedAt = $clock->now();
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
