<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionConfig;
use App\TaskManagement\Domain\Event\StatusChangeRuleCreated;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'status_change_rules')]
#[ORM\Index(columns: ['is_active'])]
#[ORM\Index(columns: ['task_template_id'])]
#[ORM\Index(columns: ['team_id'])]
class StatusChangeRule
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $teamId,

        #[ORM\Column(type: 'uuid')]
        private Uuid $taskTemplateId,

        #[ORM\Column(type: 'string', length: 255)]
        private string $name,

        #[ORM\Column(type: 'text')]
        private string $description,

        #[ORM\Column(type: 'string', length: 50)]
        private StatusChangeConditionType $conditionType,

        #[ORM\Column(type: 'json')]
        private array $config,

        #[ORM\Column(type: 'boolean')]
        private bool $isActive,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(
        Uuid $id,
        Uuid $teamId,
        Uuid $taskTemplateId,
        string $name,
        string $description,
        StatusChangeConditionConfig $config
    ): self {
        $rule = new self(
            $id,
            $teamId,
            $taskTemplateId,
            $name,
            $description,
            $config->type(),
            $config->toArray(),
            true,
            new DateTimeImmutable()
        );

        $rule->record(new StatusChangeRuleCreated(
            $id,
            $taskTemplateId,
            $name,
            $config->type(),
            new DateTimeImmutable()
        ));

        return $rule;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function teamId(): Uuid
    {
        return $this->teamId;
    }

    public function taskTemplateId(): Uuid
    {
        return $this->taskTemplateId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function conditionType(): StatusChangeConditionType
    {
        return $this->conditionType;
    }

    public function config(): StatusChangeConditionConfig
    {
        return StatusChangeConditionConfig::fromArray($this->config);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function update(string $name, string $description): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = new DateTimeImmutable();
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
