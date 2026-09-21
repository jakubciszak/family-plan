<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Entity;

use App\DayPlanning\Domain\Exception\PlanningException;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'day_planning_tags')]
#[ORM\Index(name: 'idx_day_planning_tag_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_day_planning_tag_team', columns: ['team_id'])]
class CalendarTag
{
    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\Column(type: 'boolean')]
    private bool $archived = false;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        #[ORM\Column(type: 'uuid')]
        private Uuid $ownerId,
        #[ORM\Column(length: 8)]
        private string $scope,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $teamId,
    ) {
    }

    public static function create(Uuid $id, Uuid $ownerId, array $data): self
    {
        $scope = $data['scope'] ?? 'PERSONAL';
        if (!in_array($scope, ['PERSONAL', 'TEAM'], true) || ($scope === 'TEAM' && ($data['teamId'] ?? null) === null) || ($scope === 'PERSONAL' && ($data['teamId'] ?? null) !== null)) {
            throw PlanningException::invalid('Invalid tag scope.');
        }
        $teamId = ($data['teamId'] ?? null) === null ? null : Uuid::fromString(CalendarEvent::uuid($data['teamId']));
        $tag = new self($id, $ownerId, $scope, $teamId);
        $tag->revise($data);
        return $tag;
    }

    public function revise(array $data): void
    {
        if ((isset($data['scope']) && $data['scope'] !== $this->scope) || (array_key_exists('teamId', $data) && $data['teamId'] !== $this->teamId?->value())) {
            throw PlanningException::invalid('Tag scope cannot be changed.');
        }
        $name = $data['name'] ?? ($this->name ?? null);
        $color = $data['color'] ?? ($this->color ?? '#226a4c');
        if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 80 || !is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
            throw PlanningException::invalid('A tag needs a name and a hexadecimal color.');
        }
        $this->name = trim($name);
        $this->color = strtolower($color);
    }

    public function archive(): void { $this->archived = true; }
    public function id(): Uuid { return $this->id; }
    public function ownerId(): Uuid { return $this->ownerId; }
    public function teamId(): ?Uuid { return $this->teamId; }
    public function scope(): string { return $this->scope; }
    public function archived(): bool { return $this->archived; }
    public function describe(): array { return ['id' => $this->id->value(), 'name' => $this->name, 'color' => $this->color, 'scope' => $this->scope, 'teamId' => $this->teamId?->value()]; }
}
