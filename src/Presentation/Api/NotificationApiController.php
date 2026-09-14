<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Notifications\Domain\Entity\InAppNotification;
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
        private readonly ClockInterface $clock
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/notifications', summary: 'Notifications the application holds for the caller', tags: ['Notifications'])]
    #[OA\Parameter(name: 'unread', in: 'query', required: false, description: 'Only the ones the caller has not seen yet')]
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

        $notification->markAsRead($this->clock->now());
        $this->notifications->save($notification);

        return $this->json([
            'notification' => $this->present($notification),
            'unreadCount' => $this->notifications->countUnreadFor($userId),
        ]);
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    #[OA\Post(path: '/api/notifications/read-all', summary: 'Mark every notification of the caller as read', tags: ['Notifications'])]
    #[OA\Response(response: 200, description: 'How many notifications were marked')]
    public function readAll(): JsonResponse
    {
        $marked = $this->notifications->markAllAsRead($this->callerId(), $this->clock->now());

        return $this->json([
            'status' => 'success',
            'marked' => $marked,
            'unreadCount' => 0,
        ]);
    }

    private function present(InAppNotification $notification): array
    {
        return [
            'id' => $notification->id()->value(),
            'subject' => $notification->subject(),
            'message' => $notification->message(),
            'parameters' => $notification->parameters(),
            'createdAt' => $notification->createdAt()->format(DATE_ATOM),
            'readAt' => $notification->readAt()?->format(DATE_ATOM),
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
