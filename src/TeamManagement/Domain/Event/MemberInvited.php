<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;

final class MemberInvited
{
    public function __construct(
        public readonly Uuid $invitationId,
        public readonly Uuid $teamId,
        public readonly Email $email,
        public readonly TeamRole $role,
        public readonly Uuid $invitedBy,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }
}
