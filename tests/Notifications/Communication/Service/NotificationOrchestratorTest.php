<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication\Service;

use App\Notifications\Application\Port\ActorProviderInterface;
use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\Service\ChannelResolver;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Infrastructure\Persistence\InMemoryNotificationPolicyRepository;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use App\UserSettings\Infrastructure\Persistence\InMemoryUserSettingsRepository;
use PHPUnit\Framework\TestCase;

class NotificationOrchestratorTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;

    private InMemoryUserRepository $userRepository;

    private InMemoryUserSettingsRepository $settingsRepository;

    private InMemoryNotificationPolicyRepository $policyRepository;

    private NotificationOrchestrator $orchestrator;

    private User $user;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
        $this->userRepository = new InMemoryUserRepository();
        $this->settingsRepository = new InMemoryUserSettingsRepository();
        $this->policyRepository = new InMemoryNotificationPolicyRepository();

        $this->orchestrator = new NotificationOrchestrator(
            new NotificationFacade([$this->adapter]),
            $this->userRepository,
            $this->settingsRepository,
            new NotificationPolicyProvider($this->policyRepository),
            new ChannelResolver()
        );

        $this->user = User::create(
            Uuid::generate(),
            'Zosia',
            Email::fromString('zosia@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );
        $this->userRepository->save($this->user);
    }

    public function testTaskDefaultsReachEmailInAppAndPush(): void
    {
        $this->notifyUser(NotificationEvent::taskApproved());

        $this->assertSame(['email', 'in_app', 'push'], $this->sentChannels());
        $this->assertSame('zosia@example.com', $this->adapter->getSentNotifications()[0]['recipient']);
        $this->assertSame(['email', 'in_app', 'push'], $this->adapter->getSentNotifications()[0]['parameters']['delivery_channels']);
    }

    public function testAdminMovesAnEventToTheInAppChannel(): void
    {
        $this->givenPolicy(NotificationEvent::taskApproved(), ['in_app']);

        $this->notifyUser(NotificationEvent::taskApproved());

        $sent = $this->adapter->getSentNotifications();
        $this->assertSame(['in_app'], $this->sentChannels());
        $this->assertSame($this->user->id()->value(), $sent[0]['recipient']);
        $this->assertSame(['in_app'], $sent[0]['parameters']['delivery_channels']);
    }

    public function testEventSwitchedOffReachesNobody(): void
    {
        $this->givenPolicy(NotificationEvent::taskApproved(), []);

        $this->notifyUser(NotificationEvent::taskApproved());

        $this->assertSame([], $this->sentChannels());
    }

    public function testUserCanSilenceAChannelTheAdminAllows(): void
    {
        $this->givenPolicy(NotificationEvent::taskApproved(), ['email', 'in_app']);
        $this->givenUserChannels(['email' => false, 'in_app' => true]);

        $this->notifyUser(NotificationEvent::taskApproved());

        $this->assertSame(['in_app'], $this->sentChannels());
    }

    public function testUserPreferenceCannotOpenAChannelTheAdminClosed(): void
    {
        $this->givenPolicy(NotificationEvent::taskApproved(), ['email']);
        $this->givenUserChannels(['email' => true, 'in_app' => true]);

        $this->notifyUser(NotificationEvent::taskApproved());

        $this->assertSame(['email'], $this->sentChannels());
    }

    public function testUserWhoSilencedEveryAllowedChannelGetsNothing(): void
    {
        $this->givenPolicy(NotificationEvent::taskApproved(), ['email', 'in_app']);
        $this->givenUserChannels(['email' => false, 'in_app' => false]);

        $this->notifyUser(NotificationEvent::taskApproved());

        $this->assertSame([], $this->sentChannels());
    }

    public function testPoliciesAreKeptPerEvent(): void
    {
        $this->givenPolicy(NotificationEvent::taskApproved(), ['in_app']);
        $this->givenPolicy(NotificationEvent::taskCompleted(), ['email']);

        $this->notifyUser(NotificationEvent::taskApproved());
        $this->notifyUser(NotificationEvent::taskCompleted());

        $this->assertSame(['in_app', 'email'], $this->sentChannels());
    }

    public function testActivationEmailIgnoresPoliciesAndPreferences(): void
    {
        $this->givenUserChannels(['email' => false]);

        $this->orchestrator->notifyEmail('zosia@example.com', 'Aktywuj konto', 'Aktywacja');

        $this->assertSame(['email'], $this->sentChannels());
    }

    public function testUnknownUserIsNotNotified(): void
    {
        $this->orchestrator->notifyUser(NotificationEvent::taskApproved(), Uuid::generate(), 'Gotowe');

        $this->assertSame([], $this->sentChannels());
    }

    public function testAKindTheUserSwitchedOffReachesThemThroughNoChannel(): void
    {
        $settings = UserSettings::create($this->user->id());
        $settings->updatePreference(UserPreference::create(PreferenceType::notificationEvents(), [
            PreferenceOption::create('task_approved', false),
        ]));
        $this->settingsRepository->save($settings);

        $this->notifyUser(NotificationEvent::taskApproved());
        $this->notifyUser(NotificationEvent::payoutOffered());

        $this->assertSame(['in_app', 'push'], $this->sentChannels());
    }

    public function testNobodyIsToldAboutTheirOwnAction(): void
    {
        $orchestrator = new NotificationOrchestrator(
            new NotificationFacade([$this->adapter]),
            $this->userRepository,
            $this->settingsRepository,
            new NotificationPolicyProvider($this->policyRepository),
            new ChannelResolver(),
            null,
            new FixedActor($this->user->id())
        );

        $orchestrator->notifyUser(NotificationEvent::taskApproved(), $this->user->id(), 'Gotowe', 'Zadanie');

        $this->assertSame([], $this->sentChannels());
    }

    public function testEveryChannelGetsTheSameIdEventAndLifetime(): void
    {
        $this->notifyUser(NotificationEvent::streakAtRisk());

        $sent = $this->adapter->getSentNotifications();
        $this->assertSame('streak_at_risk', $sent[0]['parameters']['event']);
        $this->assertTrue(Uuid::isValid($sent[0]['parameters']['notification_id']));
        $this->assertSame(21600, $sent[0]['parameters']['ttl']);
    }

    public function testTwoNotificationsNeverShareAnId(): void
    {
        $this->notifyUser(NotificationEvent::payoutOffered());
        $this->notifyUser(NotificationEvent::payoutOffered());

        $ids = array_unique(array_map(
            static fn (array $sent) => $sent['parameters']['notification_id'],
            $this->adapter->getSentNotifications()
        ));
        $this->assertCount(2, $ids);
    }

    private function notifyUser(NotificationEvent $event): void
    {
        $this->orchestrator->notifyUser($event, $this->user->id(), 'Gotowe', 'Zadanie');
    }

    /**
     * @param list<string> $channels
     */
    private function givenPolicy(NotificationEvent $event, array $channels): void
    {
        $this->policyRepository->save(NotificationPolicy::create(
            Uuid::generate(),
            $event,
            NotificationChannels::fromArray($channels)
        ));
    }

    /**
     * @param array<string, bool> $channels
     */
    private function givenUserChannels(array $channels): void
    {
        $options = [];

        foreach ($channels as $name => $enabled) {
            $options[] = PreferenceOption::create($name, $enabled);
        }

        $settings = UserSettings::create($this->user->id());
        $settings->updatePreference(UserPreference::create(PreferenceType::notifications(), $options));

        $this->settingsRepository->save($settings);
    }

    /**
     * @return list<string>
     */
    private function sentChannels(): array
    {
        return array_column($this->adapter->getSentNotifications(), 'channel');
    }
}

final readonly class FixedActor implements ActorProviderInterface
{
    public function __construct(private ?Uuid $actor)
    {
    }

    public function currentActorId(): ?Uuid
    {
        return $this->actor;
    }
}
