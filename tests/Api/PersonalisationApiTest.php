<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

class PersonalisationApiTest extends ApiTestCase
{
    private const DOT = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testEverybodyStartsWithSomethingToLookAt(): void
    {
        $mine = $this->getJson('/api/personalisation');

        $this->assertSame('#2e7d5b', $mine['theme']);
        $this->assertNull($mine['nickname']);
        $this->assertSame('bottts', $mine['avatar']['style']);
        $this->assertNotEmpty($mine['home']);
        $this->assertNotEmpty($mine['places']['navigation']);
    }

    public function testAColourANameAndAFaceAreRemembered(): void
    {
        $saved = $this->assertJsonResponse($this->putJson('/api/personalisation', [
            'nickname' => 'Zuzia Rakieta',
            'theme' => '#8E24AA',
            'avatar' => ['style' => 'pixel-art', 'seed' => 'rakieta'],
        ]));

        $this->assertSame('Zuzia Rakieta', $saved['nickname']);
        $this->assertSame('#8e24aa', $saved['theme']);
        $this->assertSame('pixel-art', $saved['avatar']['style']);

        $this->assertSame('Zuzia Rakieta', $this->getJson('/api/personalisation')['nickname']);
    }

    public function testANicknameIsDroppedByAskingForAnEmptyOne(): void
    {
        $this->putJson('/api/personalisation', ['nickname' => 'Rakieta']);

        $saved = $this->assertJsonResponse($this->putJson('/api/personalisation', ['nickname' => '']));

        $this->assertNull($saved['nickname']);
    }

    public function testChangingOneThingLeavesTheRestAlone(): void
    {
        $this->putJson('/api/personalisation', ['nickname' => 'Rakieta', 'theme' => '#8E24AA']);

        $saved = $this->assertJsonResponse($this->putJson('/api/personalisation', ['theme' => '#1565C0']));

        $this->assertSame('Rakieta', $saved['nickname']);
        $this->assertSame('#1565c0', $saved['theme']);
    }

    public function testAColourThatIsNotAColourIsRefused(): void
    {
        $response = $this->putJson('/api/personalisation', ['theme' => 'fioletowy']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testAnAvatarStyleWeDoNotDrawIsRefused(): void
    {
        $response = $this->putJson('/api/personalisation', [
            'avatar' => ['style' => 'malowany-recznie', 'seed' => 'x'],
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testTheHomePageIsArrangedAndTrimmed(): void
    {
        $saved = $this->assertJsonResponse($this->putJson('/api/personalisation', [
            'home' => ['standings', 'week'],
        ]));

        $this->assertSame(['standings', 'week'], $saved['home']);
    }

    public function testAPlaceThatDoesNotExistCannotBeArranged(): void
    {
        $response = $this->putJson('/api/personalisation', ['home' => ['week', 'pogoda']]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAPictureOfMyOwnIsKeptAndGivenBack(): void
    {
        $kept = $this->assertJsonResponse(
            $this->postJson('/api/personalisation/pictures', ['purpose' => 'avatar', 'data' => self::DOT]),
            Response::HTTP_CREATED
        );

        $this->assertSame('avatar', $kept['purpose']);
        $this->assertSame(1, $kept['width']);

        $this->client->request('GET', $kept['url']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'image/png');
    }

    public function testAFileThatIsNotAPictureIsRefused(): void
    {
        $response = $this->postJson('/api/personalisation/pictures', [
            'purpose' => 'avatar',
            'data' => base64_encode('to na pewno nie jest obrazek'),
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testMyOwnPictureCanBecomeMyFace(): void
    {
        $kept = $this->assertJsonResponse(
            $this->postJson('/api/personalisation/pictures', ['purpose' => 'avatar', 'data' => self::DOT]),
            Response::HTTP_CREATED
        );

        $saved = $this->assertJsonResponse($this->putJson('/api/personalisation', [
            'avatar' => ['pictureId' => $kept['id']],
        ]));

        $this->assertSame($kept['id'], $saved['avatar']['pictureId']);
    }

    public function testAPictureBelongingToSomebodyElseCannotBeWorn(): void
    {
        $kept = $this->assertJsonResponse(
            $this->postJson('/api/personalisation/pictures', ['purpose' => 'avatar', 'data' => self::DOT]),
            Response::HTTP_CREATED
        );

        $this->loginAs($this->authenticate());

        $response = $this->putJson('/api/personalisation', ['avatar' => ['pictureId' => $kept['id']]]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAStrangerDoesNotSeeMyPicture(): void
    {
        $kept = $this->assertJsonResponse(
            $this->postJson('/api/personalisation/pictures', ['purpose' => 'avatar', 'data' => self::DOT]),
            Response::HTTP_CREATED
        );

        $this->loginAs($this->authenticate());
        $this->client->request('GET', $kept['url']);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAnAvatarPictureIsNotAllowedAsABackdrop(): void
    {
        $kept = $this->assertJsonResponse(
            $this->postJson('/api/personalisation/pictures', ['purpose' => 'avatar', 'data' => self::DOT]),
            Response::HTTP_CREATED
        );

        $response = $this->putJson('/api/personalisation', ['backdrop' => ['pictureId' => $kept['id']]]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
