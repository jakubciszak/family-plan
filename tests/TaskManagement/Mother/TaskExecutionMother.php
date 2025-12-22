<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\Tests\Shared\Mother\UuidMother;
use DateTimeImmutable;

final class TaskExecutionMother
{
    private ?Uuid $id = null;
    private ?Uuid $taskTemplateId = null;
    private ?TaskName $name = null;
    private ?string $description = null;
    private ?Points $points = null;
    private ?DateTimeImmutable $scheduledFor = null;
    private ?Uuid $assignedUserId = null;
    private bool $shouldComplete = false;
    private ?Uuid $completingUserId = null;
    private bool $shouldApprove = false;
    private ?Uuid $approvingAdminId = null;
    private bool $shouldReject = false;
    private bool $clearEvents = false;
    private bool $noTaskTemplate = false;

    private function __construct()
    {
    }

    public static function aTaskExecution(): self
    {
        return new self();
    }

    public function withId(Uuid $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function withTaskTemplateId(Uuid $taskTemplateId): self
    {
        $this->taskTemplateId = $taskTemplateId;
        $this->noTaskTemplate = false;
        return $this;
    }

    public function withTemplateTaskId(Uuid $templateTaskId): self
    {
        $this->taskTemplateId = $templateTaskId;
        $this->noTaskTemplate = false;
        return $this;
    }

    public function withoutTaskTemplate(): self
    {
        $this->taskTemplateId = null;
        $this->noTaskTemplate = true;
        return $this;
    }

    public function withoutTemplateTask(): self
    {
        $this->taskTemplateId = null;
        $this->noTaskTemplate = true;
        return $this;
    }

    public function withoutRoutineTask(): self
    {
        $this->taskTemplateId = null;
        $this->noTaskTemplate = true;
        return $this;
    }

    public function withName(TaskName $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function withPoints(Points $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function withScheduledFor(DateTimeImmutable $scheduledFor): self
    {
        $this->scheduledFor = $scheduledFor;
        return $this;
    }

    public function assignedTo(Uuid $userId): self
    {
        $this->assignedUserId = $userId;
        return $this;
    }

    public function completed(Uuid $userId): self
    {
        $this->shouldComplete = true;
        $this->completingUserId = $userId;
        return $this;
    }

    public function approved(Uuid $userId, Uuid $adminId): self
    {
        $this->shouldComplete = true;
        $this->completingUserId = $userId;
        $this->shouldApprove = true;
        $this->approvingAdminId = $adminId;
        return $this;
    }

    public function rejected(Uuid $userId): self
    {
        $this->shouldComplete = true;
        $this->completingUserId = $userId;
        $this->shouldReject = true;
        return $this;
    }

    public function withoutEvents(): self
    {
        $this->clearEvents = true;
        return $this;
    }

    public function build(): TaskExecution
    {
        $id = $this->id ?? UuidMother::random();
        $scheduledFor = $this->scheduledFor ?? new DateTimeImmutable();

        if ($this->noTaskTemplate) {
            // One-time task execution
            $taskExecution = TaskExecution::createOneTime(
                $id,
                $this->name ?? TaskNameMother::create(),
                $this->description ?? 'Default one-time task description',
                $this->points ?? PointsMother::medium(),
                $scheduledFor,
                $this->assignedUserId
            );
        } else {
            // Task execution from template task
            $taskExecution = TaskExecution::createFromTemplate(
                $id,
                $this->taskTemplateId ?? UuidMother::random(),
                $scheduledFor,
                $this->assignedUserId
            );
        }

        if ($this->shouldComplete && $this->completingUserId !== null) {
            $taskExecution->complete($this->completingUserId);
        }

        if ($this->shouldApprove && $this->approvingAdminId !== null) {
            $taskExecution->approve($this->approvingAdminId);
        }

        if ($this->shouldReject) {
            $taskExecution->reject();
        }

        if ($this->clearEvents) {
            $taskExecution->pullDomainEvents();
        }

        return $taskExecution;
    }

    // Convenience factory methods
    public static function pending(): TaskExecution
    {
        return self::aTaskExecution()->build();
    }

    public static function fromTaskTemplate(Uuid $taskTemplateId): TaskExecution
    {
        return self::aTaskExecution()
            ->withTaskTemplateId($taskTemplateId)
            ->build();
    }

    public static function fromTemplate(Uuid $templateTaskId): TaskExecution
    {
        return self::aTaskExecution()
            ->withTemplateTaskId($templateTaskId)
            ->build();
    }

    public static function oneTime(): TaskExecution
    {
        return self::aTaskExecution()
            ->withoutTaskTemplate()
            ->withName(TaskNameMother::create('One-time task'))
            ->build();
    }

    public static function random(): TaskExecution
    {
        $isOneTime = (bool)rand(0, 1);
        
        if ($isOneTime) {
            return self::aTaskExecution()
                ->withoutTaskTemplate()
                ->withName(TaskNameMother::random())
                ->withPoints(PointsMother::random())
                ->build();
        }

        return self::aTaskExecution()
            ->withTaskTemplateId(UuidMother::random())
            ->build();
    }
}
