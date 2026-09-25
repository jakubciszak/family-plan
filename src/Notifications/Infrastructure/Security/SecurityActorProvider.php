<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Security;

use App\Notifications\Application\Port\ActorProviderInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityActorProvider implements ActorProviderInterface
{
    public function __construct(
        private Security $security,
        private UserRepositoryInterface $users
    ) {
    }

    public function currentActorId(): ?Uuid
    {
        $identifier = $this->security->getUser()?->getUserIdentifier();

        if ($identifier === null || $identifier === '') {
            return null;
        }

        try {
            return $this->users->findByEmail(Email::fromString($identifier))?->id();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
