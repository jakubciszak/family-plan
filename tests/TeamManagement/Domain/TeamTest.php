<?php

declare(strict_types=1);

namespace App\Tests\TeamManagement\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\ValueObject\TeamName;
use PHPUnit\Framework\TestCase;

class TeamTest extends TestCase
{
    public function testCanCreateTeam(): void
    {
        $id = Uuid::generate();
        $name = TeamName::fromString('Test Team');
        $description = 'This is a test team';
        $createdBy = Uuid::generate();

        $team = Team::create($id, $name, $description, $createdBy);

        $this->assertEquals($id, $team->id());
        $this->assertEquals($name, $team->name());
        $this->assertEquals($description, $team->description());
        $this->assertEquals($createdBy, $team->createdBy());
        $this->assertNotNull($team->createdAt());
        $this->assertNull($team->updatedAt());
    }

    public function testCanUpdateTeamName(): void
    {
        $team = Team::create(
            Uuid::generate(),
            TeamName::fromString('Old Name'),
            'Description',
            Uuid::generate()
        );

        $newName = TeamName::fromString('New Name');
        $team->updateName($newName);

        $this->assertEquals($newName, $team->name());
        $this->assertNotNull($team->updatedAt());
    }

    public function testCanUpdateTeamDescription(): void
    {
        $team = Team::create(
            Uuid::generate(),
            TeamName::fromString('Team Name'),
            'Old Description',
            Uuid::generate()
        );

        $newDescription = 'New Description';
        $team->updateDescription($newDescription);

        $this->assertEquals($newDescription, $team->description());
        $this->assertNotNull($team->updatedAt());
    }

    public function testTeamCreationRecordsDomainEvent(): void
    {
        $team = Team::create(
            Uuid::generate(),
            TeamName::fromString('Test Team'),
            'Description',
            Uuid::generate()
        );

        $events = $team->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\TeamManagement\Domain\Event\TeamCreated::class, $events[0]);
    }
}
