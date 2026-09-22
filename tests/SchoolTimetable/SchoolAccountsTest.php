<?php

declare(strict_types=1);

namespace App\Tests\SchoolTimetable;

use App\SchoolTimetable\Application\Service\SchoolAccounts;
use App\SchoolTimetable\Application\Service\TimetableAccess;
use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\SchoolTimetable\Domain\Service\TimetableProviderInterface;
use App\SchoolTimetable\Domain\ValueObject\SchoolCredentials;
use App\SchoolTimetable\Domain\ValueObject\TimetableWeek;
use App\SchoolTimetable\Infrastructure\Security\SodiumSecretCipher;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use PHPUnit\Framework\TestCase;

final class SchoolAccountsTest extends TestCase
{
    private Uuid $parent;
    private Uuid $team;
    private Uuid $child;
    private SchoolAccount $account;

    public function testAStudentIdThatJsonDecodedIntoAnIntegerStillLinks(): void
    {
        $result = $this->accounts()->linkStudents($this->parent, $this->team->value(), [7 => $this->child->value()]);

        self::assertSame($this->child->value(), $result['account']['students'][0]['userId']);
        self::assertSame('7', $result['account']['students'][0]['studentId']);
    }

    public function testATextualStudentIdKeepsWorking(): void
    {
        $result = $this->accounts()->linkStudents($this->parent, $this->team->value(), ['7' => $this->child->value()]);

        self::assertSame($this->child->value(), $result['account']['students'][0]['userId']);
    }

    public function testUnlinkingClearsTheMember(): void
    {
        $accounts = $this->accounts();
        $accounts->linkStudents($this->parent, $this->team->value(), [7 => $this->child->value()]);
        $result = $accounts->linkStudents($this->parent, $this->team->value(), [7 => null]);

        self::assertNull($result['account']['students'][0]['userId']);
    }

    public function testAStrangerToTheTeamIsRejected(): void
    {
        $this->expectException(TimetableException::class);
        $this->accounts()->linkStudents($this->parent, $this->team->value(), [7 => Uuid::generate()->value()]);
    }

    public function testGarbageInsteadOfAMemberIsRejected(): void
    {
        $this->expectException(TimetableException::class);
        $this->accounts()->linkStudents($this->parent, $this->team->value(), [7 => 'nie-uuid']);
    }

    public function testTheStoredAccountNeverCarriesThePassword(): void
    {
        $described = $this->accounts()->read($this->parent, $this->team->value())['account'];

        self::assertArrayNotHasKey('secret', $described);
        self::assertArrayNotHasKey('password', $described);
    }

    protected function setUp(): void
    {
        $this->parent = Uuid::generate();
        $this->team = Uuid::generate();
        $this->child = Uuid::generate();

        $this->account = SchoolAccount::create(Uuid::generate(), $this->team, 'szkola', 'rodzic@example.com', (new SodiumSecretCipher('test-secret'))->encrypt('hasło'));
        $this->account->rememberStudents([['id' => '7', 'name' => 'Nowak Ola']]);
    }

    private function accounts(): SchoolAccounts
    {
        return new SchoolAccounts($this->repository(), new TimetableAccess($this->memberships()), new SodiumSecretCipher('test-secret'), $this->provider());
    }

    private function repository(): SchoolAccountRepositoryInterface
    {
        return new class($this->account) implements SchoolAccountRepositoryInterface {
            public function __construct(private ?SchoolAccount $account)
            {
            }

            public function ofTeam(Uuid $teamId): ?SchoolAccount
            {
                return $this->account;
            }

            public function all(): array
            {
                return $this->account === null ? [] : [$this->account];
            }

            public function save(SchoolAccount $account): void
            {
                $this->account = $account;
            }

            public function remove(SchoolAccount $account): void
            {
                $this->account = null;
            }
        };
    }

    private function provider(): TimetableProviderInterface
    {
        return new class implements TimetableProviderInterface {
            public function week(SchoolCredentials $credentials, string $weekStart): TimetableWeek
            {
                return new TimetableWeek([], []);
            }
        };
    }

    private function memberships(): TeamMembershipRepositoryInterface
    {
        return new class($this->parent, $this->team, $this->child) implements TeamMembershipRepositoryInterface {
            public function __construct(private readonly Uuid $admin, private readonly Uuid $team, private readonly Uuid $child)
            {
            }

            public function join(Uuid $teamId, Uuid $userId, TeamRole $role): ?TeamMembership
            {
                return null;
            }

            public function leave(Uuid $teamId, Uuid $userId): void
            {
            }

            public function find(Uuid $teamId, Uuid $userId): ?TeamMembership
            {
                return null;
            }

            public function ofTeam(Uuid $teamId): array
            {
                return [];
            }

            public function ofUser(Uuid $userId): array
            {
                return [];
            }

            public function isMember(Uuid $userId, Uuid $teamId): bool
            {
                return $teamId->equals($this->team) && ($userId->equals($this->admin) || $userId->equals($this->child));
            }

            public function isAdmin(Uuid $userId, Uuid $teamId): bool
            {
                return $teamId->equals($this->team) && $userId->equals($this->admin);
            }
        };
    }
}
