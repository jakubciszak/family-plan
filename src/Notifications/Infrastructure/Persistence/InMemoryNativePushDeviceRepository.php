<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Repository\NativePushDeviceRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryNativePushDeviceRepository implements NativePushDeviceRepositoryInterface
{
    /** @var array<string, NativePushDevice> */
    private array $devices = [];

    public function save(NativePushDevice $device): void
    {
        $this->devices[$device->token()] = $device;
    }

    public function delete(NativePushDevice $device): void
    {
        unset($this->devices[$device->token()]);
    }

    public function findByToken(string $token): ?NativePushDevice
    {
        return $this->devices[$token] ?? null;
    }

    public function findForUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->devices,
            static fn (NativePushDevice $device) => $device->belongsTo($userId)
        ));
    }

    public function countForUser(Uuid $userId): int
    {
        return count($this->findForUser($userId));
    }
}
