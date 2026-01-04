<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Query\GetUserTeamsQuery;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetUserTeamsQueryHandler
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository
    ) {
    }

    /**
     * @return Team[]
     */
    public function __invoke(GetUserTeamsQuery $query): array
    {
        $userId = Uuid::fromString($query->userId);
        return $this->teamRepository->findByUserId($userId);
    }
}
