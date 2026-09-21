<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Entity;

use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\ValueObject\EventSchedule;
use App\DayPlanning\Domain\ValueObject\RecurrenceRule;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'day_planning_events')]
#[ORM\Index(name: 'idx_day_planning_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_day_planning_team', columns: ['team_id'])]
#[ORM\Index(name: 'idx_day_planning_people', columns: ['person_ids'])]
class CalendarEvent
{
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(type: 'json')]
    private array $definition = [];

    #[ORM\Column(type: 'json')]
    private array $participants = [];

    #[ORM\Column(type: 'json')]
    private array $exceptions = [];

    #[ORM\Column(type: 'json')]
    private array $participationExceptions = [];

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $personIds = [];

    #[ORM\Column(type: 'boolean')]
    private bool $cancelled = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        #[ORM\Column(type: 'uuid')]
        private Uuid $ownerId,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $teamId = null,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(Uuid $id, Uuid $ownerId, array $data): self
    {
        $event = new self($id, $ownerId);
        $event->revise($data);
        return $event;
    }

    public function revise(array $changes): void
    {
        $next = $this->normalize(array_replace($this->definition, $changes));
        $scheduleChanged = $this->definition !== [] && ($next['schedule'] !== $this->definition['schedule'] || $next['recurrence'] !== $this->definition['recurrence']);
        if ($scheduleChanged && ($this->exceptions !== [] || $this->participationExceptions !== []) && ($changes['resetExceptions'] ?? false) !== true) {
            throw new PlanningException('exceptions_reset_required', 409, ['exceptionCount' => count(array_unique(array_merge(array_keys($this->exceptions), array_keys($this->participationExceptions))))]);
        }
        if ($scheduleChanged) {
            $this->exceptions = [];
            $this->participationExceptions = [];
        }
        $removed = array_diff(array_keys($this->participants), $next['participantIds']);
        foreach ($removed as $personId) {
            $this->removeParticipantOverrides($personId);
        }
        $this->participants = array_combine($next['participantIds'], array_map(fn (string $id): string => $this->participants[$id] ?? 'INCLUDED', $next['participantIds']));
        $this->definition = $next;
        $this->teamId = $next['teamId'] === null ? null : Uuid::fromString($next['teamId']);
        $this->touch();
    }

    private function normalize(array $data): array
    {
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? '';
        $location = $data['location'] ?? '';
        if (!is_string($title) || trim($title) === '' || mb_strlen($title) > 200 || !is_string($description) || mb_strlen($description) > 10000 || !is_string($location) || mb_strlen($location) > 500) {
            throw PlanningException::invalid('Invalid event title, description or location.');
        }
        if (!is_array($data['schedule'] ?? null)) {
            throw PlanningException::invalid('Event schedule is required.');
        }
        $schedule = EventSchedule::fromArray($data['schedule']);
        $recurrence = $data['recurrence'] ?? null;
        if ($recurrence !== null && !is_array($recurrence)) {
            throw PlanningException::invalid('Invalid recurrence.');
        }
        $rule = $recurrence === null ? null : RecurrenceRule::fromArray($recurrence, $schedule)->describe();
        $visibility = $data['visibility'] ?? 'PRIVATE';
        $blocksTime = $data['blocksTime'] ?? true;
        $ownerParticipates = $data['ownerParticipates'] ?? true;
        if (!in_array($visibility, ['PRIVATE', 'TEAM'], true) || !is_bool($blocksTime) || !is_bool($ownerParticipates)) {
            throw PlanningException::invalid('Invalid event visibility or availability.');
        }
        $teamId = $data['teamId'] ?? null;
        if ($teamId !== null) {
            $teamId = self::uuid($teamId);
        }
        $invited = self::uuidList($data['participantIds'] ?? [], 50);
        $participantIds = $ownerParticipates
            ? array_values(array_unique(array_merge([$this->ownerId->value()], $invited)))
            : array_values(array_diff($invited, [$this->ownerId->value()]));
        if ($participantIds === []) {
            throw PlanningException::invalid('An event the author does not attend needs somebody who does.');
        }
        if ($teamId === null && ($visibility === 'TEAM' || count($participantIds) > 1 || !$ownerParticipates)) {
            throw PlanningException::invalid('A team is required to share an event or invite participants.');
        }
        return ['title' => trim($title), 'description' => $description, 'location' => $location, 'teamId' => $teamId, 'visibility' => $visibility, 'schedule' => $schedule->describe(), 'recurrence' => $rule, 'participantIds' => $participantIds, 'tagIds' => self::uuidList($data['tagIds'] ?? [], 20), 'blocksTime' => $blocksTime, 'ownerParticipates' => $ownerParticipates];
    }

    public static function uuid(mixed $value): string
    {
        if (!is_string($value)) {
            throw PlanningException::invalid('Invalid identifier.');
        }
        try {
            return Uuid::fromString(strtolower($value))->value();
        } catch (\InvalidArgumentException) {
            throw PlanningException::invalid('Invalid identifier.');
        }
    }

    public static function uuidList(mixed $values, int $limit): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > $limit) {
            throw PlanningException::invalid('Invalid identifier list.');
        }
        return array_values(array_unique(array_map(self::uuid(...), $values)));
    }

    public function setException(string $key, array $payload): void
    {
        if (($payload['cancelled'] ?? false) === true) {
            $this->exceptions[$key] = ['cancelled' => true];
        } else {
            $changes = $payload['changes'] ?? null;
            $allowed = ['title', 'description', 'location', 'schedule', 'participantIds', 'tagIds', 'visibility', 'blocksTime'];
            if (!is_array($changes) || $changes === [] || array_diff(array_keys($changes), $allowed) !== []) {
                throw PlanningException::invalid('Invalid occurrence changes.');
            }
            $changes = array_replace($this->exceptions[$key]['changes'] ?? [], $changes);
            $base = $this->definition;
            $base['recurrence'] = null;
            $normalized = $this->normalize(array_replace($base, $changes));
            $this->exceptions[$key] = ['changes' => array_intersect_key($normalized, $changes)];
        }
        $this->touch();
    }

    public function restoreException(string $key): void
    {
        unset($this->exceptions[$key]);
        $this->touch();
    }

    public function participate(string $personId, string $status, ?string $key): void
    {
        if (!in_array($status, ['INCLUDED', 'DECLINED'], true)) {
            throw PlanningException::invalid('Invalid participation change.');
        }
        $participants = $key === null ? $this->participants : $this->participantsFor($key);
        if (!array_key_exists($personId, $participants)) {
            throw PlanningException::notFound();
        }
        if ($key === null) {
            $this->participants[$personId] = $status;
            foreach ($this->participationExceptions as $occurrence => $statuses) {
                unset($this->participationExceptions[$occurrence][$personId]);
            }
        } else {
            $this->participationExceptions[$key][$personId] = $status;
        }
        $this->touch();
    }

    public function participantsFor(string $key): array
    {
        $ids = $this->exceptions[$key]['changes']['participantIds'] ?? $this->definition['participantIds'];
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->participationExceptions[$key][$id] ?? $this->participants[$id] ?? 'INCLUDED';
        }
        return $result;
    }

    public function allParticipantIds(): array
    {
        $ids = array_keys($this->participants);
        foreach ($this->exceptions as $exception) {
            $ids = array_merge($ids, $exception['changes']['participantIds'] ?? []);
        }
        return array_values(array_unique($ids));
    }

    public function revokeMember(string $personId, string $teamId): void
    {
        if ($this->teamId?->value() !== $teamId) {
            return;
        }
        if ($this->ownerId->value() === $personId) {
            if (!$this->ownerParticipates()) {
                $this->cancel();
                return;
            }
            $this->teamId = null;
            $this->definition['teamId'] = null;
            $this->definition['visibility'] = 'PRIVATE';
            foreach ($this->allParticipantIds() as $participantId) {
                if ($participantId !== $personId) {
                    $this->removeParticipant($participantId);
                }
            }
            foreach ($this->exceptions as $key => $exception) {
                if (isset($exception['changes']['visibility'])) {
                    $this->exceptions[$key]['changes']['visibility'] = 'PRIVATE';
                }
            }
        } else {
            $this->removeParticipant($personId);
            if ($this->allParticipantIds() === []) {
                $this->cancel();
                return;
            }
        }
        $this->touch();
    }

    private function removeParticipant(string $personId): void
    {
        unset($this->participants[$personId]);
        $this->definition['participantIds'] = array_values(array_diff($this->definition['participantIds'], [$personId]));
        $this->removeParticipantOverrides($personId);
    }

    private function removeParticipantOverrides(string $personId): void
    {
        foreach ($this->exceptions as $key => $exception) {
            if (isset($exception['changes']['participantIds'])) {
                $this->exceptions[$key]['changes']['participantIds'] = array_values(array_diff($exception['changes']['participantIds'], [$personId]));
            }
        }
        foreach ($this->participationExceptions as $key => $statuses) {
            unset($this->participationExceptions[$key][$personId]);
        }
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        $this->touch();
    }

    private function touch(): void
    {
        $this->personIds = $this->allParticipantIds();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): Uuid { return $this->id; }
    public function ownerId(): Uuid { return $this->ownerId; }
    public function teamId(): ?Uuid { return $this->teamId; }
    public function version(): int { return $this->version; }
    public function cancelled(): bool { return $this->cancelled; }
    public function definition(): array { return $this->definition; }
    public function ownerParticipates(): bool { return $this->definition['ownerParticipates'] ?? true; }
    public function exceptions(): array { return $this->exceptions; }

    public function describe(): array
    {
        return $this->definition + ['id' => $this->id->value(), 'ownerId' => $this->ownerId->value(), 'version' => $this->version, 'participants' => self::participantList($this->participants), 'exceptions' => $this->exceptions, 'cancelled' => $this->cancelled];
    }

    public function confirmationState(): array
    {
        return ['definition' => $this->definition, 'participants' => $this->participants, 'exceptions' => $this->exceptions, 'participationExceptions' => $this->participationExceptions, 'version' => $this->version];
    }

    public static function participantList(array $participants): array
    {
        $result = [];
        foreach ($participants as $id => $status) {
            $result[] = ['personId' => $id, 'status' => $status];
        }
        return $result;
    }
}
