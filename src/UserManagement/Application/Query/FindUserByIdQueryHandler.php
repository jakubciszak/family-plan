<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Query;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class FindUserByIdQueryHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(FindUserByIdQuery $query): ?User
    {
        return $this->userRepository->findById(Uuid::fromString($query->id));
    }
}
