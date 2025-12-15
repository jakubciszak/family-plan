<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ScheduleConfig
{
    private function __construct(
        public string $type,
        public ?int $dayOfWeek = null,
        public ?int $dayOfMonth = null,
        public ?int $timesPerWeek = null
    ) {
        $this->validate();
    }

    public static function daily(): self
    {
        return new self('daily');
    }

    public static function weeklyOnDay(int $dayOfWeek): self
    {
        return new self('weekly', dayOfWeek: $dayOfWeek);
    }

    public static function monthlyOnDay(int $dayOfMonth): self
    {
        return new self('monthly', dayOfMonth: $dayOfMonth);
    }

    public static function timesPerWeek(int $times): self
    {
        return new self('times_per_week', timesPerWeek: $times);
    }

    public static function once(): self
    {
        return new self('once');
    }

    private function validate(): void
    {
        if (!in_array($this->type, ['daily', 'weekly', 'monthly', 'times_per_week', 'once'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid schedule type: %s', $this->type));
        }

        if ($this->type === 'weekly' && ($this->dayOfWeek === null || $this->dayOfWeek < 1 || $this->dayOfWeek > 7)) {
            throw new InvalidArgumentException('Weekly schedule requires a day of week between 1 (Monday) and 7 (Sunday)');
        }

        if ($this->type === 'monthly' && ($this->dayOfMonth === null || $this->dayOfMonth < 1 || $this->dayOfMonth > 31)) {
            throw new InvalidArgumentException('Monthly schedule requires a day of month between 1 and 31');
        }

        if ($this->type === 'times_per_week' && ($this->timesPerWeek === null || $this->timesPerWeek < 1 || $this->timesPerWeek > 7)) {
            throw new InvalidArgumentException('Times per week schedule requires a value between 1 and 7');
        }
    }

    public function equals(ScheduleConfig $other): bool
    {
        return $this->type === $other->type
            && $this->dayOfWeek === $other->dayOfWeek
            && $this->dayOfMonth === $other->dayOfMonth
            && $this->timesPerWeek === $other->timesPerWeek;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'day_of_week' => $this->dayOfWeek,
            'day_of_month' => $this->dayOfMonth,
            'times_per_week' => $this->timesPerWeek,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'],
            $data['day_of_week'] ?? null,
            $data['day_of_month'] ?? null,
            $data['times_per_week'] ?? null
        );
    }
}
