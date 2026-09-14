<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\TestDatabase;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvitationLookupApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::prepareOnce();
        $this->client = static::createClient();
    }

    public function testAnonymousVisitorReadsTheAddressAnInvitationWasIssuedTo(): void
    {
        $invitation = $this->pendingInvitation('invited@example.com');

        $this->client->request('GET', '/api/teams/invitations/' . $invitation->token());

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['email' => 'invited@example.com'],
            json_decode($this->client->getResponse()->getContent(), true)
        );
    }

    public function testUnknownTokenIsNotFound(): void
    {
        $this->client->request('GET', '/api/teams/invitations/' . bin2hex(random_bytes(32)));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testExpiredInvitationIsNotFound(): void
    {
        $invitation = $this->pendingInvitation('stale@example.com', -1);

        $this->client->request('GET', '/api/teams/invitations/' . $invitation->token());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAcceptingAnInvitationStillRequiresLogin(): void
    {
        $invitation = $this->pendingInvitation('guarded@example.com');

        $this->client->request('POST', '/api/teams/invitations/' . $invitation->token() . '/accept');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function pendingInvitation(string $email, int $expiresInDays = 7): TeamInvitation
    {
        $invitation = TeamInvitation::create(
            Uuid::generate(),
            Uuid::generate(),
            Email::fromString($email),
            TeamRole::member(),
            Uuid::generate(),
            $expiresInDays
        );

        static::getContainer()->get(TeamInvitationRepositoryInterface::class)->save($invitation);

        return $invitation;
    }
}
