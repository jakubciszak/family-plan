<?php

declare(strict_types=1);

namespace App\DayPlanning\Infrastructure\Persistence;

use App\DayPlanning\Domain\Entity\CalendarTag;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCalendarTagRepository implements CalendarTagRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?CalendarTag
    {
        return $this->entityManager->find(CalendarTag::class, $id);
    }

    public function visibleTo(Uuid $ownerId, ?Uuid $teamId): array
    {
        $query = $this->entityManager->createQueryBuilder()->select('tag')->from(CalendarTag::class, 'tag')
            ->where('tag.archived = false')->andWhere($teamId === null ? '(tag.scope = :personal AND tag.ownerId = :owner)' : '((tag.scope = :personal AND tag.ownerId = :owner) OR tag.teamId = :team)')
            ->setParameter('personal', 'PERSONAL')->setParameter('owner', $ownerId->value());
        if ($teamId !== null) {
            $query->setParameter('team', $teamId->value());
        }
        return $query->orderBy('tag.name', 'ASC')->getQuery()->getResult();
    }

    public function save(CalendarTag $tag): void
    {
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
    }
}
