<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Mother;

use App\UserManagement\Domain\ValueObject\Role;

final class RoleMother
{
    public static function user(): Role
    {
        return Role::USER;
    }

    public static function admin(): Role
    {
        return Role::ADMIN;
    }

    public static function random(): Role
    {
        return rand(0, 1) === 0 ? Role::USER : Role::ADMIN;
    }
}
