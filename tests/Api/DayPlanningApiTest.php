<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

final class DayPlanningApiTest extends ApiTestCase
{
    private const FROM = '2026-09-21T00:00:00+02:00';
    private const TO = '2026-09-28T00:00:00+02:00';
    private const ROOT = '/api/day-planning';

    public function testPrivateDetailsNeverReachTeamAdminsAndTagFilteringKeepsBusyBlocks(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $tag = $this->json('POST', '/tags', ['name' => 'Praca', 'color' => '#226a4c', 'scope' => 'TEAM', 'teamId' => $team], 201);
        $author = $this->member($team);
        $this->loginAs($author);
        $event = $this->create(['teamId' => $team, 'tagIds' => [$tag['id']]]);
        $this->loginAs($admin);
        foreach ([[], [$tag['id']]] as $tags) {
            $calendar = $this->calendar($team, [$author->id()->value()], $tags);
            self::assertSame([], $calendar['events']);
            self::assertCount(1, $calendar['busy']);
            self::assertEqualsCanonicalizing(['kind', 'personId', 'start', 'end'], array_keys($calendar['busy'][0]));
            self::assertSame($author->id()->value(), $calendar['busy'][0]['personId']);
            self::assertStringNotContainsString('Secret consultation', json_encode($calendar));
            self::assertStringNotContainsString($event['id'], json_encode($calendar));
            self::assertTrue($calendar['coverage']['complete']);
        }
        foreach (['/events/'.$event['id'], '/events/'.$event['id'].'/occurrences/single'] as $path) {
            self::assertSame(404, $this->request('GET', $path)->getStatusCode());
        }
        self::assertSame(404, $this->request('PATCH', '/events/'.$event['id'], $this->payload(), $event['version'])->getStatusCode());
        self::assertSame(404, $this->request('DELETE', '/events/'.$event['id'], null, $event['version'])->getStatusCode());
    }

