<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Presentation\Api\Dto\Personalisation\PersonalisationRequest;
use App\Presentation\Api\Dto\Personalisation\PictureRequest;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserSettings\Application\Service\Personalisations;
use App\UserSettings\Domain\Entity\OwnPicture;
use App\UserSettings\Domain\Entity\Personalisation;
use App\UserSettings\Domain\Repository\OwnPictureRepositoryInterface;
use App\UserSettings\Domain\ValueObject\AvatarChoice;
use App\UserSettings\Domain\ValueObject\Backdrop;
use App\UserSettings\Domain\ValueObject\Layout;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/personalisation', name: 'api_personalisation_')]
#[OA\Tag(name: 'Personalisation')]
#[IsGranted('ROLE_USER')]
class PersonalisationApiController extends AbstractController
{
    public function __construct(
        private readonly Personalisations $personalisations,
        private readonly OwnPictureRepositoryInterface $pictures,
        private readonly UserRepositoryInterface $users,
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly ClockInterface $clock
    ) {
    }

    #[Route('', name: 'mine', methods: ['GET'])]
    #[OA\Get(path: '/api/personalisation', summary: 'How I have made the app my own', tags: ['Personalisation'])]
    #[OA\Response(response: 200, description: 'My face, colour, backdrop and arrangement, with the places I may arrange')]
    public function mine(): JsonResponse
    {
        return $this->json($this->personalisations->describe($this->personalisations->of($this->caller())));
    }

