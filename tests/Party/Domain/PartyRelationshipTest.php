<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Event\PartyRelationshipCreated;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class PartyRelationshipTest extends TestCase
{
    public function testRelationshipBindsTheRolesTwoPartiesPlay(): void
    {
        $id = Uuid::generate();
        $member = $this->memberRole('Alice');
        $team = $this->teamRole('Smith Family');

        $relationship = PartyRelationship::create($id, $member, $team, PartyRelationshipType::memberOf());

        $this->assertEquals($id, $relationship->id());
        $this->assertSame($member, $relationship->from());
        $this->assertSame($team, $relationship->to());
        $this->assertTrue($relationship->type()->isMemberOf());
        $this->assertInstanceOf(DateTimeImmutable::class, $relationship->createdAt());
        $this->assertNull($relationship->endedAt());
        $this->assertTrue($relationship->isActive());
    }

    public function testRelationshipReachesThroughToThePartiesBehindTheRoles(): void
    {
        $member = $this->memberRole('Alice');
        $team = $this->teamRole('Smith Family');

        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $member,
            $team,
            PartyRelationshipType::memberOf()
        );

        $this->assertSame($member->party(), $relationship->fromParty());
        $this->assertSame($team->party(), $relationship->toParty());
        $this->assertTrue($relationship->isPlayedFrom($member->partyId()));
        $this->assertTrue($relationship->isPlayedTo($team->partyId()));
    }

    public function testCreationRecordsTheRolesThatWereBound(): void
    {
        $admin = $this->adminRole('Bob');
        $team = $this->teamRole('Jones Family');

        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $admin,
            $team,
            PartyRelationshipType::adminOf()
        );
        $events = $relationship->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PartyRelationshipCreated::class, $events[0]);
        $this->assertTrue($events[0]->fromPartyRoleId->equals($admin->id()));
        $this->assertTrue($events[0]->toPartyRoleId->equals($team->id()));
    }

    public function testDomainEventsAreClearedAfterPulling(): void
    {
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $this->memberRole('Carol'),
            $this->teamRole('Brown Family'),
            PartyRelationshipType::memberOf()
        );

        $relationship->pullDomainEvents();

        $this->assertCount(0, $relationship->pullDomainEvents());
    }

    public function testRelationshipTypeRefusesRolesItDoesNotConnect(): void
    {
        $this->expectException(DomainException::class);

        PartyRelationship::create(
            Uuid::generate(),
            $this->memberRole('Dave'),
            $this->teamRole('Green Family'),
            PartyRelationshipType::adminOf()
        );
    }

    public function testCanEndRelationship(): void
    {
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $this->memberRole('Eve'),
            $this->teamRole('White Family'),
            PartyRelationshipType::memberOf()
        );
        $endDate = new DateTimeImmutable('2024-06-01');

        $relationship->end($endDate);

        $this->assertEquals($endDate, $relationship->endedAt());
        $this->assertFalse($relationship->isActive());
    }

    public function testCanCheckWhichRolesAreOnEachEnd(): void
    {
        $member = $this->memberRole('Frank');
        $team = $this->teamRole('Black Family');

        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $member,
            $team,
            PartyRelationshipType::memberOf()
        );

        $this->assertTrue($relationship->isFrom($member->id()));
        $this->assertTrue($relationship->isTo($team->id()));
        $this->assertFalse($relationship->isFrom($team->id()));
        $this->assertFalse($relationship->isTo($member->id()));
    }

    private function memberRole(string $name): PartyRole
    {
        return PartyRole::start(
            Uuid::generate(),
            Person::create(Uuid::generate(), $name, Email::fromString(strtolower($name) . '@example.com')),
            PartyRoleType::teamMember()
        );
    }

    private function adminRole(string $name): PartyRole
    {
        return PartyRole::start(
            Uuid::generate(),
            Person::create(Uuid::generate(), $name, Email::fromString(strtolower($name) . '@example.com')),
            PartyRoleType::teamAdmin()
        );
    }

    private function teamRole(string $name): PartyRole
    {
        return PartyRole::start(
            Uuid::generate(),
            Organization::create(Uuid::generate(), $name, null),
            PartyRoleType::team()
        );
    }
}
