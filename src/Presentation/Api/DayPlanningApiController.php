<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\DayPlanning\Application\Query\CalendarView;
use App\DayPlanning\Application\Query\PlanningView;
use App\DayPlanning\Application\Service\CalendarTags;
use App\DayPlanning\Application\Service\DayPlanningService;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/day-planning', name: 'api_day_planning_', format: 'json')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Day planning')]
final class DayPlanningApiController extends AbstractController
{
    public function __construct(private readonly DayPlanningService $planning, private readonly CalendarView $calendar, private readonly PlanningView $slots, private readonly CalendarTags $tags, private readonly UserRepositoryInterface $users)
    {
    }

    #[Route('/calendar', name: 'calendar', methods: ['GET'])]
    #[OA\Get(summary: 'Read authorized event occurrences and anonymous busy intervals')]
    #[OA\Response(response: 200, description: 'Privacy-filtered calendar and complete range coverage')]
    public function calendar(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->calendar->calendar($this->caller(), $request->query->all()));
    }

    #[Route('/planning/suggestions', name: 'suggestions', methods: ['POST'])]
    #[OA\Post(summary: 'Find common free time across all selected calendars')]
    #[OA\Response(response: 200, description: 'Up to twenty suggestions and busy intervals')]
    public function suggestions(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->slots->suggestions($this->caller(), $this->payload($request)));
    }

    #[Route('/availability/query', name: 'availability', methods: ['POST'])]
    #[OA\Post(summary: 'Read global busy intervals without event details')]
    #[OA\Response(response: 200, description: 'Anonymous merged busy intervals')]
    public function availability(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->calendar->availability($this->caller(), $this->payload($request)));
    }

    #[Route('/events', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create an event or recurrence series')]
    #[OA\Response(response: 201, description: 'Created event definition')]
    #[OA\Response(response: 409, description: 'Collision requires a confirmation token')]
    public function create(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->planning->create($this->caller(), $this->payload($request), $request->headers->get('Idempotency-Key')), 201);
    }

    #[Route('/events/{id}', name: 'definition', methods: ['GET'])]
    #[OA\Get(summary: 'Read my event definition for editing')]
    #[OA\Response(response: 200, description: 'Definition visible only to its author')]
    #[OA\Response(response: 404, description: 'No accessible event')]
    public function definition(string $id): JsonResponse
    {
        return $this->respond(fn () => $this->planning->definition($this->caller(), $this->id($id)));
    }

    #[Route('/events/{id}', name: 'update', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Update my event with optimistic concurrency')]
    #[OA\Response(response: 200, description: 'Updated event definition')]
    #[OA\Response(response: 412, description: 'The event was changed by another request')]
    public function update(string $id, Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->planning->update($this->caller(), $this->id($id), $this->version($request), $this->payload($request)));
    }

    #[Route('/events/{id}', name: 'cancel', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Cancel my event or entire series')]
    #[OA\Response(response: 204, description: 'Event cancelled')]
    public function cancel(string $id, Request $request): JsonResponse
    {
        return $this->respond(function () use ($id, $request): null {
            $this->planning->cancel($this->caller(), $this->id($id), $this->version($request));
            return null;
        }, 204);
    }

    #[Route('/events/{id}/occurrences/{occurrenceKey}', name: 'occurrence', methods: ['GET'])]
    #[OA\Get(summary: 'Read one occurrence after applying privacy and exception rules')]
    #[OA\Response(response: 200, description: 'Authorized event occurrence')]
    #[OA\Response(response: 404, description: 'No accessible occurrence')]
    public function occurrence(string $id, string $occurrenceKey): JsonResponse
    {
        return $this->respond(fn () => $this->calendar->occurrence($this->caller(), $this->id($id), $occurrenceKey));
    }

    #[Route('/events/{id}/exceptions/{occurrenceKey}', name: 'exception', methods: ['PUT'])]
    #[OA\Put(summary: 'Change or cancel one occurrence without changing its original key')]
    #[OA\Response(response: 200, description: 'Updated series definition')]
    public function exception(string $id, string $occurrenceKey, Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->planning->exception($this->caller(), $this->id($id), $this->version($request), $occurrenceKey, $this->payload($request)));
    }

    #[Route('/events/{id}/exceptions/{occurrenceKey}', name: 'restore', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Restore one occurrence from the series definition')]
    #[OA\Response(response: 200, description: 'Updated series definition')]
    public function restore(string $id, string $occurrenceKey, Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->planning->restore($this->caller(), $this->id($id), $this->version($request), $occurrenceKey, $request->getContent() === '' ? [] : $this->payload($request)));
    }

    #[Route('/events/{id}/participation/me', name: 'participation', methods: ['PUT'])]
    #[OA\Put(summary: 'Decline or rejoin one occurrence or the series')]
    #[OA\Response(response: 200, description: 'Updated participation, with the invitation retained')]
    public function participation(string $id, Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->planning->participate($this->caller(), $this->id($id), $this->payload($request)));
    }

    #[Route('/tags', name: 'tags', methods: ['GET'])]
    #[OA\Get(summary: 'Read my personal tags and selected team tags')]
    #[OA\Response(response: 200, description: 'Available tags')]
    public function tags(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->tags->list($this->caller(), $request->query->get('teamId')));
    }

    #[Route('/tags', name: 'tag_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a personal tag or administer a team tag')]
    #[OA\Response(response: 201, description: 'Created tag')]
    public function createTag(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->tags->create($this->caller(), $this->payload($request)), 201);
    }

    #[Route('/tags/{id}', name: 'tag_update', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Rename or recolor a tag within its current scope')]
    #[OA\Response(response: 200, description: 'Updated tag')]
    public function updateTag(string $id, Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->tags->update($this->caller(), $this->id($id), $this->payload($request)));
    }

    #[Route('/tags/{id}', name: 'tag_archive', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Archive a tag while retaining existing event labels')]
    #[OA\Response(response: 204, description: 'Archived tag')]
    public function archiveTag(string $id): JsonResponse
    {
        return $this->respond(function () use ($id): null {
            $this->tags->archive($this->caller(), $this->id($id));
            return null;
        }, 204);
    }

    private function respond(callable $operation, int $status = 200): JsonResponse
    {
        try {
            $data = $operation();
            $response = $this->json($data, $status);
            if (isset($data['version'])) {
                $response->setEtag((string) $data['version']);
            }
        } catch (PlanningException $error) {
            $response = $this->json(['code' => $error->errorCode, 'message' => $error->getMessage()] + $error->details, $error->status);
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        return $response;
    }

    private function payload(Request $request): array
    {
        $body = $request->getContent();
        if (strlen($body) > 131072 || !str_starts_with(ltrim($body), '{')) {
            throw PlanningException::invalid('Expected a JSON object.');
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw PlanningException::invalid('Invalid JSON body.');
        }
        if (!is_array($data)) {
            throw PlanningException::invalid('Expected a JSON object.');
        }
        return $data;
    }

    private function version(Request $request): int
    {
        $match = $request->headers->get('If-Match');
        if ($match === null) {
            throw new PlanningException('version_required', 428);
        }
        if (!preg_match('/^"?([1-9][0-9]{0,9})"?$/D', $match, $parts)) {
            throw PlanningException::invalid('If-Match must contain the current event version.');
        }
        return (int) $parts[1];
    }

    private function id(string $id): Uuid
    {
        try {
            return Uuid::fromString(strtolower($id));
        } catch (\InvalidArgumentException) {
            throw PlanningException::notFound();
        }
    }

    private function caller(): Uuid
    {
        return $this->users->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))?->id() ?? throw $this->createAccessDeniedException();
    }
}
