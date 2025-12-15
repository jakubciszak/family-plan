<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\UserManagement\Domain\Entity\User;

#[Route('/api/auth', name: 'api_auth_')]
class AuthApiController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This is handled by Symfony Security, but we can return success here
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->getId()->value(),
                'name' => $user->getName(),
                'email' => $user->getEmail()->value(),
                'role' => $user->getRole()->value,
            ],
        ]);
    }

    #[Route('/me', name: 'current_user', methods: ['GET'])]
    public function currentUser(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId()->value(),
            'name' => $user->getName(),
            'email' => $user->getEmail()->value(),
            'role' => $user->getRole()->value,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        // This is handled by Symfony Security
        return $this->json([
            'message' => 'Logout successful'
        ]);
    }
}
