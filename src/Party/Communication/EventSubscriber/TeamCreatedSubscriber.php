<?php

declare(strict_types=1);

namespace App\Party\Communication\EventSubscriber;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\TeamManagement\Domain\Event\TeamCreated;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class TeamCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PartyAdapter $partyAdapter,
        private PartyResponsibilities $responsibilities,
        private TeamRepositoryInterface $teamRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TeamCreated::class => 'onTeamCreated',
        ];
    }

    public function onTeamCreated(TeamCreated $event): void
    {
        $team = $this->teamRepository->findById($event->teamId);

        if ($team === null) {
            return;
        }

        $organization = $this->partyAdapter->getOrCreateOrganization($team);

        $this->responsibilities->allocateDefaults(
            $this->partyAdapter->getOrStartRole($organization, PartyRoleType::team())
        );
    }
}
