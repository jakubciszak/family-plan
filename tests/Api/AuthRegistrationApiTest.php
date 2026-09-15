<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;

class AuthRegistrationApiTest extends ApiTestCase
{
    public function testRegisterAcceptsJsonPayload(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Api Test User',
            'email' => sprintf('api-register-%s@example.com', uniqid()),
            'password' => 'securePassword123',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('activationRequired', $payload);
    }

    public function testPlainRegistrationGetsAFamilyOfItsOwn(): void
    {
        $email = sprintf('api-own-team-%s@example.com', uniqid());

        $this->postJson('/api/auth/register', [
            'name' => 'Sam Solo',
            'email' => $email,
            'password' => 'securePassword123',
        ]);

        $this->assertCount(1, $this->teamsOf($email));
    }

    public function testRegisteringOnAnInvitationJoinsThatFamilyInsteadOfStartingANewOne(): void
    {
        $team = $this->createTeamAndAdmin();
        $email = sprintf('api-invited-%s@example.com', uniqid());

        $invitation = $this->assertJsonResponse(
            $this->postJson(sprintf('/api/teams/%s/invite', $team['teamId']), [
                'email' => $email,
                'role' => 'member',
            ]),
            Response::HTTP_CREATED
        );

        $this->postJson('/api/auth/register', [
            'name' => 'Nowe Dziecko',
            'email' => $email,
            'password' => 'securePassword123',
            'inviteToken' => $invitation['invitation']['token'],
        ]);

        $this->assertSame([], $this->teamsOf($email));
    }

    public function testAMadeUpInviteTokenStillGetsAFamilyOfItsOwn(): void
    {
        $email = sprintf('api-bad-token-%s@example.com', uniqid());

        $this->postJson('/api/auth/register', [
            'name' => 'Sam Solo',
            'email' => $email,
            'password' => 'securePassword123',
            'inviteToken' => 'nie-ma-takiego-zaproszenia',
        ]);

        $this->assertCount(1, $this->teamsOf($email));
    }

    public function testRegisterRejectsInvalidPayload(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Api Test User',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $email = sprintf('api-duplicate-%s@example.com', uniqid());
        $payload = [
            'name' => 'Api Test User',
            'email' => $email,
            'password' => 'securePassword123',
        ];

        $this->assertSame(Response::HTTP_CREATED, $this->postJson('/api/auth/register', $payload)->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->postJson('/api/auth/register', $payload)->getStatusCode());
    }

    private function teamsOf(string $email): array
    {
        $user = static::getContainer()
            ->get(UserRepositoryInterface::class)
            ->findByEmail(Email::fromString($email));

        return static::getContainer()
            ->get(TeamMembershipRepositoryInterface::class)
            ->ofUser($user->id());
    }
}
