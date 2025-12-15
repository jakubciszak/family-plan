<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Query;

final readonly class FindUserByIdQuery
{
    public function __construct(
        public string $id
    ) {
    }
}
