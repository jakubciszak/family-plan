<?php

declare(strict_types=1);

namespace App\ActionPlanning\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'action_plans')]
#[ORM\Index(name: 'idx_action_plan_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_action_plan_team', columns: ['team_id'])]
class ActionPlan
{
    public const REMINDER_SOUNDS = ['soft', 'bell', 'double', 'melody'];

    #[ORM\Column(length: 20, options: ['default' => 'soft'])]
    private string $reminderSound = 'soft';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,
        #[ORM\Column(length: 160)]
        private string $name,
        #[ORM\Column(type: 'json')]
        private array $steps,
        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $estimatedMinutes,
        #[ORM\Column(type: 'integer')]
        private int $reminderMinutes,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $teamId = null,
    ) {
    }

    public static function create(Uuid $id, Uuid $userId, string $name, array $steps, ?int $estimatedMinutes, int $reminderMinutes): self
    {
        $plan = new self($id, $userId, '', [], null, 10);
        $plan->revise($name, $steps, $estimatedMinutes, $reminderMinutes);

        return $plan;
    }

    public function revise(string $name, array $steps, ?int $estimatedMinutes, int $reminderMinutes): void
    {
        $name = self::name($name, 160);
        if ($estimatedMinutes !== null && ($estimatedMinutes < 1 || $estimatedMinutes > 1440)) {
            throw new \DomainException('actionPlans.invalidDuration');
        }
        if ($reminderMinutes < 1 || $reminderMinutes > 120) {
            throw new \DomainException('actionPlans.invalidReminder');
        }
        if (!array_is_list($steps) || count($steps) < 1 || count($steps) > 100) {
            throw new \DomainException('actionPlans.invalidSteps');
        }
        $normalized = [];
        $count = 0;
        foreach ($steps as $step) {
            if (!is_array($step) || !is_string($step['name'] ?? null) || !is_array($step['stages'] ?? null) || !array_is_list($step['stages']) || count($step['stages']) > 100) {
                throw new \DomainException('actionPlans.invalidSteps');
            }
            $stages = [];
            foreach ($step['stages'] as $stage) {
                if (!is_string($stage)) {
                    throw new \DomainException('actionPlans.invalidSteps');
                }
                $stages[] = self::name($stage, 240);
            }
            $normalized[] = ['name' => self::name($step['name'], 240), 'stages' => $stages];
            $count += max(1, count($stages));
        }
        if ($count > 500) {
            throw new \DomainException('actionPlans.invalidSteps');
        }
        $this->name = $name;
        $this->steps = $normalized;
        $this->estimatedMinutes = $estimatedMinutes;
        $this->reminderMinutes = $reminderMinutes;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function teamId(): ?Uuid
    {
        return $this->teamId;
    }

    public function shareWith(?Uuid $teamId): void
    {
        $this->teamId = $teamId;
    }

    public function changeReminderSound(string $sound): void
    {
        if (!in_array($sound, self::REMINDER_SOUNDS, true)) {
            throw new \DomainException('actionPlans.invalidSound');
        }
        $this->reminderSound = $sound;
    }

    public function describe(): array
    {
        return [
            'id' => $this->id->value(),
            'name' => $this->name,
            'teamId' => $this->teamId?->value(),
            'steps' => $this->steps,
            'estimatedMinutes' => $this->estimatedMinutes,
            'reminderMinutes' => $this->reminderMinutes,
            'reminderSound' => $this->reminderSound,
        ];
    }

    private static function name(string $name, int $limit): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > $limit) {
            throw new \DomainException('actionPlans.invalidName');
        }

        return $name;
    }
}
