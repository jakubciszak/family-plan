<?php

declare(strict_types=1);

namespace App\UserManagement\Infrastructure\Security;

use App\UserManagement\Domain\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Your account is not activated. Please check your email for the activation link.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Nothing to check after authentication
    }
}