    #[Route('/of/{userId}', name: 'of_member', methods: ['GET'])]
    #[OA\Get(path: '/api/personalisation/of/{userId}', summary: 'The face and name a teammate goes by', tags: ['Personalisation'])]
    #[OA\Response(response: 200, description: 'Nickname, colour and avatar of somebody on my team')]
    #[OA\Response(response: 403, description: 'That person shares no team with the caller')]
    public function ofMember(string $userId): JsonResponse
    {
        $member = Uuid::fromString($userId);
        $caller = $this->caller();

        if (!$caller->equals($member) && !$this->shareATeam($caller, $member)) {
            throw new UnauthorizedTeamActionException('Only people on the same team see each other');
        }

        return $this->json($this->personalisations->face($this->personalisations->of($member)));
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    #[OA\Put(path: '/api/personalisation', summary: 'Make the app my own', tags: ['Personalisation'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nickname', type: 'string', nullable: true),
                new OA\Property(property: 'theme', type: 'string', example: '#2E7D5B'),
                new OA\Property(property: 'avatar', type: 'object', example: ['style' => 'bottts', 'seed' => 'jez']),
                new OA\Property(property: 'backdrop', type: 'object', example: ['pattern' => 'dots']),
                new OA\Property(property: 'home', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'navigation', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'celebrates', type: 'boolean'),
                new OA\Property(property: 'makesSound', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Saved, given back as it now stands')]
    #[OA\Response(response: 400, description: 'A colour, a place or a picture that does not exist')]
    public function update(#[MapRequestPayload] PersonalisationRequest $request): JsonResponse
    {
        $caller = $this->caller();
        $mine = $this->personalisations->of($caller);

        if ($request->nickname !== null) {
            $mine->callThemselves($request->nickname === '' ? null : $request->nickname, $this->clock);
        }

        if ($request->theme !== null) {
            $mine->paintWith($request->theme, $this->clock);
        }

        if ($request->themeMode !== null) {
            $mine->lightOrDark($request->themeMode, $this->clock);
        }

        if ($request->language !== null) {
            $mine->speak($request->language, $this->clock);
        }

        if ($request->avatar !== null) {
            $mine->wear($this->avatarFrom($request->avatar, $caller), $this->clock);
        }

        if ($request->backdrop !== null) {
            $mine->standAgainst($this->backdropFrom($request->backdrop, $caller), $this->clock);
        }

        if ($request->home !== null) {
            $mine->arrangeHome(Layout::of(Personalisation::HOME_PLACES, $request->home), $this->clock);
        }

        if ($request->navigation !== null) {
            $mine->arrangeNavigation(Layout::of(Personalisation::NAV_PLACES, $request->navigation), $this->clock);
        }

        if ($request->celebrates !== null || $request->makesSound !== null) {
            $mine->celebrate(
                $request->celebrates ?? $mine->celebrates(),
                $request->makesSound ?? $mine->makesSound(),
                $this->clock
            );
        }

        $this->personalisations->save($mine);

        return $this->json($this->personalisations->describe($mine));
    }

    #[Route('/pictures', name: 'upload', methods: ['POST'])]
    #[OA\Post(path: '/api/personalisation/pictures', summary: 'Keep a picture of my own for an avatar or a backdrop', tags: ['Personalisation'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['purpose', 'data'],
            properties: [
                new OA\Property(property: 'purpose', type: 'string', enum: ['avatar', 'backdrop']),
                new OA\Property(property: 'data', type: 'string', description: 'The picture as a data URL or plain base64'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Picture kept')]
    #[OA\Response(response: 400, description: 'Not a picture we can show, or too heavy')]
    public function upload(#[MapRequestPayload] PictureRequest $request): JsonResponse
    {
        $picture = OwnPicture::keep(
            Uuid::generate(),
            $this->caller(),
            $request->purpose,
            $this->bytesOf($request->data),
            $this->clock
        );

        $this->pictures->save($picture);

        return $this->json($this->describePicture($picture), Response::HTTP_CREATED);
    }

    #[Route('/pictures', name: 'list_pictures', methods: ['GET'])]
    #[OA\Get(path: '/api/personalisation/pictures', summary: 'Pictures of my own I have kept', tags: ['Personalisation'])]
    #[OA\Response(response: 200, description: 'Pictures, newest first')]
    public function listPictures(): JsonResponse
    {
        return $this->json([
            'pictures' => array_map(
                fn (OwnPicture $picture) => $this->describePicture($picture),
                $this->pictures->ofUser($this->caller())
            ),
        ]);
    }

    #[Route('/pictures/{id}', name: 'show_picture', methods: ['GET'])]
    #[OA\Get(path: '/api/personalisation/pictures/{id}', summary: 'The picture itself', tags: ['Personalisation'])]
    #[OA\Response(response: 200, description: 'The image bytes')]
    #[OA\Response(response: 403, description: 'That picture belongs to somebody outside the caller\'s teams')]
    public function showPicture(string $id): Response
    {
        $picture = $this->pictures->find(Uuid::fromString($id));

        if ($picture === null) {
            throw $this->createNotFoundException('No such picture');
        }

        $caller = $this->caller();

        if (!$picture->belongsTo($caller) && !$this->shareATeam($caller, $picture->userId())) {
            throw new UnauthorizedTeamActionException('Only people on the same team see each other');
        }

        return new Response($picture->bytes(), Response::HTTP_OK, [
            'Content-Type' => $picture->mimeType(),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    #[Route('/pictures/{id}', name: 'forget_picture', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/personalisation/pictures/{id}', summary: 'Throw away a picture of mine', tags: ['Personalisation'])]
    #[OA\Response(response: 200, description: 'Picture thrown away')]
    public function forgetPicture(string $id): JsonResponse
    {
        $picture = $this->pictures->find(Uuid::fromString($id));

        if ($picture === null) {
            throw $this->createNotFoundException('No such picture');
        }

        if (!$picture->belongsTo($this->caller())) {
            throw new UnauthorizedTeamActionException('Only the owner throws away their own picture');
        }

        $this->pictures->remove($picture);

        return $this->json(['message' => 'Picture thrown away']);
    }

    private function avatarFrom(array $held, Uuid $caller): AvatarChoice
    {
        $pictureId = $held['pictureId'] ?? null;

        if (is_string($pictureId) && $pictureId !== '') {
            return AvatarChoice::uploaded($this->ownPicture($pictureId, $caller, 'avatar')->id());
        }

        return AvatarChoice::drawn($held['style'] ?? 'bottts', (string) ($held['seed'] ?? $caller->value()));
    }

    private function backdropFrom(array $held, Uuid $caller): Backdrop
    {
        $pictureId = $held['pictureId'] ?? null;

        if (is_string($pictureId) && $pictureId !== '') {
            return Backdrop::picture(
                $this->ownPicture($pictureId, $caller, 'backdrop')->id(),
                (int) ($held['dimming'] ?? 40)
            );
        }

        return Backdrop::drawn($held['pattern'] ?? 'plain');
    }

    private function ownPicture(string $id, Uuid $caller, string $purpose): OwnPicture
    {
        $picture = Uuid::isValid($id) ? $this->pictures->find(Uuid::fromString($id)) : null;

        if ($picture === null || !$picture->belongsTo($caller) || $picture->purpose() !== $purpose) {
            throw new \DomainException('That picture is not one of yours to use here');
        }

        return $picture;
    }

    private function bytesOf(string $data): string
    {
        $encoded = str_contains($data, ',') ? substr($data, strpos($data, ',') + 1) : $data;
        $bytes = base64_decode($encoded, true);

        if ($bytes === false || $bytes === '') {
            throw new \DomainException('That picture did not arrive in one piece');
        }

        return $bytes;
    }

    private function describePicture(OwnPicture $picture): array
    {
        return [
            'id' => $picture->id()->value(),
            'purpose' => $picture->purpose(),
            'width' => $picture->width(),
            'height' => $picture->height(),
            'bytes' => $picture->byteSize(),
            'url' => sprintf('/api/personalisation/pictures/%s', $picture->id()->value()),
        ];
    }

    private function shareATeam(Uuid $caller, Uuid $other): bool
    {
        foreach ($this->memberships->ofUser($other) as $membership) {
            if ($this->memberships->isMember($caller, $membership->teamId())) {
                return true;
            }
        }

        return false;
    }

    private function caller(): Uuid
    {
        return $this->users->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))->id();
    }
}
