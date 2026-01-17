<?php

declare(strict_types=1);

namespace App\Party\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Domain Event: Organization Created
 *
 * Emitted when a new Organization is created in the Party archetype.
 */
final readonly class OrganizationCreated
{
    public function __construct(
        public Uuid $organizationId,
        public string $name,
        public ?string $description
    ) {
    }
}
