<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\RuleType;
use App\TaskManagement\Domain\ValueObject\RuleConfig;
use App\TaskManagement\Domain\Event\BonusPointsRuleCreated;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bonus_points_rules')]
#[ORM\Index(columns: ['is_active'])]
class BonusPointsRule
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        
        #[ORM\Column(type: 'text')]
        private string $description,
        
        #[ORM\Column(type: 'points')]
        private Points $bonusPoints,
        
        #[ORM\Column(type: 'string', length: 50, enumType: RuleType::class)]
        private RuleType $type,
        
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
        string $name,
        string $description,
        Points $bonusPoints,
        RuleConfig $config
    ): self {
        $rule = new self(
            $id,
            $name,
            $description,
            $bonusPoints,
            $config->type(),
            $config->toArray(),
            true,
            new DateTimeImmutable()
        );

        $rule->record(new BonusPointsRuleCreated(
            $id,
            $name,
            $bonusPoints,
            $config->type(),
            new DateTimeImmutable()
        ));

        return $rule;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function bonusPoints(): Points
    {
        return $this->bonusPoints;
    }

    public function type(): RuleType
    {
        return $this->type;
    }

    public function config(): RuleConfig
    {
        return RuleConfig::fromArray($this->config);
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

    public function update(string $name, string $description, Points $bonusPoints): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->bonusPoints = $bonusPoints;
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
