<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: TeamMembershipRepositoryInterface::class)]
final readonly class CalendarAwareTeamMemberships implements TeamMembershipRepositoryInterface
{
    public function __construct(
        #[AutowireDecorated] private TeamMembershipRepositoryInterface $memberships,
        private CalendarEventRepositoryInterface $events,
    ) {
    }

    public function join(Uuid $teamId, Uuid $userId, TeamRole $role): ?TeamMembership
    {
        return $this->memberships->join($teamId, $userId, $role);
    }

    public function leave(Uuid $teamId, Uuid $userId): void
    {
        $this->events->transactional(function () use ($teamId, $userId): void {
            $this->memberships->leave($teamId, $userId);
            foreach ($this->events->findByTeam($teamId->value()) as $event) {
                $event->revokeMember($userId->value(), $teamId->value());
                $this->events->save($event);
            }
        });
    }

    public function find(Uuid $teamId, Uuid $userId): ?TeamMembership
    {
        return $this->memberships->find($teamId, $userId);
    }

    public function ofTeam(Uuid $teamId): array
    {
        return $this->memberships->ofTeam($teamId);
    }

    public function ofUser(Uuid $userId): array
    {
        return $this->memberships->ofUser($userId);
    }

    public function isMember(Uuid $userId, Uuid $teamId): bool
    {
        return $this->memberships->isMember($userId, $teamId);
    }

    public function isAdmin(Uuid $userId, Uuid $teamId): bool
    {
        return $this->memberships->isAdmin($userId, $teamId);
    }
}
