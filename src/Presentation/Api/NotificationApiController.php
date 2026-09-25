<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Notifications\Communication\Application\Service\NotificationPreferences;
use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Domain\Port\PushRetractionInterface;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', name: 'api_notifications_')]
#[OA\Tag(name: 'Notifications')]
#[IsGranted('ROLE_USER')]
class NotificationApiController extends AbstractController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly InAppNotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ClockInterface $clock,
        private readonly NotificationPreferences $preferences,
        private readonly PushRetractionInterface $retraction
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/notifications', summary: 'Notifications the application holds for the caller', tags: ['Notifications'])]
    #[OA\Parameter(name: 'unread', in: 'query', required: false, description: 'Only the ones the caller has not seen yet and that are still true: handled and expired ones are left out')]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'How many to return (1-100, 20 by default)')]
    #[OA\Response(response: 200, description: 'Notifications of the caller, newest first, with the unread count')]
    public function list(Request $request): JsonResponse
    {
        $userId = $this->callerId();
        $limit = $this->limitFrom($request);

        $notifications = $request->query->getBoolean('unread')
            ? $this->notifications->unreadFor($userId, $limit)
            : $this->notifications->recentFor($userId, $limit);

        return $this->json([
            'notifications' => array_map($this->present(...), $notifications),
            'unreadCount' => $this->notifications->countUnreadFor($userId),
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    #[OA\Post(path: '/api/notifications/{id}/read', summary: 'Mark one notification as read', tags: ['Notifications'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Notification UUID')]
    #[OA\Response(response: 200, description: 'The notification as it now stands')]
    #[OA\Response(response: 404, description: 'No such notification belongs to the caller')]
    public function read(string $id): JsonResponse
    {
        $userId = $this->callerId();
        $notification = Uuid::isValid($id) ? $this->notifications->findById(Uuid::fromString($id)) : null;

        if ($notification === null || !$notification->belongsTo($userId)) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $now = $this->clock->now();
        $wasNews = $notification->isActive($now);
        $notification->markAsRead($now);
        $this->notifications->save($notification);

        if ($wasNews) {
            $this->retraction->retract([$userId], [$notification->pushTag()]);
        }

        return $this->json([
            'notification' => $this->present($notification),
            'unreadCount' => $this->notifications->countUnreadFor($userId),
        ]);
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    #[OA\Post(path: '/api/notifications/read-all', summary: 'Mark every notification of the caller as read, or only the listed ones', tags: ['Notifications'])]
    #[OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'string'))]))]
    #[OA\Response(response: 200, description: 'How many notifications were marked')]
    public function readAll(Request $request): JsonResponse
    {
        $userId = $this->callerId();
        $now = $this->clock->now();
        $payload = json_decode($request->getContent() ?: '{}', true);
        $ids = is_array($payload) && is_array($payload['ids'] ?? null) ? $payload['ids'] : null;

        // Read here, so the tray of the phone may drop them too. Only news still has its entry there: an older
        // notification on the same topic was replaced by the newer one, which may still be unread.
        $tags = [];

        if ($ids === null) {
            $tags = array_map(static fn (InAppNotification $notification): string => $notification->pushTag(), $this->notifications->unreadFor($userId, self::MAX_LIMIT));
            $marked = $this->notifications->markAllAsRead($userId, $now);
        } else {
            $marked = 0;

            foreach (array_slice(array_unique(array_filter($ids, 'is_string')), 0, self::MAX_LIMIT) as $id) {
                $notification = Uuid::isValid($id) ? $this->notifications->findById(Uuid::fromString($id)) : null;

                if ($notification === null || !$notification->belongsTo($userId) || $notification->isRead()) {
                    continue;
                }

                if ($notification->isActive($now)) {
                    $tags[] = $notification->pushTag();
                }

                $notification->markAsRead($now);
                $this->notifications->save($notification);
                $marked++;
            }
        }

        if ($tags !== []) {
            $this->retraction->retract([$userId], $tags);
        }

        return $this->json([
            'status' => 'success',
            'marked' => $marked,
            'unreadCount' => $this->notifications->countUnreadFor($userId),
        ]);
    }

    #[Route('/preferences', name: 'preferences', methods: ['GET'])]
    #[OA\Get(path: '/api/notifications/preferences', summary: 'Kinds of notification the caller can switch on and off', tags: ['Notifications'])]
    #[OA\Response(response: 200, description: 'Every kind with its group, whether it is on, the channels it travels through and whether it can reach the caller at all')]
    public function preferences(): JsonResponse
    {
        return $this->json(['events' => $this->preferences->of($this->callerId())]);
    }

    #[Route('/preferences', name: 'change_preferences', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/api/notifications/preferences', summary: 'Switch kinds of notification on or off for the caller', tags: ['Notifications'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'events', type: 'object', example: ['task_assigned' => false])]))]
    #[OA\Response(response: 200, description: 'The preferences as they now stand')]
    #[OA\Response(response: 400, description: 'Unknown kind, one that cannot be switched off, or a value that is not true/false')]
    public function changePreferences(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || !is_array($payload['events'] ?? null)) {
            return $this->json(['error' => 'Field "events" must be an object of event => true/false'], Response::HTTP_BAD_REQUEST);
        }

        $userId = $this->callerId();

        try {
            $this->preferences->change($userId, $payload['events']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['events' => $this->preferences->of($userId)]);
    }

    private function present(InAppNotification $notification): array
    {
        return [
            'id' => $notification->id()->value(),
            'subject' => $notification->subject(),
            'message' => $notification->message(),
            'parameters' => $notification->parameters(),
            'event' => $notification->event(),
            'topic' => $notification->topic(),
            'createdAt' => $notification->createdAt()->format(DATE_ATOM),
            'readAt' => $notification->readAt()?->format(DATE_ATOM),
            'expiresAt' => $notification->expiresAt()?->format(DATE_ATOM),
            'resolvedAt' => $notification->resolvedAt()?->format(DATE_ATOM),
            // Still news: not read, not handled by anyone and not out of date.
            'active' => $notification->isActive($this->clock->now()),
        ];
    }

    private function limitFrom(Request $request): int
    {
        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);

        return max(1, min($limit, self::MAX_LIMIT));
    }

    private function callerId(): Uuid
    {
        return $this->userRepository
            ->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))
            ->id();
    }
}
