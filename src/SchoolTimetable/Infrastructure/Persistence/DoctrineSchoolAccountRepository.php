<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Infrastructure\Persistence;

use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSchoolAccountRepository implements SchoolAccountRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function ofTeam(Uuid $teamId): ?SchoolAccount
    {
        return $this->entityManager->getRepository(SchoolAccount::class)->findOneBy(['teamId' => $teamId->value()]);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(SchoolAccount::class)->findAll();
    }

    public function save(SchoolAccount $account): void
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    public function remove(SchoolAccount $account): void
    {
        $this->entityManager->remove($account);
        $this->entityManager->flush();
    }
}
