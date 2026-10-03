<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;

final readonly class AllowanceAccess
{
    public function __construct(
        private UserRepositoryInterface $users,
        private Households $households,
        private \App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface $memberships
    ) {
    }

    public function callerFrom(string $email): Uuid
    {
        return $this->users->findByEmail(Email::fromString($email))->id();
    }

    /**
     * The member being looked at: yourself, or someone whose team you administer.
     */
    public function inspected(Uuid $caller, ?string $wanted): Uuid
    {
        if ($wanted === null || $wanted === $caller->value()) {
            return $caller;
        }

        $member = Uuid::fromString($wanted);

        if ($this->households->sharedWithAdmin($caller, $member) === null) {
            throw new UnauthorizedTeamActionException('Only an admin of their team looks after another member');
        }

        return $member;
    }

    public function adminTeamFor(Uuid $caller, Uuid $member, ?string $wantedTeam = null): Uuid
    {
        $teamId = $this->teamFor($caller, $member, $wantedTeam, true);

        if ($teamId === null) {
            throw new UnauthorizedTeamActionException('Only an admin of their team settles a week for a member');
        }

        return $teamId;
    }

    public function teamFor(Uuid $caller, Uuid $member, ?string $wantedTeam = null, bool $manage = false): ?Uuid
    {
        $teams = [];
        foreach ($this->memberships->ofUser($member) as $membership) {
            $team = $membership->teamId();
            if (($caller->equals($member) && !$manage) || $this->memberships->isAdmin($caller, $team)) {
                $teams[$team->value()] = $team;
            }
        }
        if ($wantedTeam !== null) {
            if (!isset($teams[$wantedTeam])) {
                throw new UnauthorizedTeamActionException('This household is not available to you');
            }
            return $teams[$wantedTeam];
        }
        if (count($teams) > 1) {
            throw new \DomainException('Choose a household using teamId');
        }
        if ($teams === [] && (!$caller->equals($member) || $manage)) {
            throw new UnauthorizedTeamActionException('This household is not available to you');
        }
        return $teams === [] ? null : array_values($teams)[0];
    }

    public function assertSelf(Uuid $caller, Uuid $owner): void
    {
        if (!$caller->equals($owner)) {
            throw new UnauthorizedTeamActionException('Only the owner of the money decides about it');
        }
    }
}
