<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\UserSettings\Application\Command\UpdateUserSettingsCommand;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user-settings', name: 'api_user_settings_')]
class UserSettingsApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UserSettingsRepositoryInterface $userSettingsRepository
    ) {
    }

    #[Route('/{userId}', name: 'get', methods: ['GET'])]
    public function getUserSettings(string $userId): JsonResponse
    {
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
    public function updateUserSettings(string $userId, Request $request): JsonResponse
    {
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
}
