<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\TestDatabase;
use App\TeamManagement\Application\Command\CreateTeamCommand;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class IntegrationTestCase extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        TestDatabase::prepareOnce();
        self::bootKernel();

        $this->connection = $this->service(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    protected function service(string $id): object
    {
        return static::getContainer()->get($id);
    }

    protected function user(string $name = 'Rodzic', Role $role = Role::USER): User
    {
        $user = User::create(
            Uuid::generate(),
            $name,
            Email::fromString(sprintf('%s-%s@example.com', strtolower($name), uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            $role
        );

        $this->service(UserRepositoryInterface::class)->save($user);

        return $user;
    }

    protected function team(User $founder, string $name = 'Rodzina'): Uuid
    {
        $teamId = Uuid::generate();

        $this->service('command.bus')->dispatch(
            new CreateTeamCommand($teamId->value(), $name, null, $founder->id()->value())
        );

        return $teamId;
    }

    protected function join(Uuid $teamId, User $user, TeamRole $role): void
    {
        $this->service(TeamMembershipRepositoryInterface::class)->join($teamId, $user->id(), $role);
    }
}
