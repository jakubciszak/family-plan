<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\DayPlanning\Domain\Exception\PlanningException;
use App\SchoolTimetable\Application\Service\SchoolAccounts;
use App\SchoolTimetable\Application\Service\TimetableImport;
use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/school-timetable', name: 'api_school_timetable_', format: 'json')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'School timetable')]
final class SchoolTimetableApiController extends AbstractController
{
    public function __construct(private readonly SchoolAccounts $accounts, private readonly TimetableImport $import, private readonly UserRepositoryInterface $users)
    {
    }

    #[Route('/config', name: 'config', methods: ['GET'])]
    #[OA\Get(summary: 'Read the mobidziennik sign in of a team, without its password')]
    #[OA\Response(response: 200, description: 'Stored school, login and student links')]
    #[OA\Response(response: 403, description: 'Only a team admin reads the sign in')]
    public function config(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->accounts->read($this->caller(), $request->query->get('teamId')));
    }

    #[Route('/config', name: 'save_config', methods: ['PUT'])]
    #[OA\Put(summary: 'Store the mobidziennik sign in of a team')]
    #[OA\Response(response: 200, description: 'Stored sign in; the password is encrypted at rest')]
    public function saveConfig(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->accounts->save($this->caller(), $this->payload($request)));
    }

    #[Route('/config', name: 'forget_config', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Forget the mobidziennik sign in of a team')]
    #[OA\Response(response: 204, description: 'Sign in and student links removed')]
    public function forgetConfig(Request $request): JsonResponse
    {
        return $this->respond(function (): ?array {
            $this->accounts->forget($this->caller(), $request->query->get('teamId'));

            return null;
        }, 204);
    }

    #[Route('/students/refresh', name: 'refresh_students', methods: ['POST'])]
    #[OA\Post(summary: 'Sign in to mobidziennik and read the students of that account')]
    #[OA\Response(response: 200, description: 'Students found on the account, with their current links')]
    #[OA\Response(response: 422, description: 'Mobidziennik rejected the sign in')]
    public function refreshStudents(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        return $this->respond(fn () => $this->accounts->refreshStudents($this->caller(), $payload['teamId'] ?? null, $payload['weekStart'] ?? null));
    }

    #[Route('/students/links', name: 'link_students', methods: ['POST'])]
    #[OA\Post(summary: 'Point each mobidziennik student at a family member')]
    #[OA\Response(response: 200, description: 'Stored links')]
    public function linkStudents(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        return $this->respond(fn () => $this->accounts->linkStudents($this->caller(), $payload['teamId'] ?? null, $payload['links'] ?? null));
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    #[OA\Post(summary: 'Import one week of lessons into the calendars of the linked family members')]
    #[OA\Response(response: 200, description: 'Counts of imported, replaced and skipped lessons')]
    #[OA\Response(response: 404, description: 'No sign in stored for this team')]
    public function importWeek(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        return $this->respond(fn () => $this->import->run($this->caller(), $payload['teamId'] ?? null, $payload['weekStart'] ?? null));
    }

    private function respond(callable $work, int $status = 200): JsonResponse
    {
        try {
            $result = $work();
        } catch (TimetableException $failure) {
            return new JsonResponse(['error' => $failure->errorCode] + ($failure->details === [] ? [] : ['details' => $failure->details]), $failure->status);
        } catch (PlanningException $failure) {
            return new JsonResponse(['error' => $failure->errorCode] + ($failure->details === [] ? [] : ['details' => $failure->details]), $failure->status);
        }

        return $result === null ? new JsonResponse(null, $status) : new JsonResponse($result, $status);
    }

    private function payload(Request $request): array
    {
        $body = $request->getContent();
        if ($body === '') {
            return [];
        }
        if (strlen($body) > 131072 || !str_starts_with(ltrim($body), '{')) {
            throw TimetableException::invalid('Expected a JSON object.');
        }

        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw TimetableException::invalid('Invalid JSON body.');
        }

        if (!is_array($data)) {
            throw TimetableException::invalid('Expected a JSON object.');
        }

        return $data;
    }

    private function caller(): Uuid
    {
        return $this->users->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))?->id() ?? throw $this->createAccessDeniedException();
    }
}
