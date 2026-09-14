<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Notifications\Communication\Application\Command\UpdateNotificationPolicyCommand;
use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notification-policies', name: 'api_notification_policy_')]
#[OA\Tag(name: 'Notification Policies')]
#[IsGranted('ROLE_ADMIN')]
class NotificationPolicyApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly NotificationPolicyProvider $policyProvider
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/notification-policies',
        summary: 'Read the event x channel matrix (Admin only)',
        tags: ['Notification Policies']
    )]
    #[OA\Response(
        response: 200,
        description: 'Supported channels and the channels enabled for every event',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'channels',
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: ['email', 'sms', 'in_app'])
                ),
                new OA\Property(
                    property: 'events',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'event', type: 'string'),
                            new OA\Property(property: 'channels', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'defaultChannels', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'configurable', type: 'boolean'),
                        ]
                    )
                ),
            ]
        )
    )]
    public function list(): JsonResponse
    {
        $matrix = $this->policyProvider->matrix();

        return $this->json([
            'channels' => NotificationChannels::supported(),
            'events' => array_map(
                fn(NotificationEvent $event) => [
                    'event' => $event->value(),
                    'channels' => $matrix[$event->value()]->toArray(),
                    'defaultChannels' => $event->defaultChannels()->toArray(),
                    'configurable' => $event->isConfigurable(),
                ],
                NotificationEvent::all()
            ),
        ]);
    }

    #[Route('/{event}', name: 'update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/notification-policies/{event}',
        summary: 'Set the channels an event is communicated through (Admin only)',
        tags: ['Notification Policies']
    )]
    #[OA\Parameter(
        name: 'event',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', enum: ['task_completed', 'task_approved', 'user_welcome'])
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['channels'],
            properties: [
                new OA\Property(
                    property: 'channels',
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: ['email', 'sms', 'in_app'])
                ),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Policy updated')]
    #[OA\Response(response: 400, description: 'Unknown event, unknown channel or a transactional event')]
    public function update(string $event, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || !is_array($payload['channels'] ?? null)) {
            return $this->json(['error' => 'Field "channels" must be an array'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch(new UpdateNotificationPolicyCommand($event, array_values($payload['channels'])));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof \InvalidArgumentException) {
                return $this->json(['error' => $previous->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            throw $e;
        }

        return $this->json(['message' => 'Notification policy updated']);
    }
}
