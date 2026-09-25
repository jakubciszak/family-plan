<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserSettings\Application\Command\UpdateUserSettingsCommand;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user-settings', name: 'api_user_settings_')]
#[OA\Tag(name: 'User Settings')]
class UserSettingsApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UserSettingsRepositoryInterface $userSettingsRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    #[Route('/{userId}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/user-settings/{userId}',
        summary: 'Get user settings',
        tags: ['User Settings']
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        required: true,
        description: 'User UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'User settings',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'preferences',
                    type: 'object',
                    description: 'User preferences as key-value pairs'
                )
            ]
        )
    )]
    public function getUserSettings(string $userId): JsonResponse
    {
        if (!$this->mayManage($userId)) {
            return $this->json(['error' => 'You can only manage your own settings'], Response::HTTP_FORBIDDEN);
        }

        $settings = $this->userSettingsRepository->findByUserId(Uuid::fromString($userId));
        
        if ($settings === null) {
            return $this->json(
                ['preferences' => []],
                Response::HTTP_OK
            );
        }

        return $this->json([
            'preferences' => $settings->preferences()->toArray(),
        ]);
    }

    #[Route('/{userId}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/user-settings/{userId}',
        summary: 'Update user settings',
        tags: ['User Settings']
    )]
    #[OA\Patch(
        path: '/api/user-settings/{userId}',
        summary: 'Partially update user settings',
        tags: ['User Settings']
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        required: true,
        description: 'User UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['preference_type', 'options'],
            properties: [
                new OA\Property(
                    property: 'preference_type',
                    type: 'string',
                    description: 'Type of preference to update (e.g., notifications, theme, language, privacy)',
                    example: 'notifications'
                ),
                new OA\Property(
                    property: 'options',
                    type: 'object',
                    description: 'Preference options as key-value pairs',
                    example: ['email_enabled' => true, 'push_enabled' => false]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Settings updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Missing required fields',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Missing required fields: preference_type, options'
                )
            ]
        )
    )]
    public function updateUserSettings(string $userId, Request $request): JsonResponse
    {
        if (!$this->mayManage($userId)) {
            return $this->json(['error' => 'You can only manage your own settings'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['preference_type']) || !isset($data['options'])) {
            return $this->json(
                ['error' => 'Missing required fields: preference_type, options'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $command = new UpdateUserSettingsCommand(
            $userId,
            $data['preference_type'],
            $data['options']
        );

        $this->commandBus->dispatch($command);

        return $this->json(['status' => 'success'], Response::HTTP_OK);
    }

    /**
     * Settings belong to their owner; only an administrator of the application may manage someone else's.
     */
    private function mayManage(string $userId): bool
    {
        if (!Uuid::isValid($userId)) {
            return false;
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $caller = $this->getUser();
        $callerId = $caller === null ? null : $this->userRepository->findByEmail(Email::fromString($caller->getUserIdentifier()))?->id();

        return $callerId !== null && $callerId->equals(Uuid::fromString($userId));
    }
}
