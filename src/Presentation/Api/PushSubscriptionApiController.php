<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Application\Service\PushAnnouncements;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Presentation\Api\Dto\Push\RegisterPushSubscriptionRequest;
use App\Presentation\Api\Dto\Push\SendPushAnnouncementRequest;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/push', name: 'api_push_')]
#[OA\Tag(name: 'Push notifications')]
#[IsGranted('ROLE_USER')]
class PushSubscriptionApiController extends AbstractController
{
    public function __construct(
        private readonly PushSubscriptionRepositoryInterface $subscriptions,
        private readonly UserRepositoryInterface $userRepository,
        private readonly NotificationFacade $notifications,
        private readonly PushAnnouncements $announcements,
        private readonly ClockInterface $clock,
        private readonly string $vapidPublicKey
    ) {
    }

    #[Route('/key', name: 'key', methods: ['GET'])]
    #[OA\Get(path: '/api/push/key', summary: 'Public key the browser needs to subscribe', tags: ['Push notifications'])]
    #[OA\Response(response: 200, description: 'VAPID public key, or null when push is not configured on this server')]
    public function key(): JsonResponse
    {
        $key = trim($this->vapidPublicKey);

        return $this->json([
            'publicKey' => $key === '' ? null : $key,
            'available' => $key !== '',
        ]);
    }

    #[Route('/subscriptions', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/push/subscriptions', summary: 'Devices the caller receives push on', tags: ['Push notifications'])]
    #[OA\Response(response: 200, description: 'Subscriptions of the caller')]
    public function list(): JsonResponse
    {
        return $this->json([
            'subscriptions' => array_map(
                $this->present(...),
                $this->subscriptions->findForUser($this->callerId())
            ),
        ]);
    }

    #[Route('/subscriptions', name: 'register', methods: ['POST'])]
    #[OA\Post(path: '/api/push/subscriptions', summary: 'Start receiving push on this device', tags: ['Push notifications'])]
    #[OA\Response(response: 201, description: 'Device registered')]
    #[OA\Response(response: 200, description: 'Device was already registered and has been refreshed')]
    public function register(#[MapRequestPayload] RegisterPushSubscriptionRequest $request): JsonResponse
    {
        $userId = $this->callerId();
        $existing = $this->subscriptions->findByEndpoint($request->endpoint);

        if ($existing !== null) {
            $existing->handOverTo($userId, $request->publicKey, $request->authToken, $request->deviceLabel);
            $this->subscriptions->save($existing);

            return $this->json($this->present($existing));
        }

        $subscription = PushSubscription::register(
            Uuid::generate(),
            $userId,
            $request->endpoint,
            $request->publicKey,
            $request->authToken,
            $request->deviceLabel,
            $this->clock->now()
        );

        $this->subscriptions->save($subscription);

        return $this->json($this->present($subscription), Response::HTTP_CREATED);
    }

    #[Route('/subscriptions', name: 'unregister', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/push/subscriptions', summary: 'Stop receiving push on this device', tags: ['Push notifications'])]
    #[OA\Parameter(name: 'endpoint', in: 'query', required: true, description: 'Endpoint the browser gave when subscribing')]
    #[OA\Response(response: 204, description: 'Device will not receive push any more')]
    #[OA\Response(response: 404, description: 'No such device belongs to the caller')]
    public function unregister(Request $request): JsonResponse
    {
        $endpoint = (string) $request->query->get('endpoint', '');
        $subscription = $endpoint === '' ? null : $this->subscriptions->findByEndpoint($endpoint);

        if ($subscription === null || !$subscription->belongsTo($this->callerId())) {
            return $this->json(['error' => 'Push subscription not found'], Response::HTTP_NOT_FOUND);
        }

        $this->subscriptions->delete($subscription);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/test', name: 'test', methods: ['POST'])]
    #[OA\Post(path: '/api/push/test', summary: 'Send the caller a push to check the device works', tags: ['Push notifications'])]
    #[OA\Response(response: 202, description: 'Notification handed over to the push service')]
    #[OA\Response(response: 409, description: 'The caller has no device registered')]
    public function test(): JsonResponse
    {
        $userId = $this->callerId();

        if ($this->subscriptions->countForUser($userId) === 0) {
            return $this->json(
                ['error' => 'No device is registered for push notifications'],
                Response::HTTP_CONFLICT
            );
        }

        $this->notifications->sendPush(
            $userId->value(),
            'Powiadomienia push działają.',
            'Family Plan'
        );

        return $this->json(null, Response::HTTP_ACCEPTED);
    }

    #[Route('/audience', name: 'audience', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(path: '/api/push/audience', summary: 'Who an admin can reach with a push notification', tags: ['Push notifications'])]
    #[OA\Response(response: 200, description: 'Every user with the number of devices registered for push')]
    public function audience(): JsonResponse
    {
        $users = array_map(
            fn (User $user) => [
                'id' => $user->id()->value(),
                'name' => $user->name(),
                'devices' => $this->subscriptions->countForUser($user->id()),
            ],
            $this->userRepository->findAll()
        );

        return $this->json([
            'users' => $users,
            'reachable' => count(array_filter($users, static fn (array $user) => $user['devices'] > 0)),
        ]);
    }

    #[Route('/announcements', name: 'announce', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(path: '/api/push/announcements', summary: 'Send a push notification an admin has written', tags: ['Push notifications'])]
    #[OA\Response(response: 202, description: 'Notification handed over to the push service, with the number of people it goes to')]
    #[OA\Response(response: 404, description: 'No such user')]
    #[OA\Response(response: 409, description: 'Nobody among the intended recipients has a device registered')]
    public function announce(#[MapRequestPayload] SendPushAnnouncementRequest $request): JsonResponse
    {
        if ($request->userId === null) {
            $recipients = $this->announcements->toEveryone($request->message, $request->title);

            return $recipients === 0
                ? $this->json(
                    ['error' => 'No device is registered for push notifications'],
                    Response::HTTP_CONFLICT
                )
                : $this->json(['recipients' => $recipients], Response::HTTP_ACCEPTED);
        }

        $recipient = $this->userRepository->findById(Uuid::fromString($request->userId));

        if ($recipient === null) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->announcements->toOne($recipient->id(), $request->message, $request->title)) {
            return $this->json(
                ['error' => 'No device is registered for push notifications'],
                Response::HTTP_CONFLICT
            );
        }

        return $this->json(['recipients' => 1], Response::HTTP_ACCEPTED);
    }

    private function present(PushSubscription $subscription): array
    {
        return [
            'id' => $subscription->id()->value(),
            'endpoint' => $subscription->endpoint(),
            'deviceLabel' => $subscription->deviceLabel(),
            'createdAt' => $subscription->createdAt()->format(\DATE_ATOM),
            'lastUsedAt' => $subscription->lastUsedAt()?->format(\DATE_ATOM),
        ];
    }

    private function callerId(): Uuid
    {
        return $this->userRepository
            ->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))
            ->id();
    }
}
