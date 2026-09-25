<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Domain\Port\PushRetractionInterface;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Notifications\Infrastructure\Adapter\PushRetractionAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class NotificationApiTest extends ApiTestCase
{
    public function testItListsTheNotificationsOfTheCaller(): void
    {
        $this->sendTo($this->currentUser, 'You earned 10 points', 'Task approved', ['points' => 10]);

        $data = $this->getJson('/api/notifications');

        $this->assertCount(1, $data['notifications']);
        $this->assertSame(1, $data['unreadCount']);
        $this->assertSame('You earned 10 points', $data['notifications'][0]['message']);
        $this->assertSame('Task approved', $data['notifications'][0]['subject']);
        $this->assertSame(['points' => 10], $data['notifications'][0]['parameters']);
        $this->assertNull($data['notifications'][0]['readAt']);
    }

    public function testItNeverListsSomebodyElsesNotifications(): void
    {
        $stranger = $this->anotherUser();
        $this->sendTo($stranger, 'Not for you');
        $this->sendTo($this->currentUser, 'For you');

        $data = $this->getJson('/api/notifications');

        $this->assertCount(1, $data['notifications']);
        $this->assertSame('For you', $data['notifications'][0]['message']);
    }

    public function testItListsOnlyTheUnreadOnesWhenAsked(): void
    {
        $this->sendTo($this->currentUser, 'Already seen');
        $this->sendTo($this->currentUser, 'Still new');

        $seen = $this->getJson('/api/notifications')['notifications'];
        $alreadySeen = array_values(array_filter(
            $seen,
            static fn (array $notification) => $notification['message'] === 'Already seen'
        ))[0];

        $this->postJson(sprintf('/api/notifications/%s/read', $alreadySeen['id']), []);

        $data = $this->getJson('/api/notifications?unread=1');

        $this->assertCount(1, $data['notifications']);
        $this->assertSame('Still new', $data['notifications'][0]['message']);
        $this->assertSame(1, $data['unreadCount']);
    }

    public function testMarkingOneAsReadStampsItAndDropsTheCount(): void
    {
        $this->sendTo($this->currentUser, 'Read me');
        $id = $this->getJson('/api/notifications')['notifications'][0]['id'];

        $data = $this->assertJsonResponse($this->postJson(sprintf('/api/notifications/%s/read', $id), []));

        $this->assertNotNull($data['notification']['readAt']);
        $this->assertSame(0, $data['unreadCount']);
    }

    public function testReadingNewsTakesItOutOfThePhoneTraysToo(): void
    {
        $retractions = $this->recordRetractions();
        $this->sendTo($this->currentUser, 'Ola czeka na akceptację', null, ['tag' => 'task-7']);
        $id = $this->notifications()->unreadFor($this->currentUser->id(), 10)[0]->id()->value();

        $this->assertJsonResponse($this->postJson(sprintf('/api/notifications/%s/read', $id), []));
        $this->assertJsonResponse($this->postJson(sprintf('/api/notifications/%s/read', $id), []));

        $this->assertSame([['users' => [$this->currentUser->id()->value()], 'tags' => ['task-7']]], $retractions->calls);
    }

    public function testReadingEverythingRetractsOnlyWhatWasStillNews(): void
    {
        $retractions = $this->recordRetractions();
        $this->sendTo($this->currentUser, 'Pierwsze');
        $this->sendTo($this->currentUser, 'Kuba czekał na akceptację', null, ['tag' => 'task-8', 'event' => 'task_completed']);
        $this->notifications()->resolveTopic('task-8', new \DateTimeImmutable(), 'task_completed');
        $first = $this->notifications()->unreadFor($this->currentUser->id(), 10)[0];

        $this->assertJsonResponse($this->postJson('/api/notifications/read-all', []));

        $this->assertSame([['users' => [$this->currentUser->id()->value()], 'tags' => [$first->id()->value()]]], $retractions->calls);
    }

    public function testItRefusesToMarkSomebodyElsesNotification(): void
    {
        $stranger = $this->anotherUser();
        $this->sendTo($stranger, 'Not for you');

        $notification = $this->notifications()->unreadFor($stranger->id(), 10)[0];

        $response = $this->postJson(sprintf('/api/notifications/%s/read', $notification->id()->value()), []);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertFalse($this->notifications()->findById($notification->id())->isRead());
    }

    public function testAnUnknownNotificationIsNotFound(): void
    {
        $response = $this->postJson(sprintf('/api/notifications/%s/read', Uuid::generate()->value()), []);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMarkingAllAsReadLeavesOtherUsersAlone(): void
    {
        $stranger = $this->anotherUser();
        $this->sendTo($stranger, 'Not for you');
        $this->sendTo($this->currentUser, 'First');
        $this->sendTo($this->currentUser, 'Second');

        $data = $this->assertJsonResponse($this->postJson('/api/notifications/read-all', []));

        $this->assertSame(2, $data['marked']);
        $this->assertSame(0, $data['unreadCount']);
        $this->assertSame(0, $this->getJson('/api/notifications')['unreadCount']);
        $this->assertCount(1, $this->notifications()->unreadFor($stranger->id(), 10));
    }

    public function testAHandledNotificationIsNoLongerUnread(): void
    {
        $this->sendTo($this->currentUser, 'Ola czeka na akceptację', 'Zadanie do akceptacji', ['event' => 'task_completed', 'tag' => 'task-42']);
        $this->sendTo($this->currentUser, 'Kieszonkowe czeka', null, ['event' => 'payout_offered', 'tag' => 'payout-7']);

        $this->notifications()->resolveTopic('task-42', new \DateTimeImmutable(), 'task_completed');

        $unread = $this->getJson('/api/notifications?unread=1');
        $this->assertSame(['Kieszonkowe czeka'], array_column($unread['notifications'], 'message'));
        $this->assertSame(1, $unread['unreadCount']);

        $all = array_column($this->getJson('/api/notifications')['notifications'], null, 'message');
        $this->assertFalse($all['Ola czeka na akceptację']['active']);
        $this->assertNotNull($all['Ola czeka na akceptację']['resolvedAt']);
        $this->assertSame('task_completed', $all['Ola czeka na akceptację']['event']);
        $this->assertSame('task-42', $all['Ola czeka na akceptację']['topic']);
        $this->assertTrue($all['Kieszonkowe czeka']['active']);
    }

    public function testAnExpiredNotificationIsNoLongerUnread(): void
    {
        $this->sendTo($this->currentUser, 'Seria zaraz przepadnie', null, ['expires_at' => (new \DateTimeImmutable('-1 minute'))->format(DATE_ATOM)]);
        $this->sendTo($this->currentUser, 'Jutro też jest dzień', null, ['expires_at' => (new \DateTimeImmutable('+1 day'))->format(DATE_ATOM)]);

        $unread = $this->getJson('/api/notifications?unread=1');

        $this->assertSame(['Jutro też jest dzień'], array_column($unread['notifications'], 'message'));
    }

    public function testUnreadOnesComeNewestFirst(): void
    {
        foreach (['Pierwsze', 'Drugie', 'Trzecie'] as $message) {
            $this->sendTo($this->currentUser, $message);
            sleep(1);
        }

        $unread = $this->getJson('/api/notifications?unread=1&limit=2');

        $this->assertSame(['Trzecie', 'Drugie'], array_column($unread['notifications'], 'message'));
        $this->assertSame(3, $unread['unreadCount']);
    }

    public function testTheListedOnesCanBeReadTogether(): void
    {
        $stranger = $this->anotherUser();
        $this->sendTo($this->currentUser, 'Pierwsze');
        $this->sendTo($this->currentUser, 'Drugie');
        $this->sendTo($this->currentUser, 'Trzecie');
        $this->sendTo($stranger, 'Cudze');
        $mine = array_column($this->getJson('/api/notifications')['notifications'], 'id', 'message');
        $theirs = $this->notifications()->unreadFor($stranger->id(), 10)[0]->id()->value();

        $response = $this->postJson('/api/notifications/read-all', ['ids' => [$mine['Pierwsze'], $mine['Drugie'], $theirs, 'nonsense']]);

        $data = $this->assertJsonResponse($response);
        $this->assertSame(2, $data['marked']);
        $this->assertSame(1, $data['unreadCount']);
        $this->assertSame(['Trzecie'], array_column($this->getJson('/api/notifications?unread=1')['notifications'], 'message'));
        $this->assertCount(1, $this->notifications()->unreadFor($stranger->id(), 10));
    }

    public function testTheCallerChoosesWhichKindsInterestThem(): void
    {
        $events = array_column($this->getJson('/api/notifications/preferences')['events'], null, 'event');
        $this->assertTrue($events['task_assigned']['enabled']);
        $this->assertSame('tasks', $events['task_assigned']['group']);
        $this->assertArrayNotHasKey('account_activation', $events);

        $response = $this->putJson('/api/notifications/preferences', ['events' => ['task_assigned' => false, 'streak_at_risk' => false]]);

        $changed = array_column($this->assertJsonResponse($response)['events'], 'enabled', 'event');
        $this->assertFalse($changed['task_assigned']);
        $this->assertFalse($changed['streak_at_risk']);
        $this->assertTrue($changed['calendar_changed']);
        $this->assertFalse(array_column($this->getJson('/api/notifications/preferences')['events'], 'enabled', 'event')['task_assigned']);
    }

    public function testAKindThatCannotBeSwitchedOffIsRefused(): void
    {
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->putJson('/api/notifications/preferences', ['events' => ['account_activation' => false]])->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->putJson('/api/notifications/preferences', ['events' => ['task_assigned' => 'no']])->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->putJson('/api/notifications/preferences', ['events' => 'all'])->getStatusCode());
    }

    public function testItAnswersAnonymousCallersWithUnauthorized(): void
    {
        $this->client->request('POST', '/api/auth/logout');
        $this->client->request('GET', '/api/notifications', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    private function sendTo(User $user, string $message, ?string $subject = null, array $parameters = []): void
    {
        static::getContainer()
            ->get(NotificationFacade::class)
            ->sendInApp($user->id()->value(), $message, $subject, $parameters);
    }

    /**
     * Retractions travel to phones through the queue; here they only get written down.
     */
    private function recordRetractions(): RetractionsLog
    {
        $this->client->disableReboot();
        $retractions = new RetractionsLog();
        static::getContainer()->set(PushRetractionAdapter::class, $retractions);
        static::getContainer()->set(PushRetractionInterface::class, $retractions);

        return $retractions;
    }

    private function notifications(): InAppNotificationRepositoryInterface
    {
        return static::getContainer()->get(InAppNotificationRepositoryInterface::class);
    }

    private function anotherUser(): User
    {
        $user = User::create(
            Uuid::generate(),
            'Someone Else',
            Email::fromString(sprintf('stranger-%s@example.com', uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );

        static::getContainer()->get(UserRepositoryInterface::class)->save($user);

        return $user;
    }
}

final class RetractionsLog implements PushRetractionInterface
{
    /**
     * @var list<array{users: list<string>, tags: list<string>}>
     */
    public array $calls = [];

    public function retract(array $userIds, array $tags): void
    {
        $this->calls[] = ['users' => array_map(static fn (Uuid $userId): string => $userId->value(), $userIds), 'tags' => $tags];
    }
}
