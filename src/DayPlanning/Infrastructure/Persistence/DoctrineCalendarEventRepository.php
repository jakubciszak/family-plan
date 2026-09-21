<?php

declare(strict_types=1);

namespace App\DayPlanning\Infrastructure\Persistence;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final readonly class DoctrineCalendarEventRepository implements CalendarEventRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?CalendarEvent
    {
        return $this->entityManager->find(CalendarEvent::class, $id);
    }

    public function forPeople(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $ids = $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM day_planning_events WHERE cancelled = FALSE AND jsonb_exists_any(person_ids, ARRAY['.$placeholders.']::text[])', $personIds);
        if ($ids === []) {
            return [];
        }
        return $this->entityManager->createQueryBuilder()->select('event')->from(CalendarEvent::class, 'event')->where('event.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
    }

    public function findByTeam(string $teamId): array
    {
        return $this->entityManager->getRepository(CalendarEvent::class)->findBy(['teamId' => $teamId]);
    }

    public function save(CalendarEvent $event): void
    {
        $this->entityManager->persist($event);
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new PlanningException('version_conflict', 412);
        }
    }

    public function transactional(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        return $connection->transactional(function () use ($operation, $connection): mixed {
            if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $connection->executeQuery("SELECT pg_advisory_xact_lock(hashtextextended('day-planning-write', 0))");
            }
            return $operation();
        });
    }

    public function idempotentResult(string $ownerId, string $key, string $hash): ?array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT request_hash, response FROM day_planning_idempotency WHERE owner_id = ? AND request_key = ?', [$ownerId, $key]);
        if ($row === false) {
            return null;
        }
        if (!hash_equals($row['request_hash'], $hash)) {
            throw new PlanningException('idempotency_key_reused', 409);
        }
        return json_decode($row['response'], true, 512, JSON_THROW_ON_ERROR);
    }

    public function rememberResult(string $ownerId, string $key, string $hash, array $result): void
    {
        $this->entityManager->getConnection()->insert('day_planning_idempotency', ['owner_id' => $ownerId, 'request_key' => $key, 'request_hash' => $hash, 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }
}
