<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Application\Service\PushAnnouncements;
use App\Notifications\Application\Service\PushReach;
use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Repository\NativePushDeviceRepositoryInterface;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Notifications\Domain\ValueObject\DeliveryParameters;
use App\Presentation\Api\Dto\Push\RegisterNativePushDeviceRequest;
use App\Presentation\Api\Dto\Push\RegisterPushSubscriptionRequest;
use App\Presentation\Api\Dto\Push\SendPushAnnouncementRequest;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\TeamManagement\Application\Service\TeamMates;
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
        private readonly TeamMates $teamMates,
        private readonly ClockInterface $clock,
        private readonly string $vapidPublicKey,
        private readonly NativePushDeviceRepositoryInterface $devices,
        private readonly NativePushSenderInterface $nativeSender,
        private readonly PushReach $reach
    ) {
    }

    #[Route('/key', name: 'key', methods: ['GET'])]
    #[OA\Get(path: '/api/push/key', summary: 'Public key the browser needs to subscribe', tags: ['Push notifications'])]
    #[OA\Response(response: 200, description: 'VAPID public key, or null when push is not configured on this server; native tells whether phones with the app can be reached')]
    public function key(): JsonResponse
    {
        $key = trim($this->vapidPublicKey);

        return $this->json([
            'publicKey' => $key === '' ? null : $key,
            'available' => $key !== '',
            'native' => $this->nativeSender->isConfigured(),
        ]);
    }

    #[Route('/devices', name: 'register_device', methods: ['POST'])]
    #[OA\Post(path: '/api/push/devices', summary: 'Start receiving push on this phone, also while the app is closed', tags: ['Push notifications'])]
    #[OA\Response(response: 201, description: 'Phone registered')]
    #[OA\Response(response: 200, description: 'Phone was already registered and has been refreshed')]
    public function registerDevice(#[MapRequestPayload] RegisterNativePushDeviceRequest $request): JsonResponse
    {
        $userId = $this->callerId();
        $token = trim($request->token);
        $existing = $this->devices->findByToken($token);

        if ($existing !== null) {
            $existing->handOverTo($userId, $request->deviceLabel);
            $this->devices->save($existing);

            return $this->json($this->presentDevice($existing));
        }

        $device = NativePushDevice::register(
            Uuid::generate(),
            $userId,
            $request->platform,
            $token,
            $request->deviceLabel,
            $this->clock->now()
        );

        $this->devices->save($device);

        return $this->json($this->presentDevice($device), Response::HTTP_CREATED);
    }

    #[Route('/devices', name: 'unregister_device', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/push/devices', summary: 'Stop receiving push on this phone, e.g. when signing out', tags: ['Push notifications'])]
    #[OA\Parameter(name: 'token', in: 'query', required: true, description: 'Token the phone got from Firebase')]
    #[OA\Response(response: 204, description: 'Phone will not receive push any more')]
    #[OA\Response(response: 404, description: 'No such phone belongs to the caller')]
    public function unregisterDevice(Request $request): JsonResponse
    {
        $token = trim((string) $request->query->get('token', ''));
        $device = $token === '' ? null : $this->devices->findByToken($token);

        if ($device === null || !$device->belongsTo($this->callerId())) {
            return $this->json(['error' => 'Device not found'], Response::HTTP_NOT_FOUND);
        }

        $this->devices->delete($device);

        return $this->json(null, Response::HTTP_NO_CONTENT);
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

        if ($this->reach->devicesOf($userId) === 0) {
            return $this->json(
                ['error' => 'No device is registered for push notifications'],
                Response::HTTP_CONFLICT
            );
        }

        $this->notifications->sendPush(
            $userId->value(),
            'Powiadomienia push działają.',
            'Family Plan',
            [DeliveryParameters::TTL => 600, DeliveryParameters::URGENCY => 'high']
        );

        return $this->json(null, Response::HTTP_ACCEPTED);
    }

    #[Route('/audience', name: 'audience', methods: ['GET'])]
    #[OA\Get(path: '/api/push/audience', summary: 'Who the caller can reach with a push notification', tags: ['Push notifications'])]
    #[OA\Response(response: 200, description: 'People the caller may write to, with the number of devices each has')]
    #[OA\Response(response: 403, description: 'The caller administers no team')]
    public function audience(): JsonResponse
    {
        $caller = $this->caller();

        if (!$this->maySendAnnouncements($caller)) {
            return $this->json(['error' => 'Only a team admin can send notifications'], Response::HTTP_FORBIDDEN);
        }

        $users = array_map(
            fn (User $user) => [
                'id' => $user->id()->value(),
                'name' => $user->name(),
                'devices' => $this->reach->devicesOf($user->id()),
            ],
            $this->audienceOf($caller)
        );

        return $this->json([
            'users' => $users,
            'reachable' => count(array_filter($users, static fn (array $user) => $user['devices'] > 0)),
        ]);
    }

    #[Route('/announcements', name: 'announce', methods: ['POST'])]
    #[OA\Post(path: '/api/push/announcements', summary: 'Send a push notification the caller has written', tags: ['Push notifications'])]
    #[OA\Response(response: 202, description: 'Notification handed over to the push service, with the number of people it goes to')]
    #[OA\Response(response: 403, description: 'The caller administers no team')]
    #[OA\Response(response: 404, description: 'Nobody the caller can write to has that id')]
    #[OA\Response(response: 409, description: 'Nobody among the intended recipients has a device registered')]
    public function announce(#[MapRequestPayload] SendPushAnnouncementRequest $request): JsonResponse
    {
        $caller = $this->caller();

        if (!$this->maySendAnnouncements($caller)) {
            return $this->json(['error' => 'Only a team admin can send notifications'], Response::HTTP_FORBIDDEN);
        }

        $recipients = $this->recipientsOf($caller, $request->userId);

        if ($recipients === null) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $reached = $this->announcements->to($recipients, $request->message, $request->title);

        if ($reached === 0) {
            return $this->json(
                ['error' => 'No device is registered for push notifications'],
                Response::HTTP_CONFLICT
            );
        }

        return $this->json(['recipients' => $reached], Response::HTTP_ACCEPTED);
    }

    /**
     * @return Uuid[]|null
     */
    private function recipientsOf(User $caller, ?string $userId): ?array
    {
        if ($userId === null) {
            return array_map(static fn (User $user) => $user->id(), $this->audienceOf($caller));
        }

        $recipient = $this->userRepository->findById(Uuid::fromString($userId));

        if ($recipient === null || !$this->mayWriteTo($caller, $recipient)) {
            return null;
        }

        return [$recipient->id()];
    }

    /**
     * @return User[]
     */
    private function audienceOf(User $caller): array
    {
        if ($caller->isAdmin()) {
            return array_values(array_filter(
                $this->userRepository->findAll(),
                static fn (User $user) => !$user->id()->equals($caller->id())
            ));
        }

        return array_values(array_filter(array_map(
            fn (Uuid $userId) => $this->userRepository->findById($userId),
            $this->teamMates->administeredBy($caller->id())
        )));
    }

    private function mayWriteTo(User $caller, User $recipient): bool
    {
        if ($recipient->id()->equals($caller->id())) {
            return false;
        }

        return $caller->isAdmin() || $this->teamMates->isAdministeredBy($recipient->id(), $caller->id());
    }

    private function maySendAnnouncements(User $caller): bool
    {
        return $caller->isAdmin() || $this->teamMates->administersAnyTeam($caller->id());
    }

    private function caller(): User
    {
        return $this->userRepository->findByEmail(
            Email::fromString($this->getUser()->getUserIdentifier())
        );
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

    private function presentDevice(NativePushDevice $device): array
    {
        return [
            'id' => $device->id()->value(),
            'platform' => $device->platform(),
            'deviceLabel' => $device->deviceLabel(),
            'createdAt' => $device->createdAt()->format(\DATE_ATOM),
            'lastUsedAt' => $device->lastUsedAt()?->format(\DATE_ATOM),
        ];
    }

    private function callerId(): Uuid
    {
        return $this->userRepository
            ->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))
            ->id();
    }
}
