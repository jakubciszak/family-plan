<?php

declare(strict_types=1);

namespace App\Tests\Party\Domain;

use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Entity\Responsibility;
use App\Party\Domain\Service\RoleResponsibilities;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use DomainException;
use PHPUnit\Framework\TestCase;

class ResponsibilityTest extends TestCase
{
    public function testAResponsibilityCoversItsOwnType(): void
    {
        $responsibility = Responsibility::allocate(
            Uuid::generate(),
            $this->role(PartyRoleType::teamAdmin()),
            ResponsibilityType::approveTask()
        );

        $this->assertTrue($responsibility->covers(ResponsibilityType::approveTask()));
        $this->assertFalse($responsibility->covers(ResponsibilityType::defineTaskType()));
    }

    public function testAWithdrawnResponsibilityCoversNothing(): void
    {
        $responsibility = Responsibility::allocate(
            Uuid::generate(),
            $this->role(PartyRoleType::teamAdmin()),
            ResponsibilityType::approveTask()
        );

        $responsibility->withdraw();

        $this->assertFalse($responsibility->covers(ResponsibilityType::approveTask()));
        $this->assertFalse($responsibility->isActive());
    }

    public function testAResponsibilityDiesWithTheRoleThatCarriesIt(): void
    {
        $role = $this->role(PartyRoleType::teamAdmin());
        $responsibility = Responsibility::allocate(Uuid::generate(), $role, ResponsibilityType::approveTask());

        $role->end();

        $this->assertFalse($responsibility->covers(ResponsibilityType::approveTask()));
    }

    public function testAResponsibilityCannotBeWithdrawnTwice(): void
    {
        $responsibility = Responsibility::allocate(
            Uuid::generate(),
            $this->role(PartyRoleType::teamMember()),
            ResponsibilityType::takeTask()
        );
        $responsibility->withdraw();

        $this->expectException(DomainException::class);

        $responsibility->withdraw();
    }

    public function testOnlyTeamAdminsCarryApprovalAndDefinition(): void
    {
        $adminCarries = array_map(
            static fn (ResponsibilityType $type) => $type->value(),
            RoleResponsibilities::of(PartyRoleType::teamAdmin())
        );
        $memberCarries = array_map(
            static fn (ResponsibilityType $type) => $type->value(),
            RoleResponsibilities::of(PartyRoleType::teamMember())
        );

        $this->assertContains(ResponsibilityType::APPROVE_TASK, $adminCarries);
        $this->assertContains(ResponsibilityType::DEFINE_TASK_TYPE, $adminCarries);
        $this->assertContains(ResponsibilityType::ASSIGN_TASK, $adminCarries);

        $this->assertNotContains(ResponsibilityType::APPROVE_TASK, $memberCarries);
        $this->assertNotContains(ResponsibilityType::DEFINE_TASK_TYPE, $memberCarries);
        $this->assertNotContains(ResponsibilityType::ASSIGN_TASK, $memberCarries);
        $this->assertContains(ResponsibilityType::TAKE_TASK, $memberCarries);
        $this->assertContains(ResponsibilityType::COMPLETE_TASK, $memberCarries);
    }

    private function role(PartyRoleType $type): PartyRole
    {
        return PartyRole::start(
            Uuid::generate(),
            Person::create(Uuid::generate(), 'Alice', Email::fromString('alice@example.com')),
            $type
        );
    }
}
