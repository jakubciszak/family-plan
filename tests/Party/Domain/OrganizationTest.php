<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Event\OrganizationCreated;
use App\Party\Domain\ValueObject\PartyType;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

/**
 * TDD: Organization Entity Tests
 *
 * Organization represents a group or entity in the Party archetype.
 * It's a concrete implementation of Party.
 */
class OrganizationTest extends TestCase
{
    public function testCanCreateOrganization(): void
    {
        // Given
        $id = Uuid::generate();
        $name = 'Smith Family';
        $description = 'The Smith family household';

        // When
        $organization = Organization::create($id, $name, $description);

        // Then
        $this->assertEquals($id, $organization->id());
        $this->assertEquals($name, $organization->name());
        $this->assertEquals($name, $organization->getName()); // Party interface
        $this->assertEquals($description, $organization->description());
        $this->assertTrue($organization->isOrganization());
        $this->assertFalse($organization->isPerson());
        $this->assertEquals(PartyType::organization(), $organization->type());
    }

    public function testCanCreateOrganizationWithoutDescription(): void
    {
        // Given
        $id = Uuid::generate();
        $name = 'Jones Family';

        // When
        $organization = Organization::create($id, $name, null);

        // Then
        $this->assertEquals($id, $organization->id());
        $this->assertEquals($name, $organization->name());
        $this->assertNull($organization->description());
    }

    public function testOrganizationCreationRecordsDomainEvent(): void
    {
        // Given
        $id = Uuid::generate();
        $name = 'Brown Family';
        $description = 'The Brown family';

        // When
        $organization = Organization::create($id, $name, $description);
        $events = $organization->pullDomainEvents();

        // Then
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrganizationCreated::class, $events[0]);

        /** @var OrganizationCreated $event */
        $event = $events[0];
        $this->assertEquals($id, $event->organizationId);
        $this->assertEquals($name, $event->name);
        $this->assertEquals($description, $event->description);
    }

    public function testDomainEventsAreClearedAfterPulling(): void
    {
        // Given
        $organization = Organization::create(
            Uuid::generate(),
            'Test Organization',
            'Test description'
        );

        // When
        $events1 = $organization->pullDomainEvents();
        $events2 = $organization->pullDomainEvents();

        // Then
        $this->assertCount(1, $events1);
        $this->assertCount(0, $events2);
    }

    public function testCanUpdateOrganizationName(): void
    {
        // Given
        $organization = Organization::create(
            Uuid::generate(),
            'Original Name',
            'Original description'
        );

        // When
        $organization->updateName('Updated Name');

        // Then
        $this->assertEquals('Updated Name', $organization->name());
        $this->assertEquals('Updated Name', $organization->getName());
    }

    public function testCanUpdateOrganizationDescription(): void
    {
        // Given
        $organization = Organization::create(
            Uuid::generate(),
            'Test Organization',
            'Old description'
        );

        // When
        $organization->updateDescription('New description');

        // Then
        $this->assertEquals('New description', $organization->description());
    }

    public function testOrganizationsWithSameIdAreEqual(): void
    {
        // Given
        $id = Uuid::generate();
        $org1 = Organization::create($id, 'Organization 1', 'Desc 1');
        $org2 = Organization::create($id, 'Organization 2', 'Desc 2');

        // Then
        $this->assertTrue($org1->equals($org2));
    }

    public function testOrganizationsWithDifferentIdsAreNotEqual(): void
    {
        // Given
        $org1 = Organization::create(Uuid::generate(), 'Organization 1', 'Desc 1');
        $org2 = Organization::create(Uuid::generate(), 'Organization 2', 'Desc 2');

        // Then
        $this->assertFalse($org1->equals($org2));
    }
}
