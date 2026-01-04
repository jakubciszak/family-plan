<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;

interface TeamRepositoryInterface
{
    public function save(Team $team): void;
    
    public function findById(Uuid $id): ?Team;
    
    /**
     * @return Team[]
     */
    public function findAll(): array;
    
    /**
     * Find teams where the user is a member
     * @return Team[]
     */
    public function findByUserId(Uuid $userId): array;
    
    public function remove(Team $team): void;
}
