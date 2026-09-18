<?php

declare(strict_types=1);

namespace App\Presentation\Api\EventListener;

use App\UserManagement\Domain\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::JWT_CREATED, method: 'enrichPayload')]
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, method: 'enrichResponse')]
final readonly class JwtResponseListener
{
    public function enrichPayload(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $event->setData([...$event->getData(), ...self::describe($user)]);
    }

    public function enrichResponse(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $event->setData([...$event->getData(), 'user' => self::describe($user)]);
    }

    /**
     * @return array{id: string, name: string, email: string, role: string}
     */
    private static function describe(User $user): array
    {
        return [
            'id' => $user->id()->value(),
            'name' => $user->name(),
            'email' => $user->email()->value(),
            'role' => $user->role()->value,
        ];
    }
}
