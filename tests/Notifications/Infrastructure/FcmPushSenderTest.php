<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;
use App\Notifications\Infrastructure\Push\FcmPushSender;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FcmPushSenderTest extends TestCase
{
    private static string $privateKey;

    private DateTimeImmutable $now;

    /** @var list<array{method: string, url: string, options: array}> */
    private array $requests = [];

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        self::$privateKey = $pem;
    }

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-25 18:00:00+00:00');
        $this->requests = [];
    }

    public function testWithoutAServiceAccountPhonesAreOff(): void
    {
        $sender = $this->sender([], '');

        $this->assertFalse($sender->isConfigured());
        $this->assertSame(PushDelivery::Failed, $sender->send($this->device(), $this->message(), $this->options()));
        $this->assertSame([], $this->requests);
    }

    public function testAnythingThatIsNotAServiceAccountKeepsItOff(): void
    {
        $this->assertFalse($this->sender([], '{"type":"service_account"}')->isConfigured());
        $this->assertFalse($this->sender([], 'not json at all')->isConfigured());
    }

    public function testTheAccountMayComeBase64Encoded(): void
    {
        $this->assertTrue($this->sender([], base64_encode($this->account()))->isConfigured());
    }

    public function testItSignsInAndSendsANotificationThePhoneShowsByItself(): void
    {
        $sender = $this->sender([$this->token(), new MockResponse('{"name":"projects/family-plan/messages/1"}')]);

        $delivery = $sender->send($this->device(), $this->message(), $this->options());

        $this->assertSame(PushDelivery::Delivered, $delivery);
        $this->assertSame('https://oauth2.googleapis.com/token', $this->requests[0]['url']);
        $this->assertStringContainsString('grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer', $this->requests[0]['options']['body']);

        $send = $this->requests[1];
        $this->assertSame('https://fcm.googleapis.com/v1/projects/family-plan/messages:send', $send['url']);
        $this->assertContains('Authorization: Bearer access-1', $send['options']['headers']);
        $message = json_decode($send['options']['body'], true)['message'];
        $this->assertSame('phone-token', $message['token']);
        $this->assertSame(['title' => 'Zadanie do akceptacji', 'body' => 'Ola czeka na akceptację zadania.'], $message['notification']);
        $this->assertSame(['url' => '/tasks', 'tag' => 'task-1', 'notificationId' => '0b9d1f6e-5b7a-4d52-9d61-1c8b2f0f2a11', 'event' => 'task_completed'], $message['data']);
        $this->assertSame('HIGH', $message['android']['priority']);
        $this->assertSame('43200s', $message['android']['ttl']);
        $this->assertSame(32, strlen($message['android']['collapse_key']));
        $this->assertSame(['channel_id' => 'family-plan', 'tag' => 'task-1'], $message['android']['notification']);
    }

    public function testARetractionIsASilentMessageTheAppReadsTheTagsFrom(): void
    {
        $sender = $this->sender([$this->token(), new MockResponse('{"name":"projects/family-plan/messages/2"}')]);

        $delivery = $sender->retract($this->device(), ['task-1', 'payout-2']);

        $this->assertSame(PushDelivery::Delivered, $delivery);
        $message = json_decode($this->requests[1]['options']['body'], true)['message'];
        $this->assertSame('phone-token', $message['token']);
        $this->assertArrayNotHasKey('notification', $message);
        $this->assertSame(['type' => 'retract', 'tags' => '["task-1","payout-2"]'], $message['data']);
        $this->assertSame(['priority' => 'HIGH', 'ttl' => '172800s'], $message['android']);
    }

    public function testARetractionForAPhoneFirebaseDoesNotKnowSaysItIsGone(): void
    {
        $unregistered = new MockResponse('{"error":{"code":404,"status":"NOT_FOUND"}}', ['http_code' => 404]);

        $this->assertSame(PushDelivery::Gone, $this->sender([$this->token(), $unregistered])->retract($this->device(), ['task-1']));
        $this->assertSame(PushDelivery::Failed, $this->sender([], '')->retract($this->device(), ['task-1']));
    }

    public function testTheAccessTokenIsReusedUntilItExpires(): void
    {
        $sender = $this->sender([$this->token(), new MockResponse('{}'), new MockResponse('{}')]);

        $sender->send($this->device(), $this->message(), $this->options());
        $sender->send($this->device(), $this->message(), $this->options());

        $this->assertCount(3, $this->requests);
        $this->assertStringContainsString('fcm.googleapis.com', $this->requests[2]['url']);
    }

    public function testAPhoneFirebaseDoesNotKnowIsGone(): void
    {
        $unregistered = new MockResponse(
            '{"error":{"code":404,"status":"NOT_FOUND","message":"Requested entity was not found.","details":[{"@type":"type.googleapis.com/google.firebase.fcm.v1.FcmError","errorCode":"UNREGISTERED"}]}}',
            ['http_code' => 404]
        );

        $this->assertSame(PushDelivery::Gone, $this->sender([$this->token(), $unregistered])->send($this->device(), $this->message(), $this->options()));
    }

    public function testAnInvalidTokenIsGoneButAMalformedMessageIsNot(): void
    {
        $badToken = new MockResponse('{"error":{"code":400,"status":"INVALID_ARGUMENT","message":"The registration token is not a valid FCM registration token"}}', ['http_code' => 400]);
        $badMessage = new MockResponse('{"error":{"code":400,"status":"INVALID_ARGUMENT","message":"Invalid value at message.android.ttl"}}', ['http_code' => 400]);

        $this->assertSame(PushDelivery::Gone, $this->sender([$this->token(), $badToken])->send($this->device(), $this->message(), $this->options()));
        $this->assertSame(PushDelivery::Failed, $this->sender([$this->token(), $badMessage])->send($this->device(), $this->message(), $this->options()));
    }

    public function testAnOutageKeepsThePhone(): void
    {
        $unavailable = new MockResponse('{"error":{"code":503,"status":"UNAVAILABLE"}}', ['http_code' => 503]);

        $this->assertSame(PushDelivery::Failed, $this->sender([$this->token(), $unavailable])->send($this->device(), $this->message(), $this->options()));
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function sender(array $responses, ?string $account = null): FcmPushSender
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });

        return new FcmPushSender($client, new FixedClock($this->now), $account ?? $this->account());
    }

    private function account(): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => 'family-plan',
            'client_email' => 'push@family-plan.iam.gserviceaccount.com',
            'private_key' => self::$privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR);
    }

    private function token(): MockResponse
    {
        return new MockResponse('{"access_token":"access-1","expires_in":3599,"token_type":"Bearer"}');
    }

    private function device(): NativePushDevice
    {
        return NativePushDevice::register(Uuid::generate(), Uuid::generate(), NativePushDevice::ANDROID, 'phone-token', null, $this->now);
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::create('Ola czeka na akceptację zadania.', 'Zadanie do akceptacji', [
            'url' => '/tasks',
            'tag' => 'task-1',
            'event' => 'task_completed',
            'notification_id' => '0b9d1f6e-5b7a-4d52-9d61-1c8b2f0f2a11',
        ]);
    }

    private function options(): PushOptions
    {
        return PushOptions::from($this->message()->additionalParameters(), $this->now);
    }
}
