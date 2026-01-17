<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\Event\PersonCreated;
use App\Party\Domain\ValueObject\PartyType;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Person Entity
 *
 * Represents an individual human being in the Party archetype.
 * Concrete implementation of Party.
 *
 * @see https://www.softwarearchetypes.com/archetypes/party/
 */
#[ORM\Entity]
class Person extends Party
{
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'email')]
    private Email $email;

    /**
     * Private constructor - use static factory method create()
     */
    private function __construct(
        Uuid $id,
        PartyType $type,
        DateTimeImmutable $createdAt,
        string $name,
        Email $email
    ) {
        parent::__construct($id, $type, $createdAt);
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * Factory method to create a new Person
     */
    public static function create(Uuid $id, string $name, Email $email): self
    {
        $person = new self(
            $id,
            PartyType::person(),
            new DateTimeImmutable(),
            $name,
            $email
        );

        $person->record(new PersonCreated($id, $name, $email));

        return $person;
    }

    /**
     * Get person's name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get person's email
     */
    public function email(): Email
    {
        return $this->email;
    }

    /**
     * Implementation of Party::getName()
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Update person's name
     */
    public function updateName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Update person's email
     */
    public function updateEmail(Email $email): void
    {
        $this->email = $email;
    }
}
