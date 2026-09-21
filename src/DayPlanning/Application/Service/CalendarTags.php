<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Service;

use App\DayPlanning\Domain\Entity\CalendarTag;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class CalendarTags
{
    public function __construct(private CalendarTagRepositoryInterface $tags, private PlanningAccess $access)
    {
    }

    public function list(Uuid $caller, ?string $teamId): array
    {
        $this->access->assertTeam($caller, $teamId);
        return ['tags' => array_map(fn (CalendarTag $tag): array => $tag->describe() + ['canEdit' => $this->access->canManageTag($tag, $caller)], $this->tags->visibleTo($caller, $teamId === null ? null : Uuid::fromString($teamId)))];
    }

    public function create(Uuid $caller, array $data): array
    {
        $tag = CalendarTag::create(Uuid::generate(), $caller, $data);
        $this->assertManage($tag, $caller);
        $this->tags->save($tag);
        return $tag->describe() + ['canEdit' => true];
    }

    public function update(Uuid $caller, Uuid $id, array $data): array
    {
        $tag = $this->owned($caller, $id);
        $tag->revise($data);
        $this->tags->save($tag);
        return $tag->describe() + ['canEdit' => true];
    }

    public function archive(Uuid $caller, Uuid $id): void
    {
        $tag = $this->owned($caller, $id);
        $tag->archive();
        $this->tags->save($tag);
    }

    private function owned(Uuid $caller, Uuid $id): CalendarTag
    {
        $tag = $this->tags->find($id);
        if ($tag === null || $tag->archived() || !$this->access->canManageTag($tag, $caller)) {
            throw PlanningException::notFound();
        }
        return $tag;
    }

    private function assertManage(CalendarTag $tag, Uuid $caller): void
    {
        if (!$this->access->canManageTag($tag, $caller)) {
            throw new PlanningException('access_denied', 403);
        }
    }
}
