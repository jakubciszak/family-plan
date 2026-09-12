<?php

declare(strict_types=1);

namespace App\Party\Communication\EventSubscriber;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Event\MemberAdded;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class MemberAddedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PartyAdapter $partyAdapter,
        private PartyResponsibilities $responsibilities,
        private PartyRelationshipRepositoryInterface $relationshipRepository,
        private TeamRepositoryInterface $teamRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MemberAdded::class => 'onMemberAdded',
        ];
    }

    public function onMemberAdded(MemberAdded $event): void
    {
        $user = $this->userRepository->findById($event->userId);
        $team = $this->teamRepository->findById($event->teamId);

        if ($user === null || $team === null) {
            return;
        }

        $relationshipType = $event->role->isAdmin()
            ? PartyRelationshipType::adminOf()
            : PartyRelationshipType::memberOf();

        $person = $this->partyAdapter->getOrStartRole(
            $this->partyAdapter->getOrCreatePerson($user),
            $relationshipType->fromRoleType()
        );
        $organization = $this->partyAdapter->getOrStartRole(
            $this->partyAdapter->getOrCreateOrganization($team),
            PartyRoleType::team()
        );

        $this->responsibilities->allocateDefaults($person);
        $this->responsibilities->allocateDefaults($organization);

        if ($this->alreadyRelated($person, $organization, $relationshipType)) {
            return;
        }

        $this->relationshipRepository->save(PartyRelationship::create(
            Uuid::generate(),
            $person,
            $organization,
            $relationshipType
        ));
    }

    private function alreadyRelated(
        PartyRole $from,
        PartyRole $to,
        PartyRelationshipType $type
    ): bool {
        foreach ($this->relationshipRepository->findByFromRole($from->id()) as $relationship) {
            if ($relationship->isActive()
                && $relationship->isTo($to->id())
                && $relationship->type()->equals($type)
            ) {
                return true;
            }
        }

        return false;
    }
}
