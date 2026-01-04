<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Application\Command\RegisterUserCommand;
use App\UserManagement\Application\Handler\RegisterUserHandler;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Authentication')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private readonly RegisterUserHandler $registerUserHandler,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }
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

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register new user',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'securePassword123'),
                new OA\Property(property: 'phoneNumber', type: 'string', nullable: true, example: '+48123456789')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'User registered successfully. Check your email for activation link.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'User registered successfully. Check your email for activation link.'),
                new OA\Property(property: 'id', type: 'string', format: 'uuid')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error or user already exists',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User with this email already exists')
            ]
        )
    )]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                return $this->json([
                    'error' => 'Missing required fields: name, email, password'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (strlen($data['password']) < 8) {
                return $this->json([
                    'error' => 'Password must be at least 8 characters long'
                ], Response::HTTP_BAD_REQUEST);
            }

            $id = Uuid::generate()->value();
            $command = new RegisterUserCommand(
                $id,
                $data['name'],
                $data['email'],
                $data['password'],
                $data['phoneNumber'] ?? null
            );

            ($this->registerUserHandler)($command);

            return $this->json([
                'message' => 'User registered successfully. Check your email for activation link.',
                'id' => $id
            ], Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Registration failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/activate/{token}', name: 'activate', methods: ['GET', 'POST'])]
    #[OA\Get(
        path: '/api/auth/activate/{token}',
        summary: 'Activate user account',
        tags: ['Authentication']
    )]
    #[OA\Parameter(
        name: 'token',
        in: 'path',
        required: true,
        description: 'Activation token',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Account activated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Account activated successfully')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid or expired activation token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid activation token')
            ]
        )
    )]
    public function activate(string $token): JsonResponse
    {
        try {
            // Find user by activation token
            $users = $this->userRepository->findAll();
            $user = null;
            
            foreach ($users as $u) {
                if ($u->activationToken() === $token) {
                    $user = $u;
                    break;
                }
            }

            if ($user === null) {
                return $this->json([
                    'error' => 'Invalid activation token'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($user->isActive()) {
                return $this->json([
                    'message' => 'Account is already active'
                ], Response::HTTP_OK);
            }

            $user->activate($token);
            $this->userRepository->save($user);

            return $this->json([
                'message' => 'Account activated successfully'
            ], Response::HTTP_OK);
        } catch (\DomainException $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Activation failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
