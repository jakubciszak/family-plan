<?php

declare(strict_types=1);

namespace App\Tests\Party\Application;

use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRoleRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryResponsibilityRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemorySignatureRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Party\Mother\PartyRelationshipMother;
use App\UserManagement\Domain\ValueObject\Email;
use DomainException;
use PHPUnit\Framework\TestCase;

class PartyResponsibilitiesTest extends TestCase
{
    private InMemoryPartyRoleRepository $roles;

    private InMemoryPartyRelationshipRepository $relationships;

    private InMemoryResponsibilityRepository $responsibilities;

    private InMemorySignatureRepository $signatures;

    private PartyResponsibilities $service;

    protected function setUp(): void
    {
        $this->roles = new InMemoryPartyRoleRepository();
        $this->relationships = new InMemoryPartyRelationshipRepository();
        $this->responsibilities = new InMemoryResponsibilityRepository();
        $this->signatures = new InMemorySignatureRepository();
        $this->service = new PartyResponsibilities(
            $this->roles,
            $this->relationships,
            $this->responsibilities,
            $this->signatures
        );
    }

    public function testATeamAdminMayApproveInTheirOwnTeam(): void
    {
        $team = $this->organization();
        $admin = $this->join($this->person('Rodzic'), $team, PartyRelationshipType::adminOf());

        $this->assertTrue(
            $this->service->partyMay($admin->partyId(), ResponsibilityType::approveTask(), $team->id())
        );
    }

    public function testAPlainMemberMayNotApprove(): void
    {
        $team = $this->organization();
        $member = $this->join($this->person('Dziecko'), $team, PartyRelationshipType::memberOf());

        $this->assertFalse(
            $this->service->partyMay($member->partyId(), ResponsibilityType::approveTask(), $team->id())
        );
        $this->assertTrue(
            $this->service->partyMay($member->partyId(), ResponsibilityType::takeTask(), $team->id())
        );
    }

    public function testResponsibilityDoesNotReachIntoAnotherTeam(): void
    {
        $ownTeam = $this->organization();
        $otherTeam = $this->organization('Obca rodzina');
        $admin = $this->join($this->person('Rodzic'), $ownTeam, PartyRelationshipType::adminOf());

        $this->assertFalse(
            $this->service->partyMay($admin->partyId(), ResponsibilityType::approveTask(), $otherTeam->id())
        );
    }

    public function testEndedMembershipEndsTheResponsibility(): void
    {
        $team = $this->organization();
        $admin = $this->join($this->person('Rodzic'), $team, PartyRelationshipType::adminOf());

        foreach ($this->relationships->findByFromRole($admin->id()) as $relationship) {
            $relationship->end(new \DateTimeImmutable());
            $this->relationships->save($relationship);
        }

        $this->assertFalse(
            $this->service->partyMay($admin->partyId(), ResponsibilityType::approveTask(), $team->id())
        );
    }

    public function testSigningRecordsWhoActedOnWhat(): void
    {
        $team = $this->organization();
        $admin = $this->join($this->person('Rodzic'), $team, PartyRelationshipType::adminOf());
        $execution = Uuid::generate();

        $signature = $this->service->sign(
            $admin->partyId(),
            ResponsibilityType::approveTask(),
            $execution,
            $team->id()
        );

        $this->assertTrue($signature->signatoryPartyId()->equals($admin->partyId()));
        $this->assertTrue($signature->act()->equals(ResponsibilityType::approveTask()));
        $this->assertCount(1, $this->signatures->findBySubject($execution));
    }

    public function testNobodySignsForAResponsibilityTheyDoNotCarry(): void
    {
        $team = $this->organization();
        $member = $this->join($this->person('Dziecko'), $team, PartyRelationshipType::memberOf());

        $this->expectException(DomainException::class);

        $this->service->sign(
            $member->partyId(),
            ResponsibilityType::approveTask(),
            Uuid::generate(),
            $team->id()
        );
    }

    public function testAllocatingDefaultsTwiceLeavesOneOfEach(): void
    {
        $role = PartyRole::start(Uuid::generate(), $this->person('Rodzic'), PartyRoleType::teamAdmin());
        $this->roles->save($role);

        $this->service->allocateDefaults($role);
        $this->service->allocateDefaults($role);

        $this->assertCount(5, $this->responsibilities->findByRole($role->id()));
    }

    public function testTeamsWhereIMayApproveAreListed(): void
    {
        $ownTeam = $this->organization();
        $otherTeam = $this->organization('Druga rodzina');
        $person = $this->person('Rodzic');

        $admin = $this->join($person, $ownTeam, PartyRelationshipType::adminOf());
        $this->join($person, $otherTeam, PartyRelationshipType::memberOf());

        $this->assertSame(
            [$ownTeam->id()->value()],
            $this->service->organizationsWhereMay($admin->partyId(), ResponsibilityType::approveTask())
        );
    }

    private function join(Person $person, Organization $organization, PartyRelationshipType $type): PartyRole
    {
        $relationship = PartyRelationshipMother::create(
            Uuid::generate(),
            $person,
            $organization,
            $type,
            $this->roles
        );
        $this->relationships->save($relationship);

        $this->service->allocateDefaults($relationship->from());
        $this->service->allocateDefaults($relationship->to());

        return $relationship->from();
    }

    private function person(string $name): Person
    {
        return Person::create(
            Uuid::generate(),
            $name,
            Email::fromString(sprintf('%s-%s@example.com', strtolower($name), uniqid()))
        );
    }

    private function organization(string $name = 'Rodzina'): Organization
    {
        return Organization::create(Uuid::generate(), $name, null);
    }
}
