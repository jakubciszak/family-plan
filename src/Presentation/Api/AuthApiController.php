<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\UserManagement\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Authentication')]
class AuthApiController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Login user',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@familyplan.local'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'admin123')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                new OA\Property(
                    property: 'user',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'role', type: 'string')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid credentials')
            ]
        )
    )]
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
    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Get current user',
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Current user information',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'role', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Not authenticated',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Not authenticated')
            ]
        )
    )]
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
    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout user',
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Logout successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Logout successful')
            ]
        )
    )]
    public function logout(): JsonResponse
    {
        // This is handled by Symfony Security
        return $this->json([
            'message' => 'Logout successful'
        ]);
    }
}
