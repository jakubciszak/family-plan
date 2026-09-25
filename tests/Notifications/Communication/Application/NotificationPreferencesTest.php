<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication\Application;

use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Application\Service\NotificationPreferences;
use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Infrastructure\Persistence\InMemoryNotificationPolicyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Service\TeamMates;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Infrastructure\Persistence\InMemoryUserSettingsRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class NotificationPreferencesTest extends TestCase
{
    private InMemoryUserSettingsRepository $settings;

    private InMemoryNotificationPolicyRepository $policies;

    private FakeMemberships $memberships;

    private NotificationPreferences $preferences;

    private Uuid $userId;

    protected function setUp(): void
    {
        $this->settings = new InMemoryUserSettingsRepository();
        $this->policies = new InMemoryNotificationPolicyRepository();
        $this->memberships = new FakeMemberships();
        $this->preferences = new NotificationPreferences(
            $this->settings,
            new NotificationPolicyProvider($this->policies),
            new TeamMates($this->memberships)
        );
        $this->userId = Uuid::generate();
    }

    public function testEveryKindAUserCanChooseIsOnAtFirst(): void
    {
        $events = $this->preferences->of($this->userId);

        $this->assertSame(
            array_map(static fn (NotificationEvent $event) => $event->value(), NotificationEvent::userChoices()),
            array_column($events, 'event')
        );
        $this->assertSame([true], array_values(array_unique(array_column($events, 'enabled'))));
        $this->assertNotContains('account_activation', array_column($events, 'event'));
    }

    public function testKindsAreGroupedTasksFirst(): void
    {
        $groups = array_values(array_unique(array_column($this->preferences->of($this->userId), 'group')));

        $this->assertSame(['tasks', 'calendar', 'allowance', 'streaks'], $groups);
    }

    public function testApprovalRequestsOnlyConcernTeamAdmins(): void
    {
        $this->assertFalse($this->find('task_completed')['relevant']);
        $this->assertTrue($this->find('task_assigned')['relevant']);

        $this->memberships->memberships[] = new TeamMembership(Uuid::generate(), Uuid::generate(), $this->userId, TeamRole::admin(), new DateTimeImmutable());

        $this->assertTrue($this->find('task_completed')['relevant']);
    }

    public function testItShowsTheChannelsTheApplicationUses(): void
    {
        $this->policies->save(NotificationPolicy::create(Uuid::generate(), NotificationEvent::taskApproved(), NotificationChannels::fromArray(['in_app'])));

        $this->assertSame(['in_app'], $this->find('task_approved')['channels']);
        $this->assertSame(['push'], $this->find('streak_at_risk')['channels']);
    }

    public function testASwitchedOffKindIsRemembered(): void
    {
        $this->preferences->change($this->userId, ['task_assigned' => false, 'calendar_changed' => false]);
        $this->preferences->change($this->userId, ['calendar_changed' => true]);

        $this->assertFalse($this->find('task_assigned')['enabled']);
        $this->assertTrue($this->find('calendar_changed')['enabled']);
        $this->assertNotNull($this->settings->findByUserId($this->userId)?->getPreferenceByType(PreferenceType::notificationEvents()));
        // The channel settings are untouched by it.
        $this->assertNotNull($this->settings->findByUserId($this->userId)?->getPreferenceByType(PreferenceType::notifications()));
    }

    public function testAnUnknownKindChangesNothing(): void
    {
        try {
            $this->preferences->change($this->userId, ['task_assigned' => false, 'birthday' => false]);
            $this->fail('An unknown kind should be refused');
        } catch (\InvalidArgumentException) {
        }

        $this->assertTrue($this->find('task_assigned')['enabled']);
    }

    private function find(string $event): array
    {
        foreach ($this->preferences->of($this->userId) as $entry) {
            if ($entry['event'] === $event) {
                return $entry;
            }
        }

        $this->fail("No preference for {$event}");
    }
}

final class FakeMemberships implements TeamMembershipRepositoryInterface
{
    /** @var list<TeamMembership> */
    public array $memberships = [];

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
        return array_values(array_filter($this->memberships, static fn (TeamMembership $m) => $m->teamId()->equals($teamId)));
    }

    public function ofUser(Uuid $userId): array
    {
        return array_values(array_filter($this->memberships, static fn (TeamMembership $m) => $m->userId()->equals($userId)));
    }

    public function isMember(Uuid $userId, Uuid $teamId): bool
    {
        return false;
    }

    public function isAdmin(Uuid $userId, Uuid $teamId): bool
    {
        return false;
    }
}
