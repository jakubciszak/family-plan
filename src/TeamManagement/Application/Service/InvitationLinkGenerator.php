<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Service;

final readonly class InvitationLinkGenerator
{
    public function __construct(
        private string $frontendUrl
    ) {
    }

    public function forToken(string $token): string
    {
        return sprintf('%s/?invite=%s', rtrim($this->frontendUrl, '/'), $token);
    }
}
