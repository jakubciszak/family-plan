<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Event\PartyRoleStarted;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use DomainException;
use PHPUnit\Framework\TestCase;

class PartyRoleTest extends TestCase
{
    public function testAPersonPlaysTheRoleOfATeamMember(): void
    {
        $person = $this->person();
        $id = Uuid::generate();

        $role = PartyRole::start($id, $person, PartyRoleType::teamMember());

        $this->assertEquals($id, $role->id());
        $this->assertSame($person, $role->party());
        $this->assertTrue($role->partyId()->equals($person->id()));
        $this->assertTrue($role->type()->equals(PartyRoleType::teamMember()));
        $this->assertTrue($role->isActive());
        $this->assertNull($role->endedAt());
    }

    public function testStartingARoleIsRecorded(): void
    {
        $person = $this->person();

        $role = PartyRole::start(Uuid::generate(), $person, PartyRoleType::teamAdmin());
        $events = $role->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PartyRoleStarted::class, $events[0]);
        $this->assertTrue($events[0]->partyId->equals($person->id()));
        $this->assertCount(0, $role->pullDomainEvents());
    }

    public function testAnOrganizationCannotPlayAPersonsRole(): void
    {
        $this->expectException(DomainException::class);

        PartyRole::start(Uuid::generate(), $this->organization(), PartyRoleType::teamAdmin());
    }

    public function testAPersonCannotPlayTheTeamRole(): void
    {
        $this->expectException(DomainException::class);

        PartyRole::start(Uuid::generate(), $this->person(), PartyRoleType::team());
    }

    public function testEndedRoleIsNoLongerActive(): void
    {
        $role = PartyRole::start(Uuid::generate(), $this->person(), PartyRoleType::teamMember());

        $role->end();

        $this->assertFalse($role->isActive());
        $this->assertNotNull($role->endedAt());
    }

    public function testARoleCannotEndTwice(): void
    {
        $role = PartyRole::start(Uuid::generate(), $this->person(), PartyRoleType::teamMember());
        $role->end();

        $this->expectException(DomainException::class);

        $role->end();
    }

    private function person(): Person
    {
        return Person::create(Uuid::generate(), 'Alice', Email::fromString('alice@example.com'));
    }

    private function organization(): Organization
    {
        return Organization::create(Uuid::generate(), 'Smith Family', null);
    }
}
