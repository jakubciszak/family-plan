<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\AcceptInvitationCommand;
use App\TeamManagement\Application\Command\CreateTeamCommand;
use App\TeamManagement\Application\Command\InviteToTeamCommand;
use App\TeamManagement\Application\Command\RemoveMemberCommand;
use App\TeamManagement\Application\Command\UpdateTeamCommand;
use App\TeamManagement\Application\Query\GetTeamInvitationsQuery;
use App\TeamManagement\Application\Query\GetTeamMembersQuery;
use App\TeamManagement\Application\Query\GetUserInvitationsQuery;
use App\TeamManagement\Application\Query\GetUserTeamsQuery;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/teams', name: 'api_team_')]
#[OA\Tag(name: 'Teams')]
class TeamApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/teams',
        summary: 'List user teams',
        tags: ['Teams']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of teams the current user is a member of',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'teams',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'description', type: 'string', nullable: true),
                            new OA\Property(property: 'createdBy', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ]
        )
    )]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $userId = $user->getUserIdentifier();
        
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));
        
        $teams = $this->queryBus->dispatch(new GetUserTeamsQuery($userEntity->id()->value()))
            ->last(HandledStamp::class)->getResult();

        return $this->json([
            'teams' => array_map(fn(Team $team) => $this->serializeTeam($team), $teams),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/teams',
        summary: 'Create a new team',
        tags: ['Teams']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 1, maxLength: 255, example: 'Family Team'),
                new OA\Property(property: 'description', type: 'string', example: 'Our family team for managing household tasks')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Team created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string', nullable: true)
            ]
        )
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $user = $this->getUser();
        $userId = $user->getUserIdentifier();
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));

        $teamId = Uuid::generate()->value();

        $this->commandBus->dispatch(new CreateTeamCommand(
            $teamId,
            $data['name'],
            $data['description'] ?? null,
            $userEntity->id()->value()
        ));

        return $this->json([
            'id' => $teamId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Put(
        path: '/api/teams/{id}',
        summary: 'Update team details',
        tags: ['Teams']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Team updated successfully')]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $user = $this->getUser();
        $userId = $user->getUserIdentifier();
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));

        $this->commandBus->dispatch(new UpdateTeamCommand(
            $id,
            $data['name'],
            $data['description'] ?? null,
            $userEntity->id()->value()
        ));

        return $this->json(['message' => 'Team updated successfully']);
    }

    #[Route('/{id}/members', name: 'members', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/teams/{id}/members',
        summary: 'Get team members',
        tags: ['Teams']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of team members',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'members',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'userId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'userName', type: 'string'),
                            new OA\Property(property: 'userEmail', type: 'string'),
                            new OA\Property(property: 'role', type: 'string', enum: ['admin', 'member']),
                            new OA\Property(property: 'joinedAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ]
        )
    )]
    public function members(string $id): JsonResponse
    {
        $members = $this->queryBus->dispatch(new GetTeamMembersQuery($id))
            ->last(HandledStamp::class)->getResult();

        return $this->json([
            'members' => array_map(fn(TeamMember $member) => $this->serializeMember($member), $members),
        ]);
    }

    #[Route('/{id}/invite', name: 'invite', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/teams/{id}/invite',
        summary: 'Invite a user to the team',
        tags: ['Teams']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'role'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'role', type: 'string', enum: ['admin', 'member'], example: 'member')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Invitation sent successfully')]
    public function invite(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $user = $this->getUser();
        $userId = $user->getUserIdentifier();
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));

        $invitationId = Uuid::generate()->value();

        $this->commandBus->dispatch(new InviteToTeamCommand(
            $invitationId,
            $id,
            $data['email'],
            $data['role'],
            $userEntity->id()->value()
        ));

        return $this->json([
            'message' => 'Invitation sent successfully',
            'invitationId' => $invitationId
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/members/{userId}', name: 'remove_member', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Delete(
        path: '/api/teams/{id}/members/{userId}',
        summary: 'Remove a member from the team',
        tags: ['Teams']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(response: 200, description: 'Member removed successfully')]
    public function removeMember(string $id, string $userId): JsonResponse
    {
        $user = $this->getUser();
        $currentUserId = $user->getUserIdentifier();
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($currentUserId));

        $this->commandBus->dispatch(new RemoveMemberCommand(
            $id,
            $userId,
            $userEntity->id()->value()
        ));

        return $this->json(['message' => 'Member removed successfully']);
    }

    #[Route('/invitations', name: 'my_invitations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/teams/invitations',
        summary: 'Get current user invitations',
        tags: ['Teams']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of pending invitations for the current user',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'invitations',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'teamName', type: 'string'),
                            new OA\Property(property: 'role', type: 'string'),
                            new OA\Property(property: 'token', type: 'string'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ]
        )
    )]
    public function myInvitations(): JsonResponse
    {
        $user = $this->getUser();
        $email = $user->getUserIdentifier();

        $invitations = $this->queryBus->dispatch(new GetUserInvitationsQuery($email))
            ->last(HandledStamp::class)->getResult();

        return $this->json([
            'invitations' => array_map(fn(TeamInvitation $inv) => $this->serializeInvitation($inv), $invitations),
        ]);
    }

    #[Route('/invitations/{token}/accept', name: 'accept_invitation', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/teams/invitations/{token}/accept',
        summary: 'Accept a team invitation',
        tags: ['Teams']
    )]
    #[OA\Parameter(
        name: 'token',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(response: 200, description: 'Invitation accepted successfully')]
    public function acceptInvitation(string $token): JsonResponse
    {
        $user = $this->getUser();
        $userId = $user->getUserIdentifier();
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));

        $this->commandBus->dispatch(new AcceptInvitationCommand(
            $token,
            $userEntity->id()->value()
        ));

        return $this->json(['message' => 'Invitation accepted successfully']);
    }

    private function serializeTeam(Team $team): array
    {
        return [
            'id' => $team->id()->value(),
            'name' => $team->name()->value(),
            'description' => $team->description(),
            'createdBy' => $team->createdBy()->value(),
            'createdAt' => $team->createdAt()->format('c'),
            'updatedAt' => $team->updatedAt()?->format('c')
        ];
    }

    private function serializeMember(TeamMember $member): array
    {
        $user = $this->userRepository->findById($member->userId());
        
        return [
            'id' => $member->id()->value(),
            'userId' => $member->userId()->value(),
            'userName' => $user?->name() ?? 'Unknown',
            'userEmail' => $user?->email()->value() ?? 'Unknown',
            'role' => $member->role()->value,
            'joinedAt' => $member->joinedAt()->format('c')
        ];
    }

    private function serializeInvitation(TeamInvitation $invitation): array
    {
        return [
            'id' => $invitation->id()->value(),
            'teamId' => $invitation->teamId()->value(),
            'role' => $invitation->role()->value,
            'token' => $invitation->token(),
            'status' => $invitation->status()->value,
            'createdAt' => $invitation->createdAt()->format('c'),
            'expiresAt' => $invitation->expiresAt()?->format('c')
        ];
    }
}
