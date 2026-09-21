<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\Payout;
use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\Allowance\Domain\ValueObject\PayoutStatus;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePayoutRepository implements PayoutRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?Payout
    {
        return $this->entityManager->getRepository(Payout::class)->find($id->value());
    }

    public function awaitingConfirmation(Uuid $userId): array
    {
        return $this->entityManager->getRepository(Payout::class)->findBy(
            ['userId' => $userId, 'status' => PayoutStatus::AWAITING_CONFIRMATION],
            ['offeredAt' => 'ASC']
        );
    }

    public function ofUser(Uuid $userId, ?int $limit = 50): array
    {
        return $this->entityManager->getRepository(Payout::class)->findBy(
            ['userId' => $userId],
            ['offeredAt' => 'DESC'],
            $limit
        );
    }

    public function save(Payout $payout): void
    {
        $this->entityManager->persist($payout);
        $this->entityManager->flush();
    }
}
