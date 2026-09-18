<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_refresh_tokens_refresh_token', columns: ['refresh_token'])]
class RefreshToken extends BaseRefreshToken
{
}
