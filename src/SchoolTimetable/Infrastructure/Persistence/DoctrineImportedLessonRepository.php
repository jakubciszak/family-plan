<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Infrastructure\Persistence;

use App\SchoolTimetable\Domain\Entity\ImportedLesson;
use App\SchoolTimetable\Domain\Repository\ImportedLessonRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineImportedLessonRepository implements ImportedLessonRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function between(Uuid $accountId, string $from, string $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('lesson')
            ->from(ImportedLesson::class, 'lesson')
            ->where('lesson.accountId = :account')
            ->andWhere('lesson.occursOn >= :from')
            ->andWhere('lesson.occursOn <= :to')
            ->setParameter('account', $accountId->value())
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    public function save(ImportedLesson $lesson): void
    {
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();
    }

    public function remove(ImportedLesson $lesson): void
    {
        $this->entityManager->remove($lesson);
        $this->entityManager->flush();
    }
}
