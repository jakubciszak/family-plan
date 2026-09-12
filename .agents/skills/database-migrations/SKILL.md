---
name: database-migrations
description: Doctrine ORM and database migrations for PostgreSQL. Use this skill when creating entities, generating migrations, managing database schema, or working with Doctrine repositories.
---

# Database Migrations Skill

This skill provides guidance for managing the PostgreSQL database using Doctrine ORM and migrations in the Family Plan backend.

## Migration Commands

### Essential Commands

```bash
# Generate migration from entity changes
make db-diff
# or: docker compose exec php php bin/console doctrine:migrations:diff

# Run pending migrations
make db-migrate
# or: docker compose exec php php bin/console doctrine:migrations:migrate

# Reset database (drop + create + migrate)
make db-reset

# Check migration status
docker compose exec php php bin/console doctrine:migrations:status

# List all migrations
docker compose exec php php bin/console doctrine:migrations:list
```

### Migration Management

```bash
# Roll back last migration
docker compose exec php php bin/console doctrine:migrations:migrate prev

# Roll back to specific version
docker compose exec php php bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version20240101120000'

# Execute single migration up
docker compose exec php php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20240101120000' --up

# Execute single migration down
docker compose exec php php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20240101120000' --down

# Skip migration (mark as executed without running)
docker compose exec php php bin/console doctrine:migrations:version 'DoctrineMigrations\Version20240101120000' --add
```

## Entity Definition

### Basic Entity

```php
declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
#[ORM\Index(columns: ['team_id'], name: 'idx_task_team')]
#[ORM\Index(columns: ['status'], name: 'idx_task_status')]
final class Task
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'integer')]
    private int $points;

    #[ORM\Column(type: 'uuid', name: 'team_id')]
    private Uuid $teamId;

    #[ORM\Column(type: 'uuid', name: 'assignee_id', nullable: true)]
    private ?Uuid $assigneeId = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;
}
```

### Entity with Relations

```php
declare(strict_types=1);

namespace App\TeamManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'teams')]
final class Team
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /** @var Collection<int, TeamMember> */
    #[ORM\OneToMany(
        mappedBy: 'team',
        targetEntity: TeamMember::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $members;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private User $owner;

    private function __construct()
    {
        $this->members = new ArrayCollection();
    }

    public function addMember(TeamMember $member): void
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }
    }

    public function removeMember(TeamMember $member): void
    {
        $this->members->removeElement($member);
    }

    /** @return Collection<int, TeamMember> */
    public function members(): Collection
    {
        return $this->members;
    }
}
```

## Custom Doctrine Types

### UUID Type

```php
// config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            uuid: App\Shared\Infrastructure\Doctrine\Type\UuidType

// Implementation
declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UuidType extends Type
{
    public const NAME = 'uuid';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Uuid
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Uuid ? $value->toString() : $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
```

## Migration File Structure

```php
// migrations/Version20240115120000.php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tasks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE tasks (
                id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                status VARCHAR(50) NOT NULL,
                points INTEGER NOT NULL,
                team_id UUID NOT NULL,
                assignee_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        $this->addSql('CREATE INDEX idx_task_team ON tasks (team_id)');
        $this->addSql('CREATE INDEX idx_task_status ON tasks (status)');

        $this->addSql('
            ALTER TABLE tasks
            ADD CONSTRAINT fk_task_team
            FOREIGN KEY (team_id) REFERENCES teams (id)
            ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tasks');
    }
}
```

## Repository Implementation

```php
declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineTaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function save(Task $task): void
    {
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();
    }

    public function remove(Task $task): void
    {
        $this->getEntityManager()->remove($task);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Task
    {
        return $this->find($id->toString());
    }

    public function findByTeamId(Uuid $teamId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.teamId = :teamId')
            ->setParameter('teamId', $teamId->toString())
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByAssignee(Uuid $assigneeId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.assigneeId = :assigneeId')
            ->andWhere('t.status = :status')
            ->setParameter('assigneeId', $assigneeId->toString())
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();
    }
}
```

## Database Console Operations

```bash
# Access PostgreSQL shell
make shell-db

# Inside psql:
\dt                    # List all tables
\d+ tasks              # Show table structure
\di                    # List indexes

# Common queries
SELECT * FROM tasks LIMIT 10;
SELECT COUNT(*) FROM tasks WHERE status = 'pending';

# Show foreign keys
SELECT
    tc.table_name,
    kcu.column_name,
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu
    ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage AS ccu
    ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY';
```

## Schema Validation

```bash
# Validate mapping files
docker compose exec php php bin/console doctrine:schema:validate

# Show SQL that would be executed
docker compose exec php php bin/console doctrine:schema:update --dump-sql

# Compare current schema with entities (dry run)
docker compose exec php php bin/console doctrine:migrations:diff --no-interaction
```

## Best Practices

1. **Never edit executed migrations** - Create new migration instead
2. **Use descriptive migration descriptions** - Helps with debugging
3. **Add indexes for frequently queried columns** - team_id, status, etc.
4. **Use foreign keys** - Maintain referential integrity
5. **Test migrations both ways** - up() and down() should be reversible
6. **Use transactions** - Wrap complex migrations in transactions
7. **Review generated SQL** - Check diff before applying

## Common Issues

### Migration Version Mismatch

```bash
# Mark all migrations as executed
docker compose exec php php bin/console doctrine:migrations:sync-metadata-storage
```

### Entity Not Found

```bash
# Clear metadata cache
docker compose exec php php bin/console doctrine:cache:clear-metadata
```

### Schema Out of Sync

```bash
# Reset and re-run all migrations
make db-reset
```
