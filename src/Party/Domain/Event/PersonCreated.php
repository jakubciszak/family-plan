<?php

declare(strict_types=1);

namespace App\Party\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;

/**
 * Domain Event: Person Created
 *
 * Emitted when a new Person is created in the Party archetype.
 */
final readonly class PersonCreated
{
    public function __construct(
        public Uuid $personId,
        public string $name,
        public Email $email
    ) {
    }
}
