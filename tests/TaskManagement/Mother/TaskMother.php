<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\Tests\Shared\Mother\UuidMother;

final class TaskMother
{
    private ?Uuid $id = null;
    private ?TaskName $name = null;
    private ?string $description = null;
    private ?Points $points = null;
    private ?Frequency $frequency = null;
    private ?Uuid $assignedUserId = null;
    private bool $shouldComplete = false;
    private ?Uuid $completingUserId = null;
    private bool $shouldApprove = false;
    private ?Uuid $approvingAdminId = null;
    private bool $shouldReject = false;
    private bool $clearEvents = false;

    private function __construct()
    {
    }

    public static function aTask(): self
    {
        return new self();
    }

    public function withId(Uuid $id): self
    {
        $this->id = $id;
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

    public function withFrequency(Frequency $frequency): self
    {
        $this->frequency = $frequency;
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

    public function build(): Task
    {
        $task = Task::create(
            $this->id ?? UuidMother::random(),
            $this->name ?? TaskNameMother::create(),
            $this->description ?? 'Default task description',
            $this->points ?? PointsMother::medium(),
            $this->frequency ?? FrequencyMother::daily(),
            $this->assignedUserId
        );

        if ($this->shouldComplete && $this->completingUserId !== null) {
            $task->markAsCompleted($this->completingUserId);
        }

        if ($this->shouldApprove && $this->approvingAdminId !== null) {
            $task->approve($this->approvingAdminId);
        }

        if ($this->shouldReject) {
            $task->reject();
        }

        if ($this->clearEvents) {
            $task->pullDomainEvents();
        }

        return $task;
    }

    // Convenience factory methods
    public static function pending(): Task
    {
        return self::aTask()->build();
    }

    public static function dailyTask(): Task
    {
        return self::aTask()
            ->withName(TaskNameMother::create('Daily chore'))
            ->withFrequency(FrequencyMother::daily())
            ->withPoints(PointsMother::low())
            ->build();
    }

    public static function weeklyTask(): Task
    {
        return self::aTask()
            ->withName(TaskNameMother::create('Weekly chore'))
            ->withFrequency(FrequencyMother::weekly())
            ->withPoints(PointsMother::medium())
            ->build();
    }

    public static function monthlyTask(): Task
    {
        return self::aTask()
            ->withName(TaskNameMother::create('Monthly chore'))
            ->withFrequency(FrequencyMother::monthly())
            ->withPoints(PointsMother::high())
            ->build();
    }

    public static function highValueTask(): Task
    {
        return self::aTask()
            ->withName(TaskNameMother::create('Important task'))
            ->withPoints(PointsMother::high())
            ->withDescription('This is a high-value task that requires significant effort')
            ->build();
    }

    public static function random(): Task
    {
        return self::aTask()
            ->withName(TaskNameMother::random())
            ->withPoints(PointsMother::random())
            ->withFrequency(FrequencyMother::random())
            ->build();
    }
}
