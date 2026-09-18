<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use InvalidArgumentException;

final readonly class RuleConfig
{
    /**
     * @param string[] $accounts account kinds the condition counts; empty means every account
     */
    private function __construct(
        private RuleType $type,
        private ?Uuid $taskTemplateId,
        private ?int $requiredDays,
        private ?int $requiredCount,
        private ?int $pointsPerDay = null,
        private ?int $requiredPoints = null,
        private array $accounts = []
    ) {
        $this->validate();
    }

    /**
     * @param string[] $accounts
     */
    public static function consecutiveDays(
        int $requiredDays,
        int $pointsPerDay = 1,
        ?Uuid $taskTemplateId = null,
        array $accounts = []
    ): self {
        return new self(
            RuleType::CONSECUTIVE_DAYS,
            $taskTemplateId,
            $requiredDays,
            null,
            $pointsPerDay,
            null,
            self::countedAccounts($accounts)
        );
    }

    public static function monthlyTaskCount(int $requiredCount): self
    {
        if ($requiredCount < 1) {
            throw new InvalidArgumentException('Required count must be at least 1');
        }

        return new self(
            RuleType::MONTHLY_TASK_COUNT,
            null,
            null,
            $requiredCount,
            null
        );
    }

    /**
     * @param string[] $accounts
     */
    public static function weeklyPointsSum(int $requiredPoints, array $accounts = []): self
    {
        return new self(
            RuleType::WEEKLY_POINTS_SUM,
            null,
            null,
            null,
            null,
            $requiredPoints,
            array_values(array_unique($accounts))
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromInput(RuleType $type, array $config): self
    {
        return match ($type) {
            RuleType::CONSECUTIVE_DAYS => self::consecutiveDays(
                (int) ($config['requiredDays'] ?? 0),
                (int) ($config['pointsPerDay'] ?? 1),
                isset($config['taskTemplateId']) ? Uuid::fromString((string) $config['taskTemplateId']) : null,
                $config['accounts'] ?? []
            ),
            RuleType::MONTHLY_TASK_COUNT => self::monthlyTaskCount((int) ($config['requiredCount'] ?? 0)),
            RuleType::WEEKLY_POINTS_SUM => self::weeklyPointsSum(
                (int) ($config['requiredPoints'] ?? 0),
                $config['accounts'] ?? []
            ),
        };
    }

    public function type(): RuleType
    {
        return $this->type;
    }

    public function taskTemplateId(): ?Uuid
    {
        return $this->taskTemplateId;
    }

    public function requiredDays(): ?int
    {
        return $this->requiredDays;
    }

    public function requiredCount(): ?int
    {
        return $this->requiredCount;
    }

    public function pointsPerDay(): ?int
    {
        return $this->pointsPerDay;
    }

    public function requiredPoints(): ?int
    {
        return $this->requiredPoints;
    }

    /**
     * @return string[] empty means the condition counts every account
     */
    public function accounts(): array
    {
        return $this->accounts;
    }

    /**
     * @return AccountKind[]
     */
    public function accountKinds(): array
    {
        return $this->accounts === [] ? AccountKind::all() : AccountKind::fromValues($this->accounts);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'taskTemplateId' => $this->taskTemplateId?->value(),
            'requiredDays' => $this->requiredDays,
            'requiredCount' => $this->requiredCount,
            'pointsPerDay' => $this->pointsPerDay,
            'requiredPoints' => $this->requiredPoints,
            'accounts' => $this->accounts,
        ];
    }

    public static function fromArray(array $data): self
    {
        $type = RuleType::from($data['type']);

        $accounts = $data['accounts'] ?? [];

        return new self(
            $type,
            isset($data['taskTemplateId']) ? Uuid::fromString($data['taskTemplateId']) : null,
            $data['requiredDays'] ?? null,
            $data['requiredCount'] ?? null,
            isset($data['requiredDays']) ? (int) ($data['pointsPerDay'] ?? 1) : null,
            $data['requiredPoints'] ?? null,
            $type === RuleType::CONSECUTIVE_DAYS ? self::countedAccounts($accounts) : $accounts
        );
    }

    private function validate(): void
    {
        match ($this->type) {
            RuleType::CONSECUTIVE_DAYS => $this->validateConsecutiveDays(),
            RuleType::MONTHLY_TASK_COUNT => $this->validateMonthlyTaskCount(),
            RuleType::WEEKLY_POINTS_SUM => $this->validateWeeklyPointsSum(),
        };
    }

    private function validateConsecutiveDays(): void
    {
        if ($this->requiredDays === null || $this->requiredDays < 2) {
            throw new InvalidArgumentException('Required days must be at least 2');
        }

        if ($this->pointsPerDay === null || $this->pointsPerDay < 1) {
            throw new InvalidArgumentException('A streak day needs at least 1 point');
        }

        $this->validateAccounts();
    }

    private function validateMonthlyTaskCount(): void
    {
        if ($this->requiredCount === null || $this->requiredCount < 1) {
            throw new InvalidArgumentException('Required count must be at least 1');
        }
    }

    private function validateWeeklyPointsSum(): void
    {
        if ($this->requiredPoints === null || $this->requiredPoints < 1) {
            throw new InvalidArgumentException('Required points must be at least 1');
        }

        $this->validateAccounts();
    }

    private function validateAccounts(): void
    {
        foreach ($this->accounts as $account) {
            if (AccountKind::tryFrom($account) === null) {
                throw new InvalidArgumentException(sprintf('Unknown account "%s"', $account));
            }
        }
    }

    /**
     * @param string[] $accounts
     * @return string[] the task account when nothing is chosen, so a streak keeps meaning what it meant
     */
    private static function countedAccounts(array $accounts): array
    {
        return $accounts === []
            ? [AccountKind::TASKS->value]
            : array_values(array_unique($accounts));
    }
}
