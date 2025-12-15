<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Mother;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\Tests\Shared\Mother\UuidMother;

final class TaskTemplateMother
{
    private ?Uuid $id = null;
    private ?TaskName $name = null;
    private ?string $description = null;
    private ?Points $points = null;
    private ?Frequency $frequency = null;
    private ?ScheduleConfig $scheduleConfig = null;
    private ?Uuid $assignedUserId = null;
    private bool $shouldDeactivate = false;
    private bool $clearEvents = false;

    private function __construct()
    {
    }

    public static function aTaskTemplate(): self
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

    public function withScheduleConfig(ScheduleConfig $scheduleConfig): self
    {
        $this->scheduleConfig = $scheduleConfig;
        return $this;
    }

    public function assignedTo(Uuid $userId): self
    {
        $this->assignedUserId = $userId;
        return $this;
    }

    public function inactive(): self
    {
        $this->shouldDeactivate = true;
        return $this;
    }

    public function withoutEvents(): self
    {
        $this->clearEvents = true;
        return $this;
    }

    public function build(): TaskTemplate
    {
        $taskTemplate = TaskTemplate::create(
            $this->id ?? UuidMother::random(),
            $this->name ?? TaskNameMother::create(),
            $this->description ?? 'Default task template description',
            $this->points ?? PointsMother::medium(),
            $this->frequency ?? FrequencyMother::daily(),
            $this->scheduleConfig ?? ScheduleConfigMother::daily(),
            $this->assignedUserId
        );

        if ($this->shouldDeactivate) {
            $taskTemplate->deactivate();
        }

        if ($this->clearEvents) {
            $taskTemplate->pullDomainEvents();
        }

        return $taskTemplate;
    }

    // Convenience factory methods
    public static function active(): TaskTemplate
    {
        return self::aTaskTemplate()->build();
    }

    public static function inactive(): TaskTemplate
    {
        return self::aTaskTemplate()->inactive()->build();
    }

    public static function dailyTaskTemplate(): TaskTemplate
    {
        return self::aTaskTemplate()
            ->withName(TaskNameMother::create('Daily routine'))
            ->withFrequency(FrequencyMother::daily())
            ->withScheduleConfig(ScheduleConfigMother::daily())
            ->withPoints(PointsMother::low())
            ->build();
    }

    public static function weeklyTaskTemplate(): TaskTemplate
    {
        return self::aTaskTemplate()
            ->withName(TaskNameMother::create('Weekly routine'))
            ->withFrequency(FrequencyMother::weekly())
            ->withScheduleConfig(ScheduleConfigMother::weeklyOnMonday())
            ->withPoints(PointsMother::medium())
            ->build();
    }

    public static function monthlyTaskTemplate(): TaskTemplate
    {
        return self::aTaskTemplate()
            ->withName(TaskNameMother::create('Monthly routine'))
            ->withFrequency(FrequencyMother::monthly())
            ->withScheduleConfig(ScheduleConfigMother::monthlyOnDay(1))
            ->withPoints(PointsMother::high())
            ->build();
    }

    public static function random(): TaskTemplate
    {
        return self::aTaskTemplate()
            ->withName(TaskNameMother::random())
            ->withPoints(PointsMother::random())
            ->withFrequency(FrequencyMother::random())
            ->withScheduleConfig(ScheduleConfigMother::random())
            ->build();
    }
}
