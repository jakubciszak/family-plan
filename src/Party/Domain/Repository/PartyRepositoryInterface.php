<?php

declare(strict_types=1);

namespace App\Party\Domain\Repository;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\Party;
use App\Party\Domain\Entity\Person;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Party Repository Interface
 *
 * Repository for managing Party entities (Person, Organization)
 */
interface PartyRepositoryInterface
{
    /**
     * Save a party to the repository
     */
    public function save(Party $party): void;

    /**
     * Find a party by its ID
     */
    public function findById(Uuid $id): ?Party;

    /**
     * Find all parties
     *
     * @return Party[]
     */
    public function findAll(): array;

    /**
     * Find all persons
     *
     * @return Person[]
     */
    public function findPersons(): array;

    /**
     * Find all organizations
     *
     * @return Organization[]
     */
    public function findOrganizations(): array;
}
