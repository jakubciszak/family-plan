<?php

declare(strict_types=1);

namespace App\UserManagement\Infrastructure\Security;

use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $email = Email::fromString($identifier);
        } catch (\InvalidArgumentException $e) {
            throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
