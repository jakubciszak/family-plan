<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Repository;

use App\Notifications\Domain\Entity\NativePushDevice;
use App\Shared\Domain\ValueObject\Uuid;

interface NativePushDeviceRepositoryInterface
{
    public function save(NativePushDevice $device): void;

    public function delete(NativePushDevice $device): void;

    public function findByToken(string $token): ?NativePushDevice;

    /**
     * @return NativePushDevice[]
     */
    public function findForUser(Uuid $userId): array;

    public function countForUser(Uuid $userId): int;
}