    public function testPrivateInviteeCanDeclineKeepDetailsAndRejoinButCannotEdit(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $invitee = $this->member($team);
        $this->loginAs($owner);
        $event = $this->create(['teamId' => $team, 'participantIds' => [$invitee->id()->value()]]);
        $this->loginAs($invitee);
        $entry = $this->json('GET', '/events/'.$event['id'].'/occurrences/single');
        self::assertSame('Secret consultation', $entry['title']);
        self::assertFalse($entry['canEdit']);
        self::assertTrue($entry['canChangeParticipation']);
        $this->json('PUT', '/events/'.$event['id'].'/participation/me', ['status' => 'DECLINED', 'occurrenceKey' => null]);
        self::assertSame('DECLINED', $this->json('GET', '/events/'.$event['id'].'/occurrences/single')['participation']);
        $free = $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO]);
        self::assertSame([], $free['busy']);
        self::assertSame(404, $this->request('PATCH', '/events/'.$event['id'], ['title' => 'Changed'], $event['version'])->getStatusCode());
        $this->json('PUT', '/events/'.$event['id'].'/participation/me', ['status' => 'INCLUDED', 'occurrenceKey' => null]);
        self::assertCount(1, $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO])['busy']);
    }

    public function testUninvitingRevokesDetailsAndBusyImmediately(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $invitee = $this->member($team);
        $this->loginAs($owner);
        $event = $this->create(['teamId' => $team, 'participantIds' => [$invitee->id()->value()]]);
        $this->json('PATCH', '/events/'.$event['id'], ['participantIds' => []], 200, $event['version']);
        $this->loginAs($invitee);
        self::assertSame(404, $this->request('GET', '/events/'.$event['id'].'/occurrences/single')->getStatusCode());
        self::assertSame([], $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO])['busy']);
    }

    public function testTeamSharingDoesNotOccupyUninvitedMembersAndCanBeRevoked(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($owner);
        $event = $this->create(['teamId' => $team, 'visibility' => 'TEAM']);
        $this->loginAs($member);
        $calendar = $this->calendar($team, [$owner->id()->value()]);
        self::assertSame($event['id'], $calendar['events'][0]['id']);
        self::assertSame([], $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO])['busy']);
        $this->loginAs($owner);
        $this->json('PATCH', '/events/'.$event['id'], ['visibility' => 'PRIVATE'], 200, $event['version']);
        $this->loginAs($member);
        self::assertSame([], $this->calendar($team, [$owner->id()->value()])['events']);
        self::assertSame(404, $this->request('GET', '/events/'.$event['id'].'/occurrences/single')->getStatusCode());
    }

    public function testNonBlockingPrivateEventsHaveNoPlaceholder(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($owner);
        $this->create(['blocksTime' => false]);
        $this->loginAs($member);
        $calendar = $this->calendar($team, [$owner->id()->value()]);
        self::assertSame([], $calendar['events']);
        self::assertSame([], $calendar['busy']);
    }

    public function testOtherTeamCommitmentsBlockPlanningWithoutLeakingTheirSource(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($member);
        $otherTeam = $this->team();
        $event = $this->create(['teamId' => $otherTeam, 'visibility' => 'TEAM']);
        $this->loginAs($owner);
        $calendar = $this->calendar($team, [$member->id()->value()]);
        self::assertSame([], $calendar['events']);
        self::assertCount(1, $calendar['busy']);
        self::assertStringNotContainsString($otherTeam, json_encode($calendar));
        self::assertStringNotContainsString($event['id'], json_encode($calendar));
        $result = $this->json('POST', '/planning/suggestions', $this->planning($team, [$member->id()->value()]));
        self::assertSame('2026-09-21T09:00:00+00:00', $result['slots'][0]['start']);
    }

    public function testSuggestionsAlwaysIncludeOrganizerEvenIfPayloadOmitsThem(): void
    {
        $team = $this->team();
        $owner = $this->currentUser;
        $member = $this->member($team);
        $this->loginAs($owner);
        $this->create();
        $result = $this->json('POST', '/planning/suggestions', $this->planning($team, [$member->id()->value()]));
        self::assertContains($owner->id()->value(), $result['personIds']);
        self::assertSame('2026-09-21T09:00:00+00:00', $result['slots'][0]['start']);
    }

    public function testArbitraryPeopleAndTeamsCannotBeQueriedOrInvited(): void
    {
        $team = $this->team();
        $owner = $this->currentUser;
        $outsider = $this->authenticate(Role::USER);
        $this->loginAs($owner);
        self::assertSame(403, $this->request('POST', '/planning/suggestions', $this->planning($team, [$outsider->id()->value()]))->getStatusCode());
        self::assertSame(403, $this->request('POST', '/events', $this->payload(['teamId' => $team, 'participantIds' => [$outsider->id()->value()]]))->getStatusCode());
        $this->loginAs($outsider);
        self::assertSame(403, $this->request('GET', '/calendar?'.http_build_query(['from' => self::FROM, 'to' => self::TO, 'teamId' => $team]))->getStatusCode());
    }

    public function testRepeatedCreationIsIdempotentAndStaleEditsCannotOverwrite(): void
    {
        $key = Uuid::generate()->value();
        $first = $this->json('POST', '/events', $this->payload(), 201, null, $key);
        $retry = $this->request('POST', '/events', $this->payload(), null, $key);
        self::assertContains($retry->getStatusCode(), [200, 201]);
        self::assertSame($first['id'], json_decode($retry->getContent(), true)['id']);
        self::assertCount(1, $this->calendar()['events']);
        $saved = $this->json('PATCH', '/events/'.$first['id'], ['title' => 'Updated'], 200, $first['version']);
        self::assertGreaterThan($first['version'], $saved['version']);
        self::assertSame(412, $this->request('PATCH', '/events/'.$first['id'], ['title' => 'Stale'], $first['version'])->getStatusCode());
        self::assertSame('Updated', $this->json('GET', '/events/'.$first['id'])['title']);
        self::assertSame(409, $this->request('POST', '/events', $this->payload(['title' => 'Different request']), null, $key)->getStatusCode());
    }

    public function testConflictsCanBeConfirmedWithoutExposingPrivateEventDetails(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($member);
        $private = $this->create();
        $this->loginAs($owner);
        $payload = $this->payload(['teamId' => $team, 'participantIds' => [$member->id()->value()]]);
        $conflict = $this->json('POST', '/events', $payload, 409);
        self::assertSame('planning_conflict', $conflict['code']);
        self::assertNotEmpty($conflict['confirmationToken']);
        self::assertStringNotContainsString('Secret consultation', json_encode($conflict));
        self::assertStringNotContainsString($private['id'], json_encode($conflict['conflicts']));
        $this->json('POST', '/events', [...$payload, 'conflictConfirmation' => $conflict['confirmationToken']], 201);
        self::assertCount(1, $this->calendar()['events']);
    }

    public function testWeeklyExceptionMovesIntoAnotherWindowAndCancellationPreservesRestOfSeries(): void
    {
        $series = $this->create(['recurrence' => ['frequency' => 'WEEKLY', 'interval' => 1, 'byDay' => [1], 'count' => 3]]);
        $first = $this->calendar()['events'][0];
        $moved = $this->json('PUT', '/events/'.$series['id'].'/exceptions/'.rawurlencode($first['occurrenceKey']), ['changes' => ['schedule' => ['kind' => 'TIMED', 'localStart' => '2026-10-06T12:00', 'durationMinutes' => 30, 'timeZone' => 'Europe/Warsaw']]], 200, $series['version']);
        self::assertSame([], $this->calendar()['events']);
        $october = $this->calendar(null, [], [], '2026-10-06T00:00:00+02:00', '2026-10-07T00:00:00+02:00');
        self::assertCount(1, $october['events']);
        self::assertSame($first['occurrenceKey'], $october['events'][0]['occurrenceKey']);
        $this->json('PUT', '/events/'.$series['id'].'/exceptions/'.rawurlencode($first['occurrenceKey']), ['cancelled' => true], 200, $moved['version']);
        self::assertSame([], $this->calendar(null, [], [], '2026-10-06T00:00:00+02:00', '2026-10-07T00:00:00+02:00')['events']);
        self::assertCount(2, $this->calendar(null, [], [], self::FROM, '2026-10-10T00:00:00+02:00')['events']);
    }

    public function testSingleOccurrenceInvitationDoesNotGrantAccessToOtherDatesOrSeriesDefinition(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $invitee = $this->member($team);
        $this->loginAs($owner);
        $series = $this->create(['teamId' => $team, 'recurrence' => ['frequency' => 'DAILY', 'interval' => 1, 'count' => 2]]);
        $occurrences = $this->calendar()['events'];
        $this->json('PUT', '/events/'.$series['id'].'/exceptions/'.rawurlencode($occurrences[0]['occurrenceKey']), ['changes' => ['participantIds' => [$owner->id()->value(), $invitee->id()->value()]]], 200, $series['version']);
        $this->loginAs($invitee);
        self::assertSame('Secret consultation', $this->json('GET', '/events/'.$series['id'].'/occurrences/'.rawurlencode($occurrences[0]['occurrenceKey']))['title']);
        self::assertSame(404, $this->request('GET', '/events/'.$series['id'].'/occurrences/'.rawurlencode($occurrences[1]['occurrenceKey']))->getStatusCode());
        self::assertSame(404, $this->request('GET', '/events/'.$series['id'])->getStatusCode());
    }

    public function testLeavingAndRejoiningTeamDoesNotRestoreOldPrivateInvitations(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($owner);
        $event = $this->create(['teamId' => $team, 'participantIds' => [$member->id()->value()]]);
        $memberships = static::getContainer()->get(TeamMembershipRepositoryInterface::class);
        $memberships->leave(Uuid::fromString($team), $member->id());
        $memberships->join(Uuid::fromString($team), $member->id(), TeamRole::member());
        $this->loginAs($member);
        self::assertSame(404, $this->request('GET', '/events/'.$event['id'].'/occurrences/single')->getStatusCode());
        self::assertSame([], $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO])['busy']);
    }

    public function testAllDayEventsUseLocalDatesAcrossDaylightSavingChange(): void
    {
        $this->create(['schedule' => ['kind' => 'ALL_DAY', 'startDate' => '2026-10-25', 'endDate' => '2026-10-26', 'timeZone' => 'Europe/Warsaw']]);
        $event = $this->calendar(null, [], [], '2026-10-24T00:00:00Z', '2026-10-27T00:00:00Z')['events'][0];
        self::assertTrue($event['allDay']);
        self::assertSame(25 * 3600, strtotime($event['end']) - strtotime($event['start']));
    }

    public function testInvalidInputAndIncompleteRangesFailWithoutPartialCalendar(): void
    {
        foreach ([['title' => ''], ['schedule' => ['kind' => 'TIMED', 'localStart' => '2026-03-29T02:30', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']], ['recurrence' => ['frequency' => 'WEEKLY', 'interval' => 0, 'byDay' => [1]]]] as $invalid) {
            self::assertSame(422, $this->request('POST', '/events', $this->payload($invalid))->getStatusCode());
        }
        self::assertSame(422, $this->request('GET', '/calendar?'.http_build_query(['from' => self::FROM, 'to' => '2027-09-28T00:00:00Z']))->getStatusCode());
        self::assertSame([], $this->calendar()['events']);
    }

    public function testInvitationNotificationsAreNeutralAndIdempotent(): void
    {
        $owner = $this->currentUser;
        $team = $this->team();
        $guest = $this->member($team);
        $observer = $this->member($team);
        $this->loginAs($owner);
        $payload = $this->payload(['teamId' => $team, 'participantIds' => [$guest->id()->value()]]);
        $key = Uuid::generate()->value();
        $event = $this->json('POST', '/events', $payload, 201, null, $key);
        $this->request('POST', '/events', $payload, null, $key);
        $this->loginAs($guest);
        $notifications = $this->getJson('/api/notifications')['notifications'];
        self::assertCount(1, $notifications);
        self::assertSame('/day-planning', $notifications[0]['parameters']['url']);
        self::assertStringNotContainsString('Secret consultation', json_encode($notifications));
        self::assertStringNotContainsString($event['id'], json_encode($notifications));
        self::assertArrayNotHasKey('_calendar', $notifications[0]['parameters']);
        $this->loginAs($observer);
        self::assertSame([], $this->getJson('/api/notifications')['notifications']);
    }

    public function testDayPlanningNavigationCanBeHiddenAfterInitiallyBeingAvailable(): void
    {
        $initial = $this->getJson('/api/personalisation');
        self::assertContains('day-planning', $initial['navigation']);
        $this->assertJsonResponse($this->putJson('/api/personalisation', ['navigation' => ['tasks', 'teams']]));
        $saved = $this->getJson('/api/personalisation');
        self::assertNotContains('day-planning', $saved['navigation']);
        self::assertContains('day-planning', $saved['places']['navigation']);
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $this->client->getCookieJar()->clear();
        self::assertSame(401, $this->request('GET', '/calendar?'.http_build_query(['from' => self::FROM, 'to' => self::TO]))->getStatusCode());
        self::assertSame(401, $this->request('POST', '/events', $this->payload())->getStatusCode());
    }

    public function testTeamAdminWritesIntoAnotherCalendarWithoutSpendingAnyOfTheirOwnTime(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($admin);
        $own = $this->create();
        $event = $this->create(['teamId' => $team, 'participantIds' => [$member->id()->value()], 'ownerParticipates' => false]);
        self::assertSame([['personId' => $member->id()->value(), 'status' => 'INCLUDED']], $event['participants']);
        self::assertSame($admin->id()->value(), $event['ownerId']);
        self::assertSame([$own['id']], array_column($this->calendar()['events'], 'id'));
        self::assertCount(1, $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO])['busy']);
        $written = $this->calendar($team, [$member->id()->value()])['events'][0];
        self::assertSame($event['id'], $written['id']);
        self::assertTrue($written['canEdit']);
        self::assertFalse($written['canChangeParticipation']);
        self::assertNull($written['participation']);
        $this->loginAs($member);
        $entry = $this->json('GET', '/events/'.$event['id'].'/occurrences/single');
        self::assertSame('Secret consultation', $entry['title']);
        self::assertFalse($entry['canEdit']);
        self::assertTrue($entry['canChangeParticipation']);
        self::assertCount(1, $this->json('POST', '/availability/query', ['from' => self::FROM, 'to' => self::TO])['busy']);
    }

    public function testWritingIntoAnotherCalendarNeedsAdminRightsATeamAndSomebodyToAttend(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $guest = $this->member($team);
        $this->loginAs($admin);
        foreach ([['participantIds' => []], ['teamId' => null, 'participantIds' => [$guest->id()->value()]]] as $invalid) {
            self::assertSame(422, $this->request('POST', '/events', $this->payload($invalid + ['teamId' => $team, 'ownerParticipates' => false]))->getStatusCode());
        }
        $this->loginAs($member);
        self::assertSame(403, $this->request('POST', '/events', $this->payload(['teamId' => $team, 'participantIds' => [$guest->id()->value()], 'ownerParticipates' => false]))->getStatusCode());
    }

    public function testTheAuthorStaysOutOfTheEventUntilTheyPutThemselvesBackIn(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($admin);
        $event = $this->create(['teamId' => $team, 'participantIds' => [$member->id()->value()], 'ownerParticipates' => false]);
        $renamed = $this->json('PATCH', '/events/'.$event['id'], ['title' => 'Dentist for you'], 200, $event['version']);
        self::assertSame([$member->id()->value()], $renamed['participantIds']);
        self::assertSame([], $this->calendar()['events']);
        $joined = $this->json('PATCH', '/events/'.$event['id'], ['ownerParticipates' => true], 200, $renamed['version']);
        self::assertEqualsCanonicalizing([$admin->id()->value(), $member->id()->value()], $joined['participantIds']);
        self::assertSame([$event['id']], array_column($this->calendar()['events'], 'id'));
    }

    public function testTheAdminsOwnCommitmentsDoNotCollideWithWhatTheyWriteForSomebodyElse(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $member = $this->member($team);
        $this->loginAs($admin);
        $this->create();
        $written = $this->payload(['teamId' => $team, 'participantIds' => [$member->id()->value()], 'ownerParticipates' => false]);
        self::assertSame([$member->id()->value()], $this->json('POST', '/events', $written, 201)['participantIds']);
        $conflict = $this->json('POST', '/events', $written, 409);
        self::assertSame([$member->id()->value()], array_column($conflict['conflicts'], 'personId'));
    }

    public function testAnEventItsAuthorNeverAttendsEndsWhenNobodyIsLeftToAttendIt(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $guest = $this->member($team);
        $other = $this->member($team);
        $this->loginAs($admin);
        $abandoned = $this->create(['teamId' => $team, 'participantIds' => [$guest->id()->value()], 'ownerParticipates' => false]);
        $orphaned = $this->create(['teamId' => $team, 'participantIds' => [$other->id()->value()], 'ownerParticipates' => false]);
        $memberships = static::getContainer()->get(TeamMembershipRepositoryInterface::class);
        $memberships->leave(Uuid::fromString($team), $guest->id());
        self::assertSame(404, $this->request('GET', '/events/'.$abandoned['id'])->getStatusCode());
        self::assertSame(200, $this->request('GET', '/events/'.$orphaned['id'])->getStatusCode());
        $memberships->leave(Uuid::fromString($team), $admin->id());
        self::assertSame(404, $this->request('GET', '/events/'.$orphaned['id'])->getStatusCode());
        $this->loginAs($guest);
        self::assertSame(404, $this->request('GET', '/events/'.$abandoned['id'].'/occurrences/single')->getStatusCode());
    }

    private function team(): string
    {
        return $this->assertJsonResponse($this->postJson('/api/teams', ['name' => 'Calendar family', 'description' => null]), 201)['id'];
    }

    private function member(string $team): User
    {
        $member = $this->authenticate(Role::USER);
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join(Uuid::fromString($team), $member->id(), TeamRole::member());

        return $member;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Secret consultation', 'description' => 'Private notes', 'location' => 'Private place',
            'teamId' => null, 'visibility' => 'PRIVATE',
            'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T10:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw'],
            'recurrence' => null, 'participantIds' => [], 'tagIds' => [], 'blocksTime' => true,
        ], $overrides);
    }

    private function create(array $overrides = []): array
    {
        return $this->json('POST', '/events', $this->payload($overrides), 201);
    }

    private function calendar(?string $team = null, array $people = [], array $tags = [], string $from = self::FROM, string $to = self::TO): array
    {
        return $this->json('GET', '/calendar?'.http_build_query(array_filter(['from' => $from, 'to' => $to, 'teamId' => $team, 'personIds' => $people, 'tagIds' => $tags], static fn ($value) => $value !== null)));
    }

    private function planning(string $team, array $people): array
    {
        return ['teamId' => $team, 'personIds' => $people, 'from' => self::FROM, 'to' => '2026-09-22T00:00:00+02:00', 'durationMinutes' => 60, 'windowStart' => '10:00', 'windowEnd' => '12:00', 'timeZone' => 'Europe/Warsaw'];
    }

    private function json(string $method, string $path, ?array $payload = null, int $status = 200, ?int $version = null, ?string $idempotencyKey = null): array
    {
        $response = $this->request($method, $path, $payload, $version, $idempotencyKey);
        self::assertSame($status, $response->getStatusCode(), $response->getContent());

        return json_decode($response->getContent(), true);
    }

    private function request(string $method, string $path, ?array $payload = null, ?int $version = null, ?string $idempotencyKey = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($version !== null) {
            $headers['HTTP_IF_MATCH'] = '"'.$version.'"';
        }
        if ($idempotencyKey !== null) {
            $headers['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
        }
        $this->client->request($method, self::ROOT.$path, [], [], $headers, $payload === null ? null : json_encode($payload));

        return $this->client->getResponse();
    }
}
