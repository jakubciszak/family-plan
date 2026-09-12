<?php

declare(strict_types=1);

namespace App\Party\Application\Service;

use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Entity\Responsibility;
use App\Party\Domain\Entity\Signature;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\Repository\PartyRoleRepositoryInterface;
use App\Party\Domain\Repository\ResponsibilityRepositoryInterface;
use App\Party\Domain\Repository\SignatureRepositoryInterface;
use App\Party\Domain\Service\RoleResponsibilities;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\ValueObject\Uuid;
use DomainException;

final readonly class PartyResponsibilities
{
    public function __construct(
        private PartyRoleRepositoryInterface $roleRepository,
        private PartyRelationshipRepositoryInterface $relationshipRepository,
        private ResponsibilityRepositoryInterface $responsibilityRepository,
        private SignatureRepositoryInterface $signatureRepository
    ) {
    }

    public function partyMay(Uuid $partyId, ResponsibilityType $type, Uuid $withinOrganizationId): bool
    {
        return $this->roleCarrying($partyId, $type, $withinOrganizationId) !== null;
    }

    public function sign(
        Uuid $partyId,
        ResponsibilityType $act,
        Uuid $subjectId,
        Uuid $withinOrganizationId
    ): Signature {
        $role = $this->roleCarrying($partyId, $act, $withinOrganizationId);

        if ($role === null) {
            throw new DomainException(sprintf(
                'This party carries no %s responsibility in that organization',
                $act->value()
            ));
        }

        $signature = Signature::sign(Uuid::generate(), $role, $act, $subjectId);
        $this->signatureRepository->save($signature);

        return $signature;
    }

    /**
     * @return string[]
     */
    public function organizationsWhereMay(Uuid $partyId, ResponsibilityType $type): array
    {
        $organizationIds = [];

        foreach ($this->roleRepository->findByParty($partyId) as $role) {
            if (!$role->isActive() || !$this->carries($role, $type)) {
                continue;
            }

            foreach ($this->relationshipRepository->findByFromRole($role->id()) as $relationship) {
                if ($relationship->isActive()) {
                    $organizationIds[$relationship->to()->partyId()->value()] = true;
                }
            }
        }

        return array_keys($organizationIds);
    }

    public function allocateDefaults(PartyRole $role): void
    {
        $held = array_map(
            static fn (Responsibility $responsibility) => $responsibility->type()->value(),
            $this->responsibilityRepository->findByRole($role->id())
        );

        foreach (RoleResponsibilities::of($role->type()) as $type) {
            if (in_array($type->value(), $held, true)) {
                continue;
            }

            $this->responsibilityRepository->save(
                Responsibility::allocate(Uuid::generate(), $role, $type)
            );
        }
    }

    private function roleCarrying(Uuid $partyId, ResponsibilityType $type, Uuid $withinOrganizationId): ?PartyRole
    {
        foreach ($this->roleRepository->findByParty($partyId) as $role) {
            if ($role->isActive() && $this->carries($role, $type) && $this->relatesTo($role, $withinOrganizationId)) {
                return $role;
            }
        }

        return null;
    }

    private function carries(PartyRole $role, ResponsibilityType $type): bool
    {
        foreach ($this->responsibilityRepository->findByRole($role->id()) as $responsibility) {
            if ($responsibility->covers($type)) {
                return true;
            }
        }

        return false;
    }

    private function relatesTo(PartyRole $role, Uuid $organizationId): bool
    {
        foreach ($this->relationshipRepository->findByFromRole($role->id()) as $relationship) {
            if ($relationship->isActive() && $relationship->to()->partyId()->equals($organizationId)) {
                return true;
            }
        }

        return false;
    }
}
